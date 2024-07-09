<?php
namespace jtgraham38\modelinput;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class ModelInputServiceProvider extends ServiceProvider
{
    const FIELD_TO_INPUT_TYPE_MAP = [
        'string' => 'text',
        'text' => 'textarea',
        'integer' => 'number',
        'smallint' => 'number',
        'bigint' => 'number',
        'decimal' => 'number',
        'float' => 'number',
        'boolean' => 'checkbox',
        'date' => 'date',
        'datetime' => 'datetime-local',
        'time' => 'time',
        'json' => 'text',
        'jsonb' => 'text',
        'binary' => 'file',
        'blob' => 'file',
        'guid' => 'text',
        'uuid' => 'text',
        'array' => 'text',
        'simple_array' => 'text',
        'object' => 'text',
        'json_array' => 'text',
    ];

    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Blade::directive('modelInput', function ($expression) {
            //parse the expression
            //NOTE: this needs to be enhanced for error checking
            list($model, $field, $args) = explode(',', $expression, 3);
            $model = trim($model);
            $field = trim($field);
            $args = eval("return $args;");
            /*
            $args can have the following keys:
                - container_classes: classes to apply to the container
                - label_classes: classes to apply to the label
                - input_classes: classes to apply to the input
                - label_text: text to display in the label
                - attributes: array of attributes to apply to the input.  These will override any rules applied by the directive
                        - this array may contain any key value pair that can be applied to an input element, ex. 'disabled' => true
                    
            */

            //dump($args['attributes']);

            //get model and table name
            $model_class = app()->getNamespace() . "Models\\" . $model; 
            $instance = resolve($model_class);
            $table_name = $instance->getTable();

            //get data schema for table
            $schema = [];
            $db = DB::connection()->getDoctrineSchemaManager();
            $doctrine_column = $db->listTableDetails($table_name)->getColumn($field);
            
            //build the schema for the column
            $schema = array(
                'type' => $doctrine_column->getType()->getName(),
                'length' => $doctrine_column->getLength(),
                'precision' => $doctrine_column->getPrecision(),
                'scale' => $doctrine_column->getScale(),
                'unsigned' => $doctrine_column->getUnsigned(),
                'fixed' => $doctrine_column->getFixed(),
                'notnull' => $doctrine_column->getNotnull(),
                'autoincrement' => $doctrine_column->getAutoincrement(),
                'default' => $doctrine_column->getDefault(),
                'comment' => $doctrine_column->getComment(),
            );

            

            
            //generate string formation of attributes for input (class="", id="", etc.)
            $attributes_string = $this->generateInputAttributesString($this->generateInputRules($schema), $args['attributes'] ?? []);
            
            //generate id attribute
            $id = $this->generateInputId($field, $args['id_suffix'] ?? '');

            //build string to echo
            $echo_str = '
                <div class="' . (isset($args['container_classes']) ? $args['container_classes'] : '') . '">
                    <label for="' . (isset($args['attributes']['id']) ? $args['attributes']['id'] : $id) . '" class="' . (isset($args['label_classes']) ? $args['label_classes'] : '') . '">' . (isset($args['label_text']) ? $args['label_text'] : $field) . '</label>
                    <input 
                        type="' . (isset($args['attributes']['type']) ? $args['attributes']['type'] : self::FIELD_TO_INPUT_TYPE_MAP[$schema['type']]) . '"
                        name="' . (isset($args['attributes']['name']) ? $args['attributes']['name'] : $field) . '"
                        id="' . (isset($args['attributes']['id']) ? $args['attributes']['id'] : $id) . '"
                        class="' . (isset($args['input_classes']) ? $args['input_classes'] : '') . '"
                        ' . $attributes_string . '
                    >
                </div>
            ';

            return "<?php echo '" . $echo_str . "'; ?>";
        });
    }

    private function generateInputRules($schema): array
    {
        //base rules
        $rules = [
            'required' => false,
            'min' => null,
            'max' => null,
            'minlength' => null,
            'maxlength' => null,
            'disabled' => false,
            'readonly' => false,
            'step' => null,
            'pattern' => null,
            'placeholder' => null,
            'autocomplete' => null,
            'autofocus' => false,
            'multiple' => false,
        ];

        //change rules based on schema
        /*
        Schema keys:
                'type' => $doctrine_column->getType()->getName(),
                'length' => $doctrine_column->getLength(),
                'precision' => $doctrine_column->getPrecision(),
                'scale' => $doctrine_column->getScale(),
                'unsigned' => $doctrine_column->getUnsigned(),
                'fixed' => $doctrine_column->getFixed(),
                'notnull' => $doctrine_column->getNotnull(),
                'autoincrement' => $doctrine_column->getAutoincrement(),
                'default' => $doctrine_column->getDefault(),
                'comment' => $doctrine_column->getComment(),
        */
        //required
        if ($schema['notnull']) {
            $rules['required'] = true;
        }
        //rules for text input types
        switch (self::FIELD_TO_INPUT_TYPE_MAP[$schema['type']]){
            case 'textarea':
            case 'text':
                //set initial min and max lengths
                $rules['minlength'] = 0;
                $rules['maxlength'] = $schema['length'];

                //check if required
                if ($schema['notnull']) {
                    $rules['minlength'] = 1;
                }

                //check if fixed length
                if ($schema['fixed']) {
                    $rules['minlength'] = $schema['length'];
                    $rules['maxlength'] = $schema['length'];
                }

                break;
            case 'number':
                //calculate max and min value the field could hold
                $max = null;
                $min = null;
                $total_digits = $schema['precision'];
                $decimal_digits = $schema['scale'];
                $whole_digits = $total_digits - $decimal_digits;
                if ($schema['unsigned']) {
                    $max = pow(10, $total_digits) - 1;
                    $min = 0;
                } else {
                    $max = pow(10, $whole_digits) - 1;
                    $min = -1 * pow(10, $whole_digits);
                }

                //set min and max values
                $rules['min'] = $min;
                $rules['max'] = $max;

                //calculate step value
                $step = pow(10, $decimal_digits * -1);
                $rules['step'] = $step;
            case 'checkbox':
                //no additional rules needed
                break;
            case 'date':
                //set the default to the current day
                $rules['value'] = date('Y-m-d');
                break;
            case 'datetime-local':
                //set the default to the current date and time
                $rules['value'] = date('Y-m-d\TH:i');
                break;
            case 'time':
                //set the default to the current time
                $rules['value'] = date('H:i');
                break;
            case 'file':
                //no additional rules needed... yet!
                break;
        }

        //return rules
        return $rules;


    }

    private function generateInputAttributesString($user, $directive): string
    {
        //combine the two arrays, keeping the user values when there are conflicts
        $args = array_merge($user, $directive); //records are kept from the second array passed in, so keep the ones explicitly passed in by the user
        //dump($user, $directive);
        //dump($args, $args);

        $attributes_string = '';
        foreach ($args as $key => $value) {
            //remove attrs handled separately
            if (in_array($key, ['id', 'name', 'type', 'class'])) continue;
                
            //skip empty values
            if (!($value === null)) continue;

            //add the attribute to the string
            $attributes_string .= $key . '="' . $value . '" ';
        }

        return $attributes_string;
    }

    private function generateInputId($field, $suffix): string
    {
        return $field . '_input_' . $suffix;
    }
}
