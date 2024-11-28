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

Add the S3-credentials and site name to the .testor.yaml configuration 
file. See the documentation. 
