# valid-array

Data filtering mechanism based on native `php-filter` extension with additional capacities.

A `ValidArray` object receives on instantiation an associative array with elements names as keys and elements filters as
values. These filters guarantee that when corresponding elements in this object are set, they will automatically get
validated and their valid value will be stored. If an element does not pass validation, a default value may be used
basing on filter parameters. Object may be used as a normal array to set elements and get elements values, validation is
done on element modification.

`ValidArray` extends SPL `ArrayObject` and wraps the functionality of `php-filter` extension.

## Introduction

PHP native data validation mechanism using standard `php-filter` extension is a great way of working with data, to
ensure user input correctness and to protect against several attack vectors. This extension is bundled with PHP and is
available by default in most configurations, which makes it a natural choice for any project requiring data validation.

As a bundled extension, `php-filter` benefits from documentation coverage in [PHP manual](https://php.net/filter),
performance gain of compiled-in module and quasi-absolute availability.

While being a de-facto standard for data validation in PHP, it still suffers from several questionable architectural
decisions that were made at the early days and that may slow down the learning curve and development pace when
implementing validation strategy in user land.

Some of these dubious decisions:

- 🤨 `default` value is implemented for limited number of filters, only to replace improperly formatted arguments that
  otherwise match defined filter flags, but not for cases when argument is not supplied or when flags mismatch is
  detected,
- 🤔 limited `FILTER_CALLBACK` functionality, when flags are ignored and validation callback cannot distinguish scalar
  data from array,
- 🧐 objects may be filtered, but only if they have a magic method `__toString()` defined (implement `Stringable` since
  v8.0), and only this value is kept as element value after validation.

Based on numerous strengths of `php-filter` extension, `ValidArray` class corrects (where possible) beforementioned
flaws and implements `php-filter` functionality into a useful and predictable data structure with the following
features:

- associative arrays with validation mechanism,
- validation is provided on array instantiation and item modification,
- filters are set at data object instantiation,
- useful for input / output parameters validation,
- minimal footprint,
- extending `ArrayObject` allows for natural array handling in user code,
- use of standard `php-filter` extension with enhanced handling of default values, callbacks and objects filtering.

## Example

Enough talks, let's see how it works.

We shall handle a login POST request with `email`, `password` and `uri` parameters that need validation.

The route `POST /api/login` will call `LoginController` and pass request parameters in an array which should have 3
fields: `email`, `password` and `uri`. All fields should have valid data, which is defined as follows:

- `email` is a string with valid email address or `false` if not valid,
- `password` is a string with hashed version of sent password (calculated during validation) or `false` if not valid;
  valid password is a string of at least 8 characters,
- `uri` is a string with a path for next page to redirect after login or `#member` by default.

We shall identify request parameters inside the corresponding controller, like in the following simplified code, where
our goal will be to implement `withRequestVars()` method:

```php
<?php   // router.php

// create controller
$controller = new ApiLoginController();

// validate request vars
$controller->withRequestVars();

// run controller with valid parameters
$controller->run();
```
```php
<?php   // ApiLoginController.php

class ApiLoginController implements Runnable
{
    protected array $vars = [];

    public function withRequestVars(): self
    {
        // TODO validate request variables
        $this->vars = [];   // <- we need to implement this

        return $this;
    }

    public function run()
    {
        // TODO redirect to $uri or output error...
    }
}
```

#### Implementing required validation with `php-filter` (1st attempt):

```php
<?php

// validate request variables
$this->vars = filter_input_array(
    INPUT_POST,
    [
        'email' => FILTER_VALIDATE_EMAIL,
        'password' => [
            'filter' => FILTER_CALLBACK,
            'options' => function ($pwd) {
                return (strlen($pwd) >= 8) ? password_hash($pwd, PASSWORD_BCRYPT) : false;
            },
        ],
        'uri' => [
            'filter' => FILTER_DEFAULT,
            'options' => ['default' => '#member'],  // <- will not work
        ],
    ]
) ?: [];
```

> If you need help with using `php-filter` extension have a look at [official documentation](http://php.net/filter).

Now, this will partly work. Until someone discovers out login api and decides to bring our system down with several
absolutely legitimate requests of the following form (newlines added for readability):

```
email=abc@def.com
&uri=%23member
&password[]=12345678
&password[]=12345678
&password[]=12345678
... repeat 100 times
&password[]=12345678
```

When `php-filter` extension sees an array sent, its default behavior is to validate every item in this array via the
provided filter and keep this array structure in place replacing array values with filtered values. In most cases this
may be a correct behavior (anyway that's how filter extension works), but in our case this will start calculating
password hashes for all the provided passwords, and this is a very CPU intensive task. A couple of dozens of such
requests sent in parallel is enough to put our infra in trouble.

`php-filter` has a very elegant solution for this situation, which is called filter flags, and our first intention will
be to set the `FILTER_REQUIRE_SCALAR`.

#### Set `FILTER_REQUIRE_SCALAR` flag (2nd attempt):

```php
<?php

// validate request variables
$this->vars = filter_input_array(
    INPUT_POST,
    [
        'email' => [
            'filter' => FILTER_VALIDATE_EMAIL,
            'flags' => FILTER_REQUIRE_SCALAR,
        ],
        'password' => [
            'filter' => FILTER_CALLBACK,
            'flags' => FILTER_REQUIRE_SCALAR,       // <- will not work
            'options' => function ($pwd) {
                return (strlen($pwd) >= 8) ? password_hash($pwd, PASSWORD_BCRYPT) : false;
            },
        ],
        'uri' => [
            'filter' => FILTER_DEFAULT,
            'flags' => FILTER_REQUIRE_SCALAR,
            'options' => ['default' => '#member'],  // <- will not work
        ],
    ]
) ?: [];
```

Nothing wrong with this approach, and scalar flag will work fine for `email` and `uri` fields, but not for
the `password` field. Yes, `FILTER_CALLBACK` simply
[ignores flags by design](https://www.php.net/manual/en/filter.filters.misc.php). You cannot do anything with it, so
before the validation step you'll likely want to add a verification to catch the possibility of array passed
as `password` argument.

Other thing, and also up to you to handle correctly, is the fact that default value provided via the `default` flag, is
only used when [the parameter is provided but is not valid](https://www.php.net/manual/en/filter.filters.validate.php).
It is only available for `FILTER_VALIDATE_*` filters (not for `FILTER_DEFAULT` by the way), it will not be used if any
sanitize-filter shrinks parameter value down to an empty string, if parameter value mismatches defined flags, and
finally, it will not be used if parameter was not set in request.

So our final version should handle these additional conditions.

#### Handle array type and default value manually (3rd attempt):

```php
<?php

// validate request variables
$post = $_POST;
if (is_array($post['password'] ?? null)) {
    $post['password'] = false;
}
if (empty($post['uri'])) {
    $post['uri'] = '#member';
}
$vars = filter_var_array(
    $post,
    [
        'email' => [
            'filter' => FILTER_VALIDATE_EMAIL,
            'flags' => FILTER_REQUIRE_SCALAR,
        ],
        'password' => [
            'filter' => FILTER_CALLBACK,
            'options' => function ($pwd) {
                return (strlen($pwd) >= 8) ? password_hash($pwd, PASSWORD_BCRYPT) : false;
            },
        ],
        'uri' => [
            'filter' => FILTER_DEFAULT,
            'flags' => FILTER_REQUIRE_SCALAR,
        ],
    ]
) ?: [];
```

This is definitely not the most beautiful piece of code and that is where `ValidArray` is improving the situation.

Consider the same example, but implemented using `ValidArray` functionality:

#### Implementing required validation with `ValidArray`:

```php
<?php

use Vertilia\ValidArray;

// validate request variables
$this->vars = new ValidArray(
    [
        'email' => [
            'filter' => FILTER_VALIDATE_EMAIL,
            'flags' => FILTER_REQUIRE_SCALAR,
        ],
        'password' => [
            'filter' => ValidArray::FILTER_EXTENDED_CALLBACK,
            'flags' => FILTER_REQUIRE_SCALAR,
            'options' => [
                'callback' => function ($pwd) {
                    return (strlen($pwd) >= 8) ? password_hash($pwd, PASSWORD_BCRYPT) : false;
                }
            ],
        ],
        'uri' => [
            'filter' => FILTER_SANITIZE_STRING,
            'flags' => FILTER_REQUIRE_SCALAR,
            'options' => ['default' => '#member'],
        ],
    ],
    $_POST
);
```

## `ValidArray` specificity

Notable distinctions between `ValidArray` and `filter_*()` functions:

- `ValidArray` is an object but allows array-style access to elements since extending `ArrayObject` SPL class;
- it sets `default` values or `null` for missing elements for all filters;
- it provides `ValidArray::FILTER_EXTENDED_CALLBACK` filter allowing to define callbacks that unlock both `flags` and
  `default` functionality;
- it provides `ValidArray::FILTER_INSTANCE_OF` filter allowing to validate values as objects of specific type and use
  both `flags` and `default` functionality;
- `ValidArray` always uses `$add_empty` mode of `filter_var_array()` function and does not allow to unset elements
  that have filters defined;
- `count()` on `ValidArray` object will always return the number of defined filters;
- setting value for undefined key will be ignored;
- setting value for existing key will trigger a `filter_var()` call, which may modify the value in the following ways:
  - change value (ex: `FILTER_SANITIZE_NUMBER_INT`),
  - change the type of the value (ex: `FILTER_VALIDATE_INT`),
  - convert scalar value to array (with `FILTER_FORCE_ARRAY` flag),
  - set value to its `default` value, `false` or `null` in case of failure (attention, not all standard filters use
    `default` value even when one is provided),
  - if value is an array (or becomes an array via `FILTER_FORCE_ARRAY` flag), these modifications will be applied to all
    array elements, recursively;
- unsetting value will set it to either `default` value (if defined) or `null` for all filters.

## More use cases

In a specific controller we handle the following request parameters:

```json
{
    "id": {"type": "integer"},
    "name": {"type": "string"},
    "email": {"type": "string"},
    "tel": {"type": "string"}
}
```

So we define the following filters:

```php
$filters = [
    'id' => [
        'filter' => FILTER_VALIDATE_INT,
        'flags' => FILTER_REQUIRE_SCALAR,
    ],
    'name' => [
        'filter' => FILTER_SANITIZE_STRING,
        'flags' => FILTER_REQUIRE_SCALAR,
    ],
    'email' => [
        'filter' => FILTER_VALIDATE_EMAIL,
        'flags' => FILTER_REQUIRE_SCALAR,
    ],
    'tel' => [
        'filter' => FILTER_VALIDATE_REGEXP,
        'flags' => FILTER_REQUIRE_SCALAR,
        'options' => [
            'regexp' => '/^\+?\d+(?:[. ()-]{1,2}\d+)*$/',
            'default' => '+00 (0)0 00 00 00 00',
        ],
    ],
];
```

We then create a `ValidArray` instance passing there predefined `$filters` and the value of the `$_REQUEST` (which
normally accumulates values of GET, POST and COOKIE parameters):

```php
$va = new ValidArray($filters, $_REQUEST);
```

Now we can be sure that the count of `$va` array is exactly 4 (`id`, `name`, `email`, `tel` keys), that each of them is
of corresponding type or `null` if not provided in request or `false` if incoming value does not correspond to
definition in filter. The default value for `tel` element will be used if not provided or invalid.

Since `ValidArray` extends `ArrayObject`, its elements may be accessed (added, iterated etc.) via normal array notation:

```php
$va['name'] = 'John Snow';
echo "{$va['name']}\n";
// prints: John Snow

foreach ($va as $name => $value) {
    printf("'%s' => %s,\n", $name, var_export($value, true));
}
// prints (if $_REQUEST is empty):
// 'id' => null,
// 'name' => 'John Snow',
// 'email' => null,
// 'tel' => '+00 (0)0 00 00 00 00',
```

When setting `ValidArray` values, they are automatically validated using the predefined filters. Here again, if
provided value does not pass validation and no default value is defined, `false` is set instead (or `null` if
`FILTER_NULL_ON_FAILURE` flag is set).

```php
$va['email'] = 'unknown';
echo "{$va['email']}\n";
// prints empty line since $va['email'] is false
```

## More examples

For the following request parameters:

```json
{
    "id": 175,
    "name": "John Snow",
    "email": "john.snow@winterfell.com",
    "tel": "322-223"
}
```

`$va` contents will be:

```php
[
    'id' => 175,
    'name' => 'John Snow',
    'email' => 'john.snow@winterfell.com',
    'tel' => '322-223',
]
```

For incorrect request parameters:

```json
{
    "id": [1, "' OR 1 -- "],
    "name": "X",
    "another": true,
    "admin": 1
}
```

`$va` contents will be:

```php
[
    'id' => false,
    'name' => 'X',
    'email' => null,
    'tel' => "+00 (0)0 00 00 00 00",
]
```

Here, correct parameters will be present, all unknown parameters will be ignored, missing parameters will be set to
`null` or provided `default` value, and incorrect parameters will be set to `false`.

## Additional filters

These additional filters are provided to enhance standard `php-filter` capacities:

- `ValidArray::FILTER_EXTENDED_CALLBACK`
- `ValidArray::FILTER_INSTANCE_OF`

| ID                         | Value | Options                 | Flags                                                                                           | Description                                                                                                                                                                                                                                                      |
|----------------------------|-------|-------------------------|-------------------------------------------------------------------------------------------------|------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `FILTER_EXTENDED_CALLBACK` | -1    | `callback`, `default`   | `FILTER_NULL_ON_FAILURE`, `FILTER_REQUIRE_SCALAR`, `FILTER_REQUIRE_ARRAY`, `FILTER_FORCE_ARRAY` | Executes user-defined `callback` passing initial value as argument. Returned value is used as valid. If initial value mismatches flags, sets value to `default` (if provided) or `null` (if `FILTER_NULL_ON_FAILURE`) or `false` without executing the callback. |
| `FILTER_INSTANCE_OF`       | -2    | `class_name`, `default` | `FILTER_NULL_ON_FAILURE`, `FILTER_REQUIRE_SCALAR`, `FILTER_REQUIRE_ARRAY`, `FILTER_FORCE_ARRAY` | Validates initial value is an instance of `class_name` class. If initial value is not of `class_name` family or not an object or mismatches flags, sets value to `default` (if provided) or `null` (if `FILTER_NULL_ON_FAILURE`) or `false`.                     |

> **IMPORTANT**
> 
> Filters in `ValidArray` object are read-only and cannot change during object lifespan. This is by design. If you need
> object with updatable filters, use provided `MutableValidArray` object instead.
