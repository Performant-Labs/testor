<?php

namespace PL\Examples\Plugins;

// Using Robo's TaskInterface for proper type compatibility
use Robo\Contract\TaskInterface;
use Robo\Result;
use Robo\Symfony\ConsoleIO;

/**
 * Example custom sanitizer plugin for Testor.
 * 
 * This plugin demonstrates how to create a custom sanitization plugin
 * that runs after database import to perform additional sanitization tasks.
 *
 * In a real implementation, you would implement the appropriate interface
 * from your Testor installation. This example uses TaskInterface as a fallback.
 */
class CustomSanitizer implements TaskInterface {
  
  /**
   * Run the task.
   * 
   * In a real implementation, this would be the postImport method
   * from the PostImportHandlerInterface.
   * 
   * @return \Robo\Result
   *   The result of the operation.
   */
  public function run(): Result {
    // Get database connection details from Testor config
    // In a real implementation, you would use dependency injection
    // or other methods to get access to the database connection.
    
    // Example: Sanitize a custom table not handled by Drush
    try {
      // Example of running a database query
      // In a real implementation, you would use proper database abstraction
      $this->sanitizeCustomerData();
      $this->sanitizeOrderData();
      $this->sanitizeLogData();
      
      // Using $this as the task is valid since we implement TaskInterface
      return new Result($this, 0, 'Custom sanitization completed successfully');
    } 
    catch (\Exception $e) {
      // Using $this as the task is valid since we implement TaskInterface
      return new Result($this, 1, 'Custom sanitization failed: ' . $e->getMessage());
    }
  }
  
  /**
   * Example method to sanitize customer data.
   */
  protected function sanitizeCustomerData() {
    // Example SQL that would sanitize a custom_customers table
    $sql = "
      UPDATE custom_customers 
      SET 
        phone_number = CONCAT('555', LPAD(FLOOR(RAND() * 10000000), 7, '0')),
        address = CONCAT('123 Example St, City ', FLOOR(RAND() * 100)),
        credit_card_number = CONCAT('XXXX-XXXX-XXXX-', LPAD(FLOOR(RAND() * 10000), 4, '0'))
      WHERE 1;
    ";
    
    // In a real implementation, you would execute this SQL
    // $this->dbConnection->execute($sql);
    
    // For this example, we'll just log what would happen
    $this->log("Would execute: $sql");
  }
  
  /**
   * Example method to sanitize order data.
   */
  protected function sanitizeOrderData() {
    // Example SQL that would sanitize a custom_orders table
    $sql = "
      UPDATE custom_orders
      SET 
        shipping_address = CONCAT('123 Example St, City ', FLOOR(RAND() * 100)),
        payment_details = 'SANITIZED'
      WHERE order_date < DATE_SUB(NOW(), INTERVAL 1 YEAR);
    ";
    
    // In a real implementation, you would execute this SQL
    // $this->dbConnection->execute($sql);
    
    // For this example, we'll just log what would happen
    $this->log("Would execute: $sql");
  }
  
  /**
   * Example method to sanitize log data.
   */
  protected function sanitizeLogData() {
    // Example SQL that would truncate log tables
    $sql = "
      TRUNCATE TABLE custom_access_log;
      TRUNCATE TABLE custom_error_log;
    ";
    
    // In a real implementation, you would execute this SQL
    // $this->dbConnection->execute($sql);
    
    // For this example, we'll just log what would happen
    $this->log("Would execute: $sql");
  }
  
  /**
   * Simple logging method.
   */
  protected function log($message) {
    // In a real implementation, you would use a proper logger
    // For this example, we'll just print to stdout
    echo $message . PHP_EOL;
  }
}
