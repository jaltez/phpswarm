#!/bin/bash

# First check for syntax errors
echo "Checking for PHP syntax errors..."
find src -name "*.php" -type f -exec php -l {} \; | grep -v "No syntax errors" > /dev/null
if [ $? -ne 0 ]; then
    echo "There are syntax errors in the codebase. Fix them before proceeding."
    exit 1
fi

# Run PHP Code Beautifier and Fixer
echo "Running PHP Code Beautifier and Fixer..."
vendor/bin/phpcbf --standard=PSR12 src/

# Check remaining issues
echo "Checking for remaining issues..."
vendor/bin/phpcs --standard=PSR12 src/ --warning-severity=0 | grep "FILE:" | wc -l

echo "Fixing complete. Some line length warnings may remain." 