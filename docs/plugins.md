# Testor Plugin System

Testor provides a flexible plugin system that allows you to extend and customize its functionality in various ways. This document explains how to use and create plugins for Testor.

## Overview

The plugin system allows you to:

1. Execute custom code at specific points in the workflow
2. Transform data in the database (including sanitization)
3. Customize the behavior of Testor without modifying its core code

There are three main approaches to extending Testor:

1. **Configuration-based approach**: Simple configuration in `.testor.yml`
2. **DDEV hooks**: Shell scripts for DDEV environments
3. **PHP plugins**: Custom PHP classes for advanced functionality

## 1. Configuration-Based Approach

The simplest way to extend Testor is through the `.testor.yml` configuration file. You can define hooks that execute at specific points in the workflow:

```yaml
hooks:
  pre_snapshot:
    commands:
      - 'drush cache:rebuild'
      - 'drush sqlq "UPDATE users_field_data SET login = 0 WHERE uid > 1"'
    
  post_import:
    commands:
      - 'drush updatedb -y'
      - 'drush config-set system.site mail noreply@example.com -y'
    sql_files:
      - '.testor/sql/post-import.sql'
    
  data_transform:
    commands:
      - 'drush sql:sanitize --yes'
    sql_files:
      - '.testor/sql/sanitize-custom-tables.sql'
```

This approach is ideal for simple customizations that can be expressed as shell commands or SQL files.

## 2. DDEV Hook System

If you're using DDEV, you can create custom scripts that run at specific points in the workflow:

- `.ddev/commands/web/pre-snapshot/`: Scripts that run before a snapshot is created
- `.ddev/commands/web/post-import/`: Scripts that run after a snapshot is imported
- `.ddev/commands/web/post-sanitize/`: Scripts that run after sanitization

Example script (`.ddev/commands/web/post-sanitize/custom-tables.sh`):

```bash
#!/bin/bash
## Description: Custom sanitization for specific tables
## Usage: Automatically executed after sanitization

echo "Sanitizing custom tables..."
mysql -udb -pdb db -e "UPDATE custom_table SET sensitive_field = 'REDACTED';"
```

This approach is ideal for users who prefer shell scripts and need to perform operations specific to DDEV.

## 3. PHP Plugin System

For advanced customization, you can create PHP plugins that integrate deeply with Testor:

1. Create a directory for your plugins: `.testor/plugins/`
2. Create PHP classes that implement one or more plugin interfaces:
   - `PreSnapshotHandlerInterface`: Runs before a snapshot is created
   - `PostImportHandlerInterface`: Runs after a snapshot is imported
   - `DataTransformerInterface`: Transforms data in the database

### Example Plugin

```php
<?php
// .testor/plugins/CustomSanitizer.php

use PL\Robo\Plugin\BaseDataTransformer;
use Robo\Result;

class CustomSanitizer extends BaseDataTransformer {
    public function __construct() {
        parent::__construct(
            'custom_sanitizer',
            'Custom Data Sanitizer',
            'Sanitizes sensitive information in custom tables'
        );
    }

    public function transform(array $options = []): Result {
        $this->printTaskInfo('Running custom sanitization...');
        
        // Sanitize custom tables
        $this->exec('drush sqlq "UPDATE customer_data SET phone = \'555-123-4567\', credit_card = NULL"');
        
        return Result::success($this, 'Custom sanitization completed successfully.');
    }
}
```

You can also register plugins in the `.testor.yml` configuration file:

```yaml
plugins:
  custom_sanitizer:
    class: 'MyProject\Plugin\CustomSanitizer'
    label: 'Custom Data Sanitizer'
    description: 'Sanitizes sensitive information in the database'
```

This approach is ideal for complex customizations that require PHP code and deep integration with Testor.

## Plugin Interfaces

Testor provides several interfaces for creating plugins:

- `PluginInterface`: Base interface for all plugins
- `PreSnapshotHandlerInterface`: Runs before a snapshot is created
- `PostImportHandlerInterface`: Runs after a snapshot is imported
- `DataTransformerInterface`: Transforms data in the database

## Base Classes

To simplify plugin development, Testor provides base classes for each plugin type:

- `BasePlugin`: Base class for all plugins
- `BasePreSnapshotHandler`: Base class for pre-snapshot handlers
- `BasePostImportHandler`: Base class for post-import handlers
- `BaseDataTransformer`: Base class for data transformers

## Examples

See the `examples/` directory for complete examples of each approach:

- `examples/.testor.yml`: Configuration-based approach
- `examples/.ddev/commands/web/custom-sanitize.sh`: DDEV hook script
- `examples/.testor/plugins/`: PHP plugin examples
- `examples/.testor/sql/`: SQL file examples
