#!/bin/bash

##
# Custom database sanitization script for Testor
#
# This script performs the same sanitization as the PHP plugin example:
# - Sanitizes customer data (phone numbers, addresses, credit cards)
# - Sanitizes order data (shipping addresses, payment details)
# - Truncates log tables
#
# Usage: ddev sanitize-custom
##

# Exit on error
set -e

echo "Starting custom database sanitization..."

# First run Drush's built-in sanitization
echo "Running Drush sanitization..."
drush sql:sanitize -y

# Sanitize customer data
echo "Sanitizing customer data..."
mysql -u db -pdb db << EOF
  UPDATE custom_customers 
  SET 
    phone_number = CONCAT('555', LPAD(FLOOR(RAND() * 10000000), 7, '0')),
    address = CONCAT('123 Example St, City ', FLOOR(RAND() * 100)),
    credit_card_number = CONCAT('XXXX-XXXX-XXXX-', LPAD(FLOOR(RAND() * 10000), 4, '0'))
  WHERE 1;
EOF

# Sanitize order data
echo "Sanitizing order data..."
mysql -u db -pdb db << EOF
  UPDATE custom_orders
  SET 
    shipping_address = CONCAT('123 Example St, City ', FLOOR(RAND() * 100)),
    payment_details = 'SANITIZED'
  WHERE order_date < DATE_SUB(NOW(), INTERVAL 1 YEAR);
EOF

# Truncate log tables
echo "Truncating log tables..."
mysql -u db -pdb db << EOF
  TRUNCATE TABLE custom_access_log;
  TRUNCATE TABLE custom_error_log;
EOF

echo "Custom database sanitization completed successfully."
