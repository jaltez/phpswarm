<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use PhpSwarm\Factory\PhpSwarmFactory;
use PhpSwarm\Utility\Validation\Attribute\Required;
use PhpSwarm\Utility\Validation\Attribute\Type;
use PhpSwarm\Utility\Validation\Attribute\Length;
use PhpSwarm\Utility\Validation\Attribute\Range;
use PhpSwarm\Utility\Validation\Attribute\Pattern;
use PhpSwarm\Utility\Validation\Attribute\InArray;
use PhpSwarm\Exception\Utility\ValidationException;

echo "PHPSwarm Validation System Example\n";
echo "=================================\n\n";

// Create factory
$factory = new PhpSwarmFactory();

// 1. Schema Validation Example
echo "1. Schema Validation Example\n";
echo "----------------------------\n";

$validator = $factory->createValidator();

// Define a schema for user registration
$userSchema = [
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
    'role' => [
        'type' => 'string',
        'required' => false,
        'enum' => ['user', 'admin', 'moderator'],
        'default' => 'user',
    ],
    'preferences' => [
        'type' => 'array',
        'required' => false,
        'default' => [],
    ],
];

// Valid data
$validData = [
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'age' => 30,
    'role' => 'admin',
    'preferences' => ['notifications' => true],
];

echo "Validating valid data:\n";
$result = $validator->validate($validData, $userSchema);
if ($result->isValid()) {
    echo "✓ Validation passed!\n";
    echo "Validated data: " . json_encode($result->getValidatedData(), JSON_PRETTY_PRINT) . "\n";
} else {
    echo "✗ Validation failed!\n";
    foreach ($result->getErrors() as $field => $errors) {
        echo "  $field: " . implode(', ', $errors) . "\n";
    }
}

echo "\n";

// Invalid data
$invalidData = [
    'name' => 'A', // Too short
    'email' => 'invalid-email', // Invalid format
    'age' => 15, // Too young
    'role' => 'superuser', // Not in enum
];

echo "Validating invalid data:\n";
$result = $validator->validate($invalidData, $userSchema);
if ($result->isValid()) {
    echo "✓ Validation passed!\n";
} else {
    echo "✗ Validation failed!\n";
    foreach ($result->getErrors() as $field => $errors) {
        echo "  $field: " . implode(', ', $errors) . "\n";
    }
}

echo "\n\n";

// 2. Attribute-Based Validation Example
echo "2. Attribute-Based Validation Example\n";
echo "------------------------------------\n";

// Define a class with validation attributes
class Product
{
    #[Required(message: 'Product name is required')]
    #[Type('string')]
    #[Length(min: 2, max: 100)]
    public string $name;

    #[Required]
    #[Type('string')]
    #[Pattern('/^[A-Z0-9-]+$/', message: 'SKU must contain only uppercase letters, numbers, and hyphens')]
    public string $sku;

    #[Required]
    #[Type('float')]
    #[Range(min: 0.01, max: 99999.99)]
    public float $price;

    #[Type('string')]
    #[InArray(['draft', 'active', 'discontinued'])]
    public string $status = 'draft';

    #[Type('array')]
    public array $tags = [];

    public function __construct()
    {
        // Properties will be validated using attributes
    }
}

// Create a product instance
$product = new Product();
$product->name = 'Awesome Widget';
$product->sku = 'AWE-001';
$product->price = 29.99;
$product->status = 'active';
$product->tags = ['widget', 'awesome'];

echo "Validating valid product object:\n";
$result = $validator->validateObject($product);
if ($result->isValid()) {
    echo "✓ Product validation passed!\n";
    echo "Validated data: " . json_encode($result->getValidatedData(), JSON_PRETTY_PRINT) . "\n";
} else {
    echo "✗ Product validation failed!\n";
    foreach ($result->getErrors() as $field => $errors) {
        echo "  $field: " . implode(', ', $errors) . "\n";
    }
}

echo "\n";

// Create an invalid product
$invalidProduct = new Product();
$invalidProduct->name = 'A'; // Too short
$invalidProduct->sku = 'invalid-sku!'; // Invalid characters
$invalidProduct->price = -5.00; // Negative price
$invalidProduct->status = 'unknown'; // Not in allowed values

echo "Validating invalid product object:\n";
$result = $validator->validateObject($invalidProduct);
if ($result->isValid()) {
    echo "✓ Product validation passed!\n";
} else {
    echo "✗ Product validation failed!\n";
    foreach ($result->getErrors() as $field => $errors) {
        echo "  $field: " . implode(', ', $errors) . "\n";
    }
}

echo "\n\n";

