<?php

use PL\Robo\Plugin\BaseDataTransformer;
use Robo\Result;

/**
 * Example data transformer for sanitization.
 * 
 * This plugin demonstrates how to create a data transformer
 * that sanitizes sensitive information in a database.
 */
class ExampleDataTransformer extends BaseDataTransformer {
    /**
     * Constructor.
     */
    public function __construct() {
        parent::__construct(
            'example_sanitizer',
            'Example Data Sanitizer',
            'Demonstrates how to create a data sanitization plugin.'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function transform(array $options = []): Result {
        $this->printTaskInfo('Running example data sanitization...');
        
        // Example: Sanitize user emails
        $this->exec('drush sqlq "UPDATE users_field_data SET mail = CONCAT(\'user_\', uid, \'@example.com\') WHERE uid > 1"');
        
        // Example: Sanitize user names
        $this->exec('drush sqlq "UPDATE users_field_data SET name = CONCAT(\'user_\', uid) WHERE uid > 1"');
        
        // Example: Truncate sensitive session data
        $this->exec('drush sqlq "TRUNCATE TABLE sessions"');
        
        // Example: Sanitize custom tables with sensitive data
        $this->exec('drush sqlq "UPDATE customer_data SET phone = \'555-123-4567\', credit_card = NULL"');
        
        return Result::success($this, 'Data sanitization completed successfully.');
    }
}
