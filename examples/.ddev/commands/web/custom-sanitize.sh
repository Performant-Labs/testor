#!/bin/bash

## Description: Custom sanitization for specific tables
## Usage: custom-sanitize
## Example: "ddev custom-sanitize"

# Ensure we're in the Drupal docroot
cd /var/www/html/src

echo "Starting custom sanitization process..."

# Example: Sanitize user emails
echo "Sanitizing user emails..."
drush sqlq "UPDATE users_field_data SET mail = CONCAT('user_', uid, '@example.com') WHERE uid > 1"

# Example: Sanitize user names
echo "Sanitizing user names..."
drush sqlq "UPDATE users_field_data SET name = CONCAT('user_', uid) WHERE uid > 1"

# Example: Truncate sensitive session data
echo "Truncating session data..."
drush sqlq "TRUNCATE TABLE sessions"

# Example: Sanitize custom tables with sensitive data
echo "Sanitizing custom tables..."
drush sqlq "UPDATE customer_data SET phone = '555-123-4567', credit_card = NULL"

# Example: Import a custom SQL sanitization script
echo "Running custom SQL sanitization script..."
mysql -udb -pdb db < /var/www/html/.testor/sql/sanitize-custom-tables.sql

echo "Custom sanitization completed successfully!"
exit 0