// 3. JSON Schema-like Validation Example
echo "3. JSON Schema-like Validation Example\n";
echo "-------------------------------------\n";

$schemaValidator = $factory->createSchemaValidator();

// Define a complex JSON schema
$apiSchema = [
    'type' => 'object',
    'properties' => [
        'user' => [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'username' => [
                    'type' => 'string',
                    'minLength' => 3,
                    'maxLength' => 20,
                    'pattern' => '/^[a-zA-Z0-9_]+$/',
                ],
                'profile' => [
                    'type' => 'object',
                    'properties' => [
                        'firstName' => ['type' => 'string'],
                        'lastName' => ['type' => 'string'],
                        'email' => [
                            'type' => 'string',
                            'format' => 'email',
                        ],
                    ],
                    'required' => ['firstName', 'email'],
                ],
            ],
            'required' => ['id', 'username', 'profile'],
        ],
        'posts' => [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string', 'minLength' => 1],
                    'content' => ['type' => 'string'],
                    'tags' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                        'uniqueItems' => true,
                    ],
                ],
                'required' => ['title', 'content'],
            ],
        ],
    ],
    'required' => ['user'],
];

// Valid complex data
$complexData = [
    'user' => [
        'id' => 123,
        'username' => 'john_doe',
        'profile' => [
            'firstName' => 'John',
            'lastName' => 'Doe',
            'email' => 'john@example.com',
        ],
    ],
    'posts' => [
        [
            'title' => 'My First Post',
            'content' => 'This is the content of my first post.',
            'tags' => ['introduction', 'first-post'],
        ],
        [
            'title' => 'Another Post',
            'content' => 'More interesting content here.',
            'tags' => ['update', 'news'],
        ],
    ],
];

echo "Validating complex valid data:\n";
$result = $schemaValidator->validateSchema($complexData, $apiSchema);
if ($result->isValid()) {
    echo "✓ Complex validation passed!\n";
} else {
    echo "✗ Complex validation failed!\n";
    foreach ($result->getErrors() as $field => $errors) {
        echo "  $field: " . implode(', ', $errors) . "\n";
    }
}

echo "\n\n";

// 4. Custom Validation Rules Example
echo "4. Custom Validation Rules Example\n";
echo "---------------------------------\n";

// Add custom validation rules
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

    if (!preg_match('/[^A-Za-z0-9]/', $value)) {
        return "The $field must contain at least one special character";
    }

    return null; // Valid
});

$validator->addRule('unique_username', function ($value, $field) {
    // Simulate checking against a database
    $existingUsernames = ['admin', 'test', 'user123'];

    if (in_array($value, $existingUsernames, true)) {
        return "The $field is already taken";
    }

    return null; // Valid
});

// Schema with custom rules
$registrationSchema = [
    'username' => [
        'type' => 'string',
        'required' => true,
        'min_length' => 3,
        'max_length' => 20,
        'rules' => ['unique_username'],
    ],
    'password' => [
        'type' => 'string',
        'required' => true,
        'rules' => ['strong_password'],
    ],
];

// Test with valid data
$validRegistration = [
    'username' => 'newuser',
    'password' => 'SecurePass123!',
];

echo "Validating registration with strong password:\n";
$result = $validator->validate($validRegistration, $registrationSchema);
if ($result->isValid()) {
    echo "✓ Registration validation passed!\n";
} else {
    echo "✗ Registration validation failed!\n";
    foreach ($result->getErrors() as $field => $errors) {
        echo "  $field: " . implode(', ', $errors) . "\n";
    }
}

echo "\n";

// Test with invalid data
$invalidRegistration = [
    'username' => 'admin', // Existing username
    'password' => 'weak', // Weak password
];

echo "Validating registration with weak password:\n";
$result = $validator->validate($invalidRegistration, $registrationSchema);
if ($result->isValid()) {
    echo "✓ Registration validation passed!\n";
} else {
    echo "✗ Registration validation failed!\n";
    foreach ($result->getErrors() as $field => $errors) {
        echo "  $field: " . implode(', ', $errors) . "\n";
    }
}

echo "\n\n";

// 5. Exception Handling Example
echo "5. Exception Handling Example\n";
echo "----------------------------\n";

try {
    $validator->validateOrThrow($invalidData, $userSchema);
    echo "This should not be reached\n";
} catch (ValidationException $e) {
    echo "Caught ValidationException: " . $e->getMessage() . "\n";
    echo "Error details:\n";
    foreach ($e->getErrorMessages() as $error) {
        echo "  - $error\n";
    }
}

echo "\nValidation system demonstration completed!\n";
