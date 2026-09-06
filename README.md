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

## Non-Drupal / Postgres projects

`sql.command` and `sqldump.command` are plain shell commands — Testor never
assumes Drupal or MySQL/MariaDB in code, only in `.testor.yml`'s *conventional*
default (`drush sql:dump`). Point them at `pg_dump`/`psql` instead and
`snapshot:create`/`snapshot:put`/`snapshot:get`/`snapshot:import` (and the
`snapshot:restore` consumer command built on them) work against Postgres with
no code changes:

```yaml
sqldump:
  command: 'pg_dump -h ${DB_HOST} -p ${DB_PORT} -U ${DB_USER} your_database_name'
sql:
  command: 'psql -h ${DB_HOST} -p ${DB_PORT} -U ${DB_USER} your_database_name'
```

`${VAR}` is substituted from the environment at config-load time (see
`Testor::createConfiguration`) — set `PGPASSWORD` in the environment rather
than embedding a password in `.testor.yml`, the same libpq convention
`pg_dump`/`psql` already support.

`snapshot:restore` (the consumer command) needs no further configuration for a
non-Drupal target: its Drupal-specific step (`DbUuidNormalize`) is a
documented no-op whenever no `uuid.value`/`--uuid` is configured, and
`DbSync` isn't part of its chain at all. `DbSanitize` only runs on the
`snapshot:refresh` producer path — skip that command entirely (call
`snapshot:create`/`snapshot:put` directly) if your snapshot doesn't need
sanitizing.

See `tests/Robo/Task/Testor/PostgresSnapshotTest.php` for a real,
end-to-end proof (real `pg_dump`/`psql`, real Postgres, no mocks) — CI runs it
against a real `postgres:18` service (`.github/workflows/php.yml`). 
