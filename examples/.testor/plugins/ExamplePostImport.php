<?php

use PL\Robo\Plugin\BasePostImportHandler;
use Robo\Result;

/**
 * Example post-import handler.
 * 
 * This plugin runs after a snapshot is imported and performs
 * post-processing tasks.
 */
class ExamplePostImport extends BasePostImportHandler {
    /**
     * Constructor.
     */
    public function __construct() {
        parent::__construct(
            'example_post_import',
            'Example Post-Import Handler',
            'Demonstrates how to create a post-import handler plugin.'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function postImport(array $options = []): Result {
        $this->printTaskInfo('Running example post-import handler...');
        
        // Example: Update site configuration after import
        $this->exec('drush config-set system.site mail noreply@example.com -y');
        
        // Example: Clear caches
        $this->exec('drush cache:rebuild');
        
        // Example: Run database updates
        $this->exec('drush updatedb -y');
        
        return Result::success($this, 'Post-import processing completed successfully.');
    }
}
