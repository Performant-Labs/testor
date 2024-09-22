# Testor

The command-line tool that works hand-in-glove with [Automated Testing Kit](https://www.drupal.org/project/automated_testing_kit).

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


## Developer Tips
Don't forget to execute `composer install` after you add or change
tasks definitions or parameters!
