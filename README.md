# Testor

The command-line tool that works hand-in-glove with [Automated Testing Kit](https://www.drupal.org/project/automated_testing_kit).

## Installation

On bare metal:
```shell
composer require performantlabs/testor
vendor/bin/robo testor:init
```

Under DDEV:
```shell
ddev composer install performantlabs/testor
ddev exec robo testor:init
```

After that put your project settings
to the created configuration files.


## Developer Tips
Don't forget to execute `composer install` after you add or make change of 
tasks definitions or parameters!