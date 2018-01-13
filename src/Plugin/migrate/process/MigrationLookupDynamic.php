<?php

namespace Drupal\up_migrate\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Plugin\migrate\process\MigrationLookup;
use Drupal\migrate\Row;

/**
 * Looks up the value of a property based on a previous migration.
 *
 * In contrast to the default migration_lookup, the migration can be dynamic
 * based on a previous destination value.
 *
 * Examples:
 *
 * @code
 * process:
 *   migration_name:
 *     plugin: static_map
 *     source: delta
 *     map:
 *       0: users
 *       1: accounts
 *   uid:
 *     plugin: migration_lookup
 *     migration: '@migration_name'
 *     source: author
 * @endcode
 *
 * This switches migrations based on the source property "delta".
 *
 * @MigrateProcessPlugin(
 *   id = "up_migration_lookup_dynamic"
 * )
 */
class MigrationLookupDynamic extends MigrationLookup {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $migration_ids = $this->configuration['migration_name'];
    if (!is_array($migration_ids)) {
      $migration_ids = [$migration_ids];
    }
    // Translate migration IDs if necessary.
    foreach ($migration_ids as $key => $migration_id) {
      $is_source = TRUE;
      if ('@' === $migration_id[0]) {
        $migration_id = preg_replace_callback('/^(@?)((?:@@)*)([^@]|$)/', function ($matches) use (&$is_source) {
          // If there are an odd number of @ in the beginning, it's a
          // destination.
          $is_source = empty($matches[1]);
          // Remove the possible escaping and do not lose the terminating
          // non-@ either.
          return str_replace('@@', '@', $matches[2]) . $matches[3];
        }, $migration_id);
      }
      if ($is_source) {
        $migration_ids[$key] = $row->getSourceProperty($migration_id);
      }
      else {
        $migration_ids[$key] = $row->getDestinationProperty($migration_id);
      }
    }

    $this->configuration['migration'] = $migration_ids;
    return parent::transform($value, $migrate_executable, $row, $destination_property);
  }

}
