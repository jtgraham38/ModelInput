# [Laravel ModelInput](https://php.jacob-t-graham.com/#jtgraham38/modelinput)

##Important Note
Currently, in order to correctly set the value on the generated input based on a model value, you must pass a php statement as a string to the directive.  This is due to the way Laravel caches views.  A better fix is in progress.  Example:
```php
@modelInput(User, email, [
        ...
        'attributes' => [
            ...
            'value' => "auth()->user()->email",
            ...
        ]
    ])
```

## The Problem
A common feature in web apps is forms that are used to perform CREATE, READ, UPDATE, and DELETE (CRUD) operations using the models that have been defined in the Laravel app.  In good web app design, any problems with the data the user entered on the form should be shown to the user before a request is made by the form.  HTML has validators, like _required_, _minlength=xxx_, _step=xxx_, etc.  But in order to use these properly, the programmer needs to have an intimate knowledge of the schema of their database so they know what the min length, or the maximum value, or any other validator, should be set to.  The result of this is a lot of seemingly "magic values" scattered throughout the html inputs you create.  Plus, I find writing inputs, and setting the name, type, id, and setting up the label **incredibly** repetitive and, to be honest, a bit un-laravel-y. 

## The Solution
So, that is why I created Laravel ModelInput.  Laravel ModelInput is a blade directive that takes an attribute from a model, and automatically generates an input for it.  This generated input is fully customizable, includes a label, and has sensible default validator rules set on it _based on the schema of the database table the model represents_.  Let's take a look at how it works.

## An Example
Say I have a a model, called Product.  It has three attributes: _name_, _price_, and _publish_at_.  These are pretty simple: the name of the product, the price of the product, and when the product should be publish to our site for sale.  They are created in a migration like this:

```php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('price')->default(0);
            $table->timestamp('publish_at')->nullable();
            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
```

Normally, when creating these inputs so that we can create a form to fit these columns, we would need to type something like this:
(this example uses Tailwind classes)

```php
<div class="p-4">
    <label for="name_input_1" class="block">Name:</label>
    <input type="text" name="name" id="name_input_1" class="text-xl border block w-1/5 p-1" required="1" minlength="1" maxlength="255" placeholder="Enter a Name">
</div>

<div class="p-4">
    <label for="price_input_1" class="block">Price:</label>
    <input type="number" name="price" id="price_input_1" class="text-xl border block w-1/5 p-1" required="1" min="0" max="9999999999" step="1" placeholder="Enter a Price">
</div>

<div class="p-4">
    <label for="publish_at_input_1" class="block">Publish At:</label>
    <input type="datetime-local" name="publish_at" id="publish_at_input_1" class="text-xl border block w-1/5 p-1" value="2024-07-09T13:18">
</div>
```

This is a _lot_ of repeat, boilerplate code full of magic values.  ModelInput adds a blade directive that automates all this boilerplate.  With modelInput, what appears above could be created like this:

```php
@modelInput(Product, name, [
    'container_classes' => 'p-4', 
    'label_classes' => 'block', 
    'input_classes' => 'text-xl border block w-1/5 p-1',
    'label_text' => 'Name:',
    'id_suffix' => 1,
    'attributes' => [
        'placeholder' => 'Enter a Name'
    ],
])

@modelInput(Product, price, [
    'container_classes' => 'p-4', 
    'label_classes' => 'block', 
    'input_classes' => 'text-xl border block w-1/5 p-1',
    'label_text' => 'Price:',
    'id_suffix' => 1,
    'attributes' => [
        'placeholder' => 'Enter a Price'
    ],
])


@modelInput(Product, publish_at, [
    'container_classes' => 'p-4', 
    'label_classes' => 'block', 
    'input_classes' => 'text-xl border block w-1/5 p-1',
    'label_text' => 'Publish At:',
    'id_suffix' => 1,
    'attributes' => [],
])
```

These inputs come out in my app looking like this:

![image](https://github.com/jtgraham38/ModelInput/assets/88167136/401660a9-874f-4264-93a0-88f49513401f)

Here are some the generated validators in action:

![image](https://github.com/jtgraham38/ModelInput/assets/88167136/24ddebe4-dbe3-422c-9f43-6c15a936ce29)
![image](https://github.com/jtgraham38/ModelInput/assets/88167136/219255d2-45b8-4c59-aee0-57d301a2c5a4)


As you can see, this vastly simpliefies the code.  Simply pass in the model name, an attribute name, and an array of arguments, and ModelInput will automatically generate inputs just like the ones you see above, with name, type, and id set up automatically, labels linked to inputs automatically, and validators set based on the schema of the underlying database table.  And, the real kicker is that the input is still directly customizable by you, the programmer.  If you want to override the step value on the price input, simply pass the _step_ key to the _attributes_ array with your desired value:

```php
@modelInput(Product, price, [
    'container_classes' => 'p-4', 
    'label_classes' => 'block', 
    'input_classes' => 'text-xl border block w-1/5 p-1',
    'label_text' => 'Price:',
    'id_suffix' => 1,
    'attributes' => [
        'placeholder' => 'Enter a Price',
        'step' => '10'
    ],
])
```

That input will come oput like this now:

```
<div class="p-4">
    <label for="price_input_1" class="block">Price:</label>
    <input type="number" name="price" id="price_input_1" class="text-xl border block w-1/5 p-1" required="1" min="0" max="9999999999" step="10" placeholder="Enter a Price">
</div>
```

## Installation
To use this library in your Laravel project, begin by adding this snippet to your composer.json:

```php
"repositories": [
    {
        "type": "composer",
        "url": "https://php.jacob-t-graham.com"
    }
]
```

Then, simply run `composer require jtgraham38/modelinput`.  The service provider should be automatically discovered by Laravel.

## Notes
- Currently, this library is only compatible with models using the `<APP_NAMESPACE>\Models` namespace.  This may change in the future.

## About Me
My name is Jacob Graham, and I developed ModelInput because, well, it is useful to me!  If it is useful to you, or you'd like to suggest an imporvement, I'd love to hear from you.  Contact me using my website: https://jacob-t-graham.com/contact/
