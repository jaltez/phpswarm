# PHPSwarm Validation System

The PHPSwarm validation system provides comprehensive data validation capabilities with support for both schema-based validation and attribute-based validation using PHP 8 attributes.

## Features

- **Schema Validation**: Define validation rules using arrays similar to JSON Schema
- **Attribute-Based Validation**: Use PHP 8 attributes to declaratively define validation rules on class properties
- **JSON Schema Support**: Comprehensive JSON Schema-like validation with nested object and array support
- **Custom Rules**: Define and register custom validation rules
- **Format Validation**: Built-in support for common formats (email, URL, date, UUID, etc.)
- **Exception Handling**: Detailed validation exceptions with error information
- **Factory Integration**: Easy creation through the PHPSwarm factory

## Basic Usage

### Schema Validation

```php
use PhpSwarm\Factory\PhpSwarmFactory;

$factory = new PhpSwarmFactory();
$validator = $factory->createValidator();

// Define a validation schema
$schema = [
    'name' => [
        'type' => 'string',
        'required' => true,
        'min_length' => 2,
        'max_length' => 50,
    ],
    'email' => [
        'type' => 'string',
        'required' => true,
        'pattern' => '/^[^\s@]+@[^\s@]+\.[^\s@]+$/',
    ],
    'age' => [
        'type' => 'int',
        'required' => true,
        'min' => 18,
        'max' => 120,
    ],
];

// Validate data
$data = [
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'age' => 30,
];

$result = $validator->validate($data, $schema);
if ($result->isValid()) {
    $validatedData = $result->getValidatedData();
    // Use validated data
} else {
    foreach ($result->getErrors() as $field => $errors) {
        echo "$field: " . implode(', ', $errors) . "\n";
    }
}
```

### Attribute-Based Validation

```php
use PhpSwarm\Utility\Validation\Attribute\Required;
use PhpSwarm\Utility\Validation\Attribute\Type;
use PhpSwarm\Utility\Validation\Attribute\Length;
use PhpSwarm\Utility\Validation\Attribute\Range;
use PhpSwarm\Utility\Validation\Attribute\Pattern;
use PhpSwarm\Utility\Validation\Attribute\InArray;

class User
{
    #[Required]
    #[Type('string')]
    #[Length(min: 2, max: 50)]
    public string $name;

    #[Required]
    #[Type('string')]
    #[Pattern('/^[^\s@]+@[^\s@]+\.[^\s@]+$/')]
    public string $email;

    #[Required]
    #[Type('int')]
    #[Range(min: 18, max: 120)]
    public int $age;

    #[Type('string')]
    #[InArray(['user', 'admin', 'moderator'])]
    public string $role = 'user';
}

// Validate an object
$user = new User();
$user->name = 'John Doe';
$user->email = 'john@example.com';
$user->age = 30;

$result = $validator->validateObject($user);
if ($result->isValid()) {
    // Object is valid
    $validatedData = $result->getValidatedData();
}
```

## Available Validation Rules

### Schema Rules

| Rule         | Type   | Description                   | Example                            |
| ------------ | ------ | ----------------------------- | ---------------------------------- |
| `type`       | string | Data type validation          | `'type' => 'string'`               |
| `required`   | bool   | Whether field is required     | `'required' => true`               |
| `min_length` | int    | Minimum string/array length   | `'min_length' => 3`                |
| `max_length` | int    | Maximum string/array length   | `'max_length' => 100`              |
| `length`     | int    | Exact string/array length     | `'length' => 10`                   |
| `min`        | number | Minimum numeric value         | `'min' => 0`                       |
| `max`        | number | Maximum numeric value         | `'max' => 100`                     |
| `pattern`    | string | Regex pattern                 | `'pattern' => '/^[A-Z]+$/'`        |
| `enum`       | array  | List of allowed values        | `'enum' => ['draft', 'published']` |
| `default`    | mixed  | Default value if not provided | `'default' => 'user'`              |
| `rules`      | array  | Custom rule names             | `'rules' => ['unique_email']`      |

### Supported Types

- `string` - String values
- `int`, `integer` - Integer values
- `float`, `double`, `number` - Numeric values (int or float)
- `bool`, `boolean` - Boolean values
- `array` - Array values
- `object` - Object values
- `null` - Null values
- `mixed` - Any type

## Validation Attributes

### Available Attributes

#### `#[Required]`

Marks a property as required.

```php
#[Required(message: 'Custom error message')]
public string $name;
```

#### `#[Type]`

Validates the data type.

```php
#[Type('string')]
#[Type('int')]
#[Type('array')]
public mixed $value;
```

#### `#[Length]`

Validates string or array length.

```php
#[Length(min: 3, max: 50)]           // Between 3 and 50
#[Length(exact: 10)]                 // Exactly 10
#[Length(min: 1)]                    // At least 1
public string $text;
```

#### `#[Range]`

Validates numeric ranges.

```php
#[Range(min: 0, max: 100)]           // Between 0 and 100
#[Range(min: 18)]                    // At least 18
public int $age;
```

#### `#[Pattern]`

Validates against a regex pattern.

```php
#[Pattern('/^[A-Z0-9-]+$/', message: 'Invalid format')]
public string $sku;
```

#### `#[InArray]`

Validates against a list of allowed values.

```php
#[InArray(['draft', 'published', 'archived'])]
#[InArray([1, 2, 3], strict: false)]  // Non-strict comparison
public string $status;
```

