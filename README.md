# Testor

[![CI](https://github.com/Performant-Labs/testor/actions/workflows/php.yml/badge.svg?branch=main)](https://github.com/Performant-Labs/testor/actions/workflows/php.yml?query=workflow%3APHP)

The command-line database and file snapshot management tool for Drupal.

## Documentation
Please see https://performantlabs.com/testor/testor.

## Installation

On bare metal:
```shell
composer require performantlabs/testor
vendor/bin/testor self:init
```

Under DDEV:
```shell
ddev composer require performantlabs/testor
ddev exec testor self:init
```

Add the S3 or SFTP credentials and site name to the .testor.yaml configuration
file. See the documentation.

## Database Sanitization with DDEV

There is now built-in support for database sanitization using DDEV and MariaDB. This allows you to:

1. Pull database snapshots from remote sources
2. Import them into a local MariaDB instance managed by DDEV
3. Automatically sanitize the database using Drush's `sql:sanitize` command

### Setup

The setup is already configured if you're using DDEV. The `.testor.yml` file has been updated to work with DDEV's MariaDB container, and custom DDEV commands have been added to make sanitization easy.

### Available Commands

- **Sanitize an existing database**:
  ```shell
  ddev sanitize-db
  ```

- **Auto-sanitize after database import**:
  Database imports via `ddev import-db` will automatically trigger sanitization. To skip sanitization, use:
  ```shell
  TESTOR_SKIP_SANITIZE=true ddev import-db --src=your-db.sql.gz
  ```

- **Pull and sanitize a snapshot in one step**:
  ```shell
  ddev pull-and-sanitize [snapshot_name]
  ```
  This will pull the specified snapshot, import it into the DDEV MariaDB, and sanitize it.

### How It Works

The sanitization process uses Drush's `sql:sanitize` command, which is designed for Drupal sites. It sanitizes user data by:
- Anonymizing user accounts
- Removing sensitive information
- Truncating session tables
- Applying any custom sanitization rules defined in your Drupal modules
