# Database Sanitization in Testor

This document provides instructions on how to use the database sanitization feature in Testor, which allows you to automatically sanitize database snapshots as part of your workflow.

## Table of Contents

- [Overview](#overview)
- [Configuration](#configuration)
  - [Setting Up Sanitization](#1-setting-up-sanitization)
  - [Creating the Configuration File](#2-creating-the-configuration-file)
- [Usage](#usage)
  - [Creating Sanitized Snapshots](#creating-sanitized-snapshots)
  - [Skipping Sanitization](#skipping-sanitization)
  - [Working with Remote Sources](#working-with-remote-sources)
  - [Uploading Sanitized Snapshots](#uploading-sanitized-snapshots)
  - [Getting and Importing Sanitized Snapshots](#getting-and-importing-sanitized-snapshots)
- [Advanced Usage](#advanced-usage)
  - [Custom Sanitization Commands](#custom-sanitization-commands)
  - [Integration with DDEV](#integration-with-ddev)
- [Troubleshooting](#troubleshooting)
  - [Sanitization Not Running](#sanitization-not-running)
  - [Command Errors](#command-errors)
- [Best Practices](#best-practices)
- [Creating Plugins for Database Sanitization](#creating-plugins-for-database-sanitization)
  - [Configuration-based Hooks in .testor.yml](#1-configuration-based-hooks-in-testoryml)
  - [DDEV Hook Scripts](#2-ddev-hook-scripts)
  - [PHP Plugins for Advanced Customization](#3-php-plugins-for-advanced-customization)
- [Further Reading](#further-reading)

## Overview

The database sanitization feature in Testor enables you to:

- Automatically sanitize database snapshots during creation
- Skip sanitization when needed
- Configure custom sanitization commands
- Work with both local and remote database sources

## Configuration

### 1. Setting Up Sanitization

To enable database sanitization, you need to configure the sanitization command in your `.testor.yml` file:

```yaml
sanitize:
  command: "drush sql:sanitize -y"
```

This configuration tells Testor to run Drush's SQL sanitize command after importing a database snapshot. You can customize this command to use any sanitization tool or script that fits your project's needs.

### 2. Creating the Configuration File

If you don't already have a `.testor.yml` file, you can create one by running:

```shell
# For DDEV environments
ddev exec vendor/bin/testor self:init

# For non-DDEV environments
vendor/bin/testor self:init
```

This will create an example configuration file that you can then edit to add your sanitization command.

## Usage

### Creating Sanitized Snapshots

To create a database snapshot with sanitization:

```shell
# For DDEV environments
ddev exec vendor/bin/testor snapshot:create --env=@self --name=sanitized

# For non-DDEV environments
vendor/bin/testor snapshot:create --env=@self --name=sanitized
```

This command will:
1. Create a database snapshot from your local environment
2. Run the sanitization command specified in your configuration
3. Save the sanitized snapshot

### Skipping Sanitization

If you need to create a snapshot without sanitizing it (for example, for development or debugging purposes), use the `--do-not-sanitize` option:

```shell
ddev exec vendor/bin/testor snapshot:create --env=@self --name=raw --do-not-sanitize
```

### Working with Remote Sources

You can also sanitize snapshots from remote sources (like Pantheon environments):

```shell
ddev exec vendor/bin/testor snapshot:create --env=dev --name=sanitized
```

This will:
1. Pull a snapshot from the remote environment
2. Import it to your local database
3. Run the sanitization command
4. Create a sanitized snapshot

### Uploading Sanitized Snapshots

To upload your sanitized snapshot to your configured storage (S3 or SFTP):

```shell
ddev exec vendor/bin/testor snapshot:create --env=@self --name=sanitized --put
```

### Getting and Importing Sanitized Snapshots

To download and import a previously sanitized snapshot:

```shell
ddev exec vendor/bin/testor snapshot:get --name=sanitized --import
```

## Advanced Usage

### Custom Sanitization Commands

You can customize the sanitization process by modifying the `sanitize.command` in your `.testor.yml` file. Examples:

```yaml
# Basic Drush sanitization
sanitize:
  command: "drush sql:sanitize -y"

# Custom sanitization script
sanitize:
  command: "./scripts/custom-sanitize.sh"

# More complex sanitization with multiple commands
sanitize:
  command: "drush sql:sanitize -y && ./scripts/additional-sanitize.sh"
```

### Integration with DDEV

When using DDEV, you can run sanitization commands within the DDEV environment:

```yaml
sanitize:
  command: "ddev exec drush sql:sanitize -y"
```

## Troubleshooting

### Sanitization Not Running

If sanitization is not running as expected, check:

1. That you have a valid `sanitize.command` in your `.testor.yml` file
2. That you're not using the `--do-not-sanitize` option
3. That the command specified in `sanitize.command` exists and is executable

### Command Errors

If you encounter errors during sanitization:

1. Run the sanitization command manually to see the specific error
2. Check that any dependencies required by your sanitization command are installed
3. Verify that your database connection is working properly

## Best Practices

1. Always sanitize production data before using it in development environments
2. Test your sanitization command to ensure it properly removes sensitive data
3. Consider using version control for your `.testor.yml` file, but exclude any sensitive credentials
4. For Drupal sites, leverage Drush's sanitization capabilities which are designed specifically for Drupal databases

## Creating Plugins for Database Sanitization

There are three ways to extend the sanitization functionality in Testor:

### 1. Configuration-based Hooks in .testor.yml

The simplest way to customize sanitization is by configuring the sanitization command in your `.testor.yml` file:

```yaml
sanitize:
  command: "drush sql:sanitize -y"
```

You can extend this with custom scripts or additional commands:

```yaml
sanitize:
  command: "drush sql:sanitize -y && ./scripts/custom-sanitize.sh"
```

### 2. DDEV Hook Scripts

For shell-based extensions, you can create DDEV hook scripts:

1. Create a script in the `.ddev/commands/web/` directory:

```shell
# Create the directory if it doesn't exist
mkdir -p .ddev/commands/web/

# Create the sanitize script
cat > .ddev/commands/web/sanitize.sh << 'EOF'
#!/bin/bash

##
# Custom database sanitization script
#
# Usage: ddev sanitize
##

# Run Drush sanitize command
drush sql:sanitize -y

# Add your custom sanitization logic here
# For example:
# - Sanitize additional tables not covered by Drush
# - Apply project-specific data transformations
# - Generate test data

echo "Database sanitization complete."
EOF

# Make the script executable
chmod +x .ddev/commands/web/sanitize.sh
```

2. Update your `.testor.yml` to use this script:

```yaml
sanitize:
  command: "ddev sanitize"
```

### 3. PHP Plugins for Advanced Customization

For more advanced sanitization needs, you can create PHP plugins that implement the appropriate interfaces:

1. Create a PHP class that implements the appropriate handler interface:

```php
<?php

namespace MyProject\Testor\Plugin;

use PL\Robo\Contract\PostImportHandlerInterface;
use Robo\Result;

class CustomSanitizer implements PostImportHandlerInterface {

  /**
   * Perform custom sanitization after database import.
   *
   * @param array $options
   *   The options passed to the command.
   *
   * @return \Robo\Result
   *   The result of the operation.
   */
  public function postImport(array $options): Result {
    // Your custom sanitization logic here
    // For example:
    // - Connect to the database
    // - Run custom queries to sanitize data
    // - Log sanitization actions

    // Return success or failure
    return new Result($this, 0, 'Custom sanitization completed successfully');
  }

}
```

2. Register your plugin with Testor by adding it to your project's composer.json:

```json
{
  "extra": {
    "testor-plugins": [
      "MyProject\\Testor\\Plugin\\CustomSanitizer"
    ]
  }
}
```

3. Ensure your plugin class is autoloadable:

```json
{
  "autoload": {
    "psr-4": {
      "MyProject\\Testor\\Plugin\\": "src/Plugin"
    }
  }
}
```

## Example Plugin

An example plugin is provided in the `examples/plugins` directory to help you understand how to create custom sanitization plugins. The example demonstrates sanitizing customer data, order information, and log tables.

### Running the Example Plugin

To use the example plugin:

1. **Copy the plugin file to your project**:

   ```shell
   # Create a directory for your plugins if it doesn't exist
   mkdir -p src/Plugin

   # Copy the example plugin
   cp examples/plugins/CustomSanitizer.php src/Plugin/
   ```

2. **Update your composer.json**:

   Add the plugin to your project's autoloading and register it with Testor:

   ```json
   {
     "autoload": {
       "psr-4": {
         "MyProject\\Testor\\Plugin\\": "src/Plugin"
       }
     },
     "extra": {
       "testor-plugins": [
         "MyProject\\Testor\\Plugin\\CustomSanitizer"
       ]
     }
   }
   ```

3. **Update the namespace and interface**:

   Edit the `src/Plugin/CustomSanitizer.php` file to:
   - Change the namespace from `PL\Examples\Plugins` to `MyProject\Testor\Plugin`
   - Update the interface from `TaskInterface` to `PostImportHandlerInterface`
   - Change the `run()` method to `postImport(array $options)`

4. **Update the autoloader**:

   ```shell
   composer dump-autoload
   ```

5. **Run a snapshot creation with sanitization**:

   ```shell
   ddev exec vendor/bin/testor snapshot:create --env=@self --name=sanitized
   ```

### Customizing the Example

The example plugin includes methods for sanitizing different types of data:

- `sanitizeCustomerData()`: Anonymizes customer personal information
- `sanitizeOrderData()`: Sanitizes shipping addresses and payment details
- `sanitizeLogData()`: Truncates log tables

You can modify these methods to match your specific database schema and sanitization requirements. The example includes commented SQL queries that you can adapt for your own database tables.

## Alternative Approach: DDEV Hook Script

As an alternative to the PHP plugin approach, you can achieve similar sanitization functionality using a DDEV hook script. While this approach is simpler to implement, it lacks the powerful advantages of Testor's plugin system.

An example DDEV hook script is provided in the `examples/ddev-hooks/sanitize-custom.sh` file.

### Setting Up the DDEV Hook Script

1. **Create the DDEV command script**:

   ```shell
   # Create the directory if it doesn't exist
   mkdir -p .ddev/commands/web/

   # Copy the example script
   cp examples/ddev-hooks/sanitize-custom.sh .ddev/commands/web/

   # Make it executable
   chmod +x .ddev/commands/web/sanitize-custom.sh
   ```

2. **Configure Testor to use the script**:

   Update your `.testor.yml` file to use the DDEV command:

   ```yaml
   sanitize:
     command: "ddev sanitize-custom"
   ```

3. **Run a snapshot creation with sanitization**:

   ```shell
   ddev exec vendor/bin/testor snapshot:create --env=@self --name=sanitized
   ```

### Why Use the PHP Plugin Approach?

The PHP plugin approach offers several significant advantages:

1. **Full Access to PHP Ecosystem**: Plugins can leverage the entire PHP ecosystem, including:
   - Drupal's API for more sophisticated database operations
   - Composer packages for specialized sanitization needs
   - Your project's existing custom PHP libraries and utilities

2. **Deep Integration with Testor**: Plugins are proper Testor citizens with:
   - Access to Testor's configuration system
   - Ability to interact with other Testor components
   - Proper dependency injection and service container access
   - Consistent error handling and logging

3. **Object-Oriented Approach**: Plugins can use OOP principles for:
   - Better code organization with classes and inheritance
   - Reusable sanitization components
   - Unit testing capabilities
   - Type safety and IDE support

4. **Advanced Data Processing**: For complex sanitization needs:
   - Process data in memory without shell roundtrips
   - Use sophisticated data transformation algorithms
   - Handle complex data relationships
   - Implement conditional sanitization logic

### Comparing the Two Approaches

| Feature | PHP Plugin | DDEV Hook Script |
|---------|-----------|------------------|
| **Integration** | Deep integration with Testor's internals | Simple command execution |
| **Ecosystem Access** | Full PHP ecosystem and Drupal APIs | Limited to command-line tools |
| **Complexity** | More sophisticated setup | Simpler setup |
| **Flexibility** | Highly flexible with OOP design | Limited by shell scripting capabilities |
| **Maintenance** | Follows PHP best practices | Requires shell scripting expertise |
| **Performance** | Efficient for complex operations | May require multiple process spawns |
| **Debugging** | Full PHP debugging capabilities | Limited to shell script debugging |
| **Testing** | Can be unit tested | Requires integration testing |

Choose the PHP plugin approach when you need sophisticated sanitization logic, integration with your PHP codebase, or access to Drupal's APIs. Use the DDEV hook script approach for simpler scenarios or when you prefer shell scripting.

## Accessing Secret Values in CI/CD Environments

When running sanitization in CI/CD environments like GitHub Actions or GitLab CI, you may need to access secret values such as API keys, database credentials, or other sensitive information. Each sanitization method has different ways of accessing these secrets:

### 1. Configuration-based Approach (.testor.yml)

When using the configuration-based approach with `.testor.yml`:

1. **Environment Variable Substitution**:
   ```yaml
   sanitize:
     command: "drush sql:sanitize -y --api-key=$SANITIZE_API_KEY"
   ```

2. **Access in CI/CD**:
   - In GitHub Actions, define secrets in the repository settings and reference them in your workflow:
     ```yaml
     # .github/workflows/sanitize.yml
     jobs:
       sanitize:
         runs-on: ubuntu-latest
         steps:
           - uses: actions/checkout@v3
           - name: Run sanitization
             env:
               SANITIZE_API_KEY: ${{ secrets.SANITIZE_API_KEY }}
             run: vendor/bin/testor snapshot:create --env=@self --name=sanitized
     ```

   - In GitLab CI, define variables in the CI/CD settings and reference them in your pipeline:
     ```yaml
     # .gitlab-ci.yml
     sanitize:
       stage: test
       script:
         - vendor/bin/testor snapshot:create --env=@self --name=sanitized
       variables:
         SANITIZE_API_KEY: $SANITIZE_API_KEY
     ```

### 2. DDEV Hook Script Approach

When using DDEV hook scripts:

1. **Environment Variables in Scripts**:
   ```bash
   #!/bin/bash

   # Access environment variables passed from CI/CD
   API_KEY="${SANITIZE_API_KEY}"

   # Use the API key in your sanitization logic
   curl -H "Authorization: Bearer ${API_KEY}" https://api.example.com/sanitize
   ```

2. **Passing Variables to DDEV**:
   - In GitHub Actions:
     ```yaml
     - name: Run DDEV sanitization
       env:
         SANITIZE_API_KEY: ${{ secrets.SANITIZE_API_KEY }}
       run: ddev exec "SANITIZE_API_KEY=$SANITIZE_API_KEY ./scripts/sanitize.sh"
     ```

   - In GitLab CI:
     ```yaml
     sanitize:
       script:
         - ddev exec "SANITIZE_API_KEY=$SANITIZE_API_KEY ./scripts/sanitize.sh"
     ```

### 3. PHP Plugin Approach

When using PHP plugins:

1. **Environment Variables in PHP**:
   ```php
   public function postImport(array $options): Result {
     // Access environment variables
     $apiKey = getenv('SANITIZE_API_KEY');

     // Use the API key in your sanitization logic
     $client = new ApiClient($apiKey);
     $client->sanitizeData();

     return new Result($this, 0, 'Sanitization completed');
   }
   ```

2. **Testor Configuration System**:
   ```php
   // Access secrets from Testor's configuration system
   $apiKey = $this->testorConfig->get('secrets.api_key');
   ```

3. **Dependency Injection**:
   ```php
   // Inject a secrets manager service
   public function __construct(SecretsManagerInterface $secretsManager) {
     $this->secretsManager = $secretsManager;
   }

   public function postImport(array $options): Result {
     $apiKey = $this->secretsManager->getSecret('api_key');
     // Use the API key...
   }
   ```

### Security Best Practices

1. **Never hardcode secrets** in your sanitization scripts or plugins
2. **Use environment variables** for passing secrets to your sanitization processes
3. **Mask secrets in logs** to prevent accidental exposure
4. **Use secret management services** provided by your CI/CD platform
5. **Limit secret access** to only the jobs that need them
6. **Rotate secrets regularly** to minimize the impact of potential leaks

## Working with the Sanitized Database

The result of the sanitization process is a modified database with sensitive data replaced by anonymized values. Here's what happens during and after sanitization:

### What Gets Sanitized

When using Drush's `sql:sanitize` command (the default approach), the following data is typically sanitized:

1. **User accounts**: Email addresses, passwords, and personal information
2. **Sessions**: All session data is typically truncated
3. **Logs**: Watchdog and other log tables may be truncated
4. **Custom fields**: Fields marked for sanitization in your Drupal code

When using custom sanitization (via plugins or scripts), you can extend this to include:

- Custom tables with sensitive customer information
- Payment and financial data
- Personally identifiable information (PII) in content
- Analytics and tracking data

### Accessing the Sanitized Database

After sanitization, the database is immediately available for use. You can:

1. **Continue working with the local database**:
   ```shell
   # Access the database directly
   ddev mysql

   # Or use Drush to interact with it
   ddev drush sql:query "SELECT * FROM users_field_data LIMIT 5;"
   ```

2. **Create a snapshot of the sanitized database**:
   ```shell
   # This happens automatically if you used snapshot:create
   ddev exec vendor/bin/testor snapshot:create --env=@self --name=sanitized
   ```

3. **Share the sanitized snapshot**:
   ```shell
   # Upload the sanitized snapshot to storage
   ddev exec vendor/bin/testor snapshot:create --env=@self --name=sanitized --put
   ```

### Verifying Sanitization

It's important to verify that your sanitization process worked correctly:

1. **Inspect sensitive tables**:
   ```shell
   # Check user emails have been sanitized
   ddev drush sql:query "SELECT mail FROM users_field_data;"

   # Check custom tables
   ddev drush sql:query "SELECT phone_number FROM custom_customers LIMIT 10;"
   ```

2. **Validate data integrity**:
   ```shell
   # Make sure the site still works with sanitized data
   ddev drush status
   ```

3. **Automated testing**:
   Consider adding automated tests that verify your sanitization process correctly anonymizes sensitive data while maintaining database integrity.

### Using the Sanitized Database for Development

The sanitized database is safe to use for development purposes:

1. **Local development**: Use it as your local development database
2. **Testing environments**: Deploy it to shared testing environments
3. **CI/CD pipelines**: Use it in automated testing
4. **Training**: Provide it to new team members for onboarding

The sanitized database gives you the structure and content of production data without the security and privacy risks of actual user information.

## Setting Up a Custom DDEV Command for Testor

To simplify working with Testor in DDEV environments, you can set up a custom DDEV command that allows you to use `ddev testor` instead of the longer `ddev exec vendor/bin/testor`. This follows DDEV's official approach for creating custom commands.

### Creating the Custom Command

1. **Create a command file in the web container directory**:

   ```shell
   # Create the directory if it doesn't exist
   mkdir -p .ddev/commands/web/
   
   # Create the command file
   cat > .ddev/commands/web/testor << 'EOF'
   #!/bin/bash

   ## Description: Run Testor commands directly
   ## Usage: testor [args]
   ## Example: "ddev testor snapshot:create --env=@self --name=sanitized"

   # Pass all arguments to the Testor CLI
   vendor/bin/testor "$@"
   EOF
   
   # Make it executable
   chmod +x .ddev/commands/web/testor
   ```

2. **Test the command**:

   ```shell
   ddev testor --version
   ```

### Using the Custom Command

Once set up, you can use the simplified syntax throughout your workflow:

```shell
# Create a sanitized snapshot
ddev testor snapshot:create --env=@self --name=sanitized

# Get a snapshot
ddev testor snapshot:get --name=latest --import

# Run sanitization
ddev testor snapshot:create --env=@self --name=sanitized --do-not-sanitize=false
```

### Benefits of Using Custom Commands

1. **Shorter syntax**: Reduces typing and makes commands easier to remember
2. **Better developer experience**: Streamlines common operations
3. **Consistent with DDEV practices**: Follows DDEV's recommended approach for extending functionality
4. **Project-specific**: Can be committed to version control for team use

You can also create global custom commands by placing them in your home directory's `.ddev/commands/web/` folder, making them available across all your DDEV projects.

## Further Reading

For more information about Testor, please visit the [official documentation](https://performantlabs.com/testor/testor).
