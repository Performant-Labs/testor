<?php

use PL\Robo\Plugin\BasePreSnapshotHandler;
use Robo\Result;

/**
 * Example pre-snapshot handler.
 * 
 * This plugin runs before a snapshot is created and performs
 * some preparation tasks.
 */
class ExamplePreSnapshot extends BasePreSnapshotHandler {
    /**
     * Constructor.
     */
    public function __construct() {
        parent::__construct(
            'example_pre_snapshot',
            'Example Pre-Snapshot Handler',
            'Demonstrates how to create a pre-snapshot handler plugin.'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function preSnapshot(array $options = []): Result {
        $this->printTaskInfo('Running example pre-snapshot handler...');
        
        // Example: Check if required tables exist
        $result = $this->exec('drush sqlq "SHOW TABLES LIKE \'users\'"');
        if ($result->getExitCode() !== 0) {
            return Result::error($this, 'Required table "users" does not exist.');
        }
        
        // Example: Prepare the database for snapshot
        $this->exec('drush sqlq "UPDATE users SET login = 0 WHERE uid > 1"');
        
        return Result::success($this, 'Pre-snapshot preparation completed successfully.');
    }
}
