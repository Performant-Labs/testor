#!/bin/bash

## Description: Sanitize the database using Drush
## Usage: sanitize-db
## Example: "ddev sanitize-db"

# Ensure we're in the Drupal docroot
cd /var/www/html/src

# Run the sanitization command
echo "Starting database sanitization..."
drush sql:sanitize --yes

# Check if the command was successful
if [ $? -eq 0 ]; then
    echo "Database sanitization completed successfully."
else
    echo "Database sanitization failed. Check the error messages above."
    exit 1
fi

exit 0
