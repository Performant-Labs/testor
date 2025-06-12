-- Setup script to create example tables for sanitization demo

-- Create customer_data table
CREATE TABLE IF NOT EXISTS customer_data (
  id INT AUTO_INCREMENT PRIMARY KEY,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  email VARCHAR(255) NOT NULL,
  phone VARCHAR(50) NOT NULL,
  address VARCHAR(255) NOT NULL,
  city VARCHAR(100) NOT NULL,
  state VARCHAR(50) NOT NULL,
  zip VARCHAR(20) NOT NULL,
  credit_card VARCHAR(50)
);

-- Insert sample data into customer_data
INSERT INTO customer_data (first_name, last_name, email, phone, address, city, state, zip, credit_card) VALUES
('John', 'Doe', 'john.doe@example.com', '555-123-4567', '123 Real St', 'Cityville', 'CA', '90210', '4111-1111-1111-1111'),
('Jane', 'Smith', 'jane.smith@example.com', '555-987-6543', '456 Actual Ave', 'Townsburg', 'NY', '10001', '5555-5555-5555-5555'),
('Bob', 'Johnson', 'bob.johnson@example.com', '555-555-5555', '789 True Blvd', 'Villageton', 'TX', '75001', '3782-822463-10005');

-- Create orders table
CREATE TABLE IF NOT EXISTS orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT NOT NULL,
  order_date DATETIME NOT NULL,
  total_amount DECIMAL(10,2) NOT NULL,
  shipping_address VARCHAR(255) NOT NULL,
  billing_address VARCHAR(255) NOT NULL,
  FOREIGN KEY (customer_id) REFERENCES customer_data(id)
);

-- Insert sample data into orders
INSERT INTO orders (customer_id, order_date, total_amount, shipping_address, billing_address) VALUES
(1, '2023-01-15 10:30:00', 125.99, '123 Real St, Cityville, CA 90210', '123 Real St, Cityville, CA 90210'),
(2, '2023-02-20 14:45:00', 89.50, '456 Actual Ave, Townsburg, NY 10001', '456 Actual Ave, Townsburg, NY 10001'),
(3, '2023-03-10 09:15:00', 210.75, '789 True Blvd, Villageton, TX 75001', '789 True Blvd, Villageton, TX 75001');

-- Create access_logs table
CREATE TABLE IF NOT EXISTS access_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  access_time DATETIME NOT NULL,
  ip_address VARCHAR(50) NOT NULL,
  action VARCHAR(100) NOT NULL
);

-- Insert sample data into access_logs
INSERT INTO access_logs (user_id, access_time, ip_address, action) VALUES
(1, '2023-04-01 08:30:00', '192.168.1.100', 'login'),
(2, '2023-04-01 09:45:00', '192.168.1.101', 'view_profile'),
(3, '2023-04-01 10:15:00', '192.168.1.102', 'update_settings');

-- Create payment_logs table
CREATE TABLE IF NOT EXISTS payment_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  payment_time DATETIME NOT NULL,
  payment_method VARCHAR(50) NOT NULL,
  transaction_id VARCHAR(100) NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id)
);

-- Insert sample data into payment_logs
INSERT INTO payment_logs (order_id, payment_time, payment_method, transaction_id, amount) VALUES
(1, '2023-01-15 10:35:00', 'credit_card', 'TXN12345', 125.99),
(2, '2023-02-20 14:50:00', 'paypal', 'TXN67890', 89.50),
(3, '2023-03-10 09:20:00', 'credit_card', 'TXN24680', 210.75);

-- Create comments table
CREATE TABLE IF NOT EXISTS comments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  uid INT NOT NULL,
  name VARCHAR(100) NOT NULL,
  mail VARCHAR(255) NOT NULL,
  hostname VARCHAR(100) NOT NULL,
  comment_text TEXT NOT NULL,
  created_at DATETIME NOT NULL
);

-- Insert sample data into comments
INSERT INTO comments (uid, name, mail, hostname, comment_text, created_at) VALUES
(1, 'John Doe', 'john.doe@example.com', '192.168.1.100', 'This is a great product!', '2023-01-20 15:30:00'),
(2, 'Jane Smith', 'jane.smith@example.com', '192.168.1.101', 'I love the service!', '2023-02-25 11:45:00'),
(3, 'Bob Johnson', 'bob.johnson@example.com', '192.168.1.102', 'Could use some improvements.', '2023-03-15 14:20:00');

-- Create custom_users table
CREATE TABLE IF NOT EXISTS custom_users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL,
  password VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL,
  reset_token VARCHAR(100),
  last_login DATETIME
);

-- Insert sample data into custom_users
INSERT INTO custom_users (username, password, email, reset_token, last_login) VALUES
('johndoe', 'hashedpassword123', 'john.doe@example.com', 'token123', '2023-04-01 10:00:00'),
('janesmith', 'hashedpassword456', 'jane.smith@example.com', 'token456', '2023-04-02 11:30:00'),
('bobjohnson', 'hashedpassword789', 'bob.johnson@example.com', 'token789', '2023-04-03 09:15:00');
