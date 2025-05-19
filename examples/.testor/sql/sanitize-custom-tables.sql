-- Example SQL sanitization script for custom tables
-- This file demonstrates how to sanitize data using raw SQL

-- Sanitize customer data
UPDATE customer_data SET 
  first_name = CONCAT('First', id),
  last_name = CONCAT('Last', id),
  email = CONCAT('customer_', id, '@example.com'),
  phone = CONCAT('555-', LPAD(id, 3, '0'), '-', LPAD(id * 7, 4, '0')),
  address = '123 Main St',
  city = 'Anytown',
  state = 'ST',
  zip = '12345',
  credit_card = NULL;

-- Sanitize order data
UPDATE orders SET 
  shipping_address = '123 Main St, Anytown, ST 12345',
  billing_address = '123 Main St, Anytown, ST 12345';

-- Truncate sensitive logs
TRUNCATE TABLE access_logs;
TRUNCATE TABLE payment_logs;

-- Anonymize user comments
UPDATE comments SET 
  name = CONCAT('Anonymous_', uid),
  mail = CONCAT('anonymous_', uid, '@example.com'),
  hostname = '127.0.0.1';

-- Set all passwords to a known value (for development only)
-- In Drupal, this would typically be handled by drush sql:sanitize
-- This is just an example for custom tables
UPDATE custom_users SET 
  password = '$S$DkIkdKLIvRK0iVHm99X7B/M8QC17E1Tp/kMOd7.w3qLBmJ5F7BV.',  -- 'password'
  reset_token = NULL,
  last_login = NULL;
