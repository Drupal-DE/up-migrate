# undpaul Migrate

This project aims to give you a jump-start on building migrations.

## Installation

Clone the project into `modules/custom` of your Drupal installation and run
`composer install` within the modules directory.

### Adding additional databases as migration source

`drush upm-da {key} {database name} --username={name} --password={password}`

## Usage

_up_migrate_ comes with some base classes usefull for building migrations.

### ID maps

#### FastSql

Since the mapping tables create by _Migrate_ do not have proper unique indexes,
the ID map "fastsql" alters the definitions of the map tables and add proper
indexes. This increases migration performance significant.
_up_migrate_ sets the ID map for **all** migrations if they do not specify the
property `idMap` for themself.

### Source plugins

_up_migrate_ comes with a set of pre-defined source plugins.

#### SqlBase

The abstract class `\Drupal\up_migrate\Plugin\migrate\source\SqlBase` is meant
to be the parent class for all custom migration sources. It allows configuring
sources from directly within a migration yml file.

Example for definition in _migrate_plus.migration.{id}.yml_:

    source:
      table:
        name: users
        alias: u
        ids:
          uid: integer

This sets the base table of the migration source to a table named "users". The
table can be referenced by using the alias "u" and have one unique key named
"uid".

Example for definition in custom source plugin (must extend `SqlBase`)

    /**
     * @MigrateSource(
     *   id = "upm_examples__user",
     *   table = {
     *     "name": "users",
     *     "alias": "u",
     *     "ids": {
     *       "uid": "integer"
     *     }
     *   }
     * )
     */

For more complex ID definitions simply override the function `getIds()`.
