# Testor

[![CI](https://github.com/Performant-Labs/testor/actions/workflows/php.yml/badge.svg?branch=main)](https://github.com/Performant-Labs/testor/actions/workflows/php.yml?query=workflow%3APHP)

A command-line database and file snapshot management tool. 
- Stores snapshots in an S3 or SFTP server.
- Can run a sanitization task when making the snapshot.
- Configured by default to work with Drupal.

## Full Documentation
Please see https://performantlabs.com/testor/testor.

## Installation

Via composer:
```shell
composer require performantlabs/testor
vendor/bin/testor self:init
```

Via composer under DDEV:
```shell
ddev composer require performantlabs/testor
ddev exec testor self:init
```

Directly download the latest release as a PHAR:
```shell
curl -L -o testor https://github.com/Performant-Labs/testor/releases/latest/download/testor.phar
php testor self:init
```

Add the S3/SFTP credentials and site name to the .testor.yml configuration 
file. See the documentation. 
