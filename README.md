# valid-array

Associative arrays with filtering mechanism.

Filters are set at array instantiation.

Validation is provided on array instantiation or item insertion.

Useful for input / output parameters validation.

Minimal footprint, use of standard `filter` extension.

## Use case

In a specific controller we handle the following request parameters:

```json
{
    "id": "integer",
    "name": "string",
    "email": "string",
    "tel": "string"
}
```

So we define the following filters:

```php
$filters = [
    'id' => FILTER_VALIDATE_INT,
    'name' => FILTER_SANITIZE_STRING,
    'email' => FILTER_VALIDATE_EMAIL,
    'tel' => [
        'filter' => FILTER_VALIDATE_REGEXP,
        'options' => [
            'default' => '+00 (0)0 00 00 00 00',
            'regexp' => '/^\+?\d+(?:[. ()-]{1,2}\d+)*$/',
        ],
    ],
];
```

- If you need help with using the `filter` php extension have a look at [official documentation](http://php.net/filter).

We then create a `ValidArray` instance passing there the predefined `$filters` and the value of the `$_REQUEST`:

```php
$va = new ValidArray($filters, $_REQUEST);
```

Now we can be sure that array items in `$va` are exactly 4 (`id`, `name`, `email`, `tel`), that each of them is of
corresponding type or `null` if not provided in request or `false` if incoming value does not correspond to definition
in filter.

Since `ValidArray` extends `ArrayObject`, its values may be accessed via normal array notation:

```php
$va['name'] = 'John Snow';
echo $va['name'];
```

- prints: `John Snow`

When setting `ValidArray` values, they are automatically validated using the predefined filters. Here again, if
provided value does not pass validation, `false` is set instead.

```php
$va['email'] = 'unknown';
echo $va['email'];
```

- prints nothing since `$va['email']` is `false`

## Usage example

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
    'tel' => null,
]
```

Here, correct parameters will be present, all unknown parameters will be removed, missing parameters will be set to
`null`, and incorrect parameters set to `false`.