## JSON Schema Validation

For complex nested data structures, use the SchemaValidator:

```php
$schemaValidator = $factory->createSchemaValidator();

$schema = [
    'type' => 'object',
    'properties' => [
        'user' => [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string', 'minLength' => 1],
                'email' => ['type' => 'string', 'format' => 'email'],
                'profile' => [
                    'type' => 'object',
                    'properties' => [
                        'bio' => ['type' => 'string'],
                        'age' => ['type' => 'integer', 'minimum' => 0],
                    ],
                    'required' => ['bio'],
                ],
            ],
            'required' => ['name', 'email'],
        ],
        'tags' => [
            'type' => 'array',
            'items' => ['type' => 'string'],
            'uniqueItems' => true,
        ],
    ],
    'required' => ['user'],
];

$result = $schemaValidator->validateSchema($data, $schema);
```

### JSON Schema Features

- **Nested Objects**: Validate complex object structures
- **Arrays**: Validate array items with type checking and uniqueness
- **Format Validation**: email, url, ipv4, ipv6, date, time, date-time, uuid, hostname
- **Numeric Constraints**: minimum, maximum, exclusiveMinimum, exclusiveMaximum, multipleOf
- **String Constraints**: minLength, maxLength, pattern
- **Array Constraints**: minItems, maxItems, uniqueItems
- **Required Properties**: Specify required object properties
- **Additional Properties**: Control whether additional properties are allowed

## Custom Validation Rules

Register custom validation rules for reusable logic:

```php
$validator->addRule('strong_password', function ($value, $field) {
    if (!is_string($value)) {
        return "The $field must be a string";
    }

    if (strlen($value) < 8) {
        return "The $field must be at least 8 characters long";
    }

    if (!preg_match('/[A-Z]/', $value)) {
        return "The $field must contain at least one uppercase letter";
    }

    if (!preg_match('/[a-z]/', $value)) {
        return "The $field must contain at least one lowercase letter";
    }

    if (!preg_match('/[0-9]/', $value)) {
        return "The $field must contain at least one number";
    }

    return null; // Valid
});

// Use in schema
$schema = [
    'password' => [
        'type' => 'string',
        'required' => true,
        'rules' => ['strong_password'],
    ],
];
```

## ValidationResult

The validation result provides detailed information about the validation process:

```php
$result = $validator->validate($data, $schema);

// Check validation status
if ($result->isValid()) {
    // Get validated (and potentially transformed) data
    $validatedData = $result->getValidatedData();

    // Get specific field value
    $email = $result->getValidatedField('email');
}

if ($result->isInvalid()) {
    // Get all errors
    $errors = $result->getErrors();

    // Get errors for specific field
    $nameErrors = $result->getFieldErrors('name');

    // Get all error messages as flat array
    $messages = $result->getAllErrorMessages();

    // Get first error message
    $firstError = $result->getFirstError();
}
```

## Exception Handling

Use `validateOrThrow()` for exception-based error handling:

```php
use PhpSwarm\Exception\Utility\ValidationException;

try {
    $validator->validateOrThrow($data, $schema);
    // Data is valid, continue processing
} catch (ValidationException $e) {
    echo "Validation failed: " . $e->getMessage() . "\n";

    // Get validation result
    $result = $e->getValidationResult();

    // Get all error messages
    $errors = $e->getErrorMessages();

    // Get structured errors
    $validationErrors = $e->getValidationErrors();
}
```

## Integration with Tools

The validation system integrates seamlessly with PHPSwarm tools. The existing `BaseTool::validateParameters()` method can be enhanced with the new validation system:

```php
use PhpSwarm\Tool\BaseTool;
use PhpSwarm\Contract\Utility\ValidatorInterface;

class CustomTool extends BaseTool
{
    private ValidatorInterface $validator;

    public function __construct()
    {
        parent::__construct('custom_tool', 'A custom tool with advanced validation');

        $factory = new PhpSwarmFactory();
        $this->validator = $factory->createValidator();

        $this->parametersSchema = [
            'name' => [
                'type' => 'string',
                'required' => true,
                'min_length' => 2,
                'max_length' => 100,
            ],
            'options' => [
                'type' => 'array',
                'required' => false,
                'default' => [],
            ],
        ];
    }

    public function run(array $parameters = []): mixed
    {
        // Use the advanced validator
        $this->validator->validateOrThrow($parameters, $this->parametersSchema);

        // Continue with tool execution
        return $this->executeLogic($parameters);
    }
}
```

## Configuration

The validation system can be configured through the factory:

```php
$validator = $factory->createValidator([
    'custom_rules' => [
        'unique_email' => function ($value, $field) {
            // Custom validation logic
            return null; // or error message
        },
        'strong_password' => $passwordValidator,
    ],
]);
```

## Best Practices

1. **Use Schema Validation for API Input**: Perfect for validating request data
2. **Use Attribute Validation for Domain Objects**: Great for model validation
3. **Combine Both Approaches**: Use schemas for external input, attributes for internal objects
4. **Define Custom Rules**: Create reusable validation logic for business rules
5. **Handle Exceptions Appropriately**: Use `validateOrThrow()` when you want to halt execution on validation failure
6. **Leverage Type Transformation**: The validator can automatically cast values to the correct types
7. **Use Meaningful Error Messages**: Provide custom error messages for better user experience

The validation system provides a robust foundation for ensuring data integrity throughout your PHPSwarm applications.
