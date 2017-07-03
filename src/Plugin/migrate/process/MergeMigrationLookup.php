<?php

namespace Drupal\up_migrate\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Plugin\migrate\process\MigrationLookup;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\migrate\Row;

/**
 * Lookup a property value based on a migration and merge with existing values.
 *
 * Example:
 * <code>
 * process:
 *   uid:
 *     plugin: up_merge_migration_lookup
 *     migration: up_user_account
 *     source: uid
 *     source_ids:
 *       up_user_account:
 *         - username
 *         - email
 * </code>
 *
 * @MigrateProcessPlugin(
 *   id = "up_merge_migration_lookup"
 * )
 */
class MergeMigrationLookup extends MigrationLookup {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $migration_ids = $this->configuration['migration'];
    if (!is_array($migration_ids)) {
      $migration_ids = [$migration_ids];
    }
    if (!is_array($value)) {
      $value = [$value];
    }
    $this->skipOnEmpty($value);
    $self = FALSE;
    $destination_ids = NULL;
    $source_id_values = [];
    /** @var \Drupal\migrate\Plugin\MigrationInterface[] $migrations */
    $migrations = $this->migrationPluginManager->createInstances($migration_ids);
    foreach ($migrations as $migration_id => $migration) {
      if ($migration_id == $this->migration->id()) {
        $self = TRUE;
      }
      if (isset($this->configuration['source_ids'][$migration_id])) {
        $configuration = ['source' => $this->configuration['source_ids'][$migration_id]];
        $source_id_values[$migration_id] = $this->processPluginManager
          ->createInstance('get', $configuration, $this->migration)
          ->transform(NULL, $migrate_executable, $row, $destination_property);
      }
      else {
        $source_id_values[$migration_id] = $value;
      }
      // Break out of the loop as soon as a destination ID is found.
      if ($destination_ids = $migration->getIdMap()->lookupDestinationId($source_id_values[$migration_id])) {
        $value = reset($destination_ids);
        // Set destination property to first destination id.
        $row->setDestinationProperty($destination_property, $value);
        // Skip further processing of this row, while the mapping is saved.
        throw new MigrateSkipRowException();
      }
    }

    if (!empty($this->configuration['no_stub'])) {
      return NULL;
    }

    if (($self || isset($this->configuration['stub_id']) || count($migrations) == 1)) {
      // If the lookup didn't succeed, figure out which migration will do the
      // stubbing.
      if ($self) {
        $migration = $this->migration;
      }
      elseif (isset($this->configuration['stub_id'])) {
        $migration = $migrations[$this->configuration['stub_id']];
      }
      else {
        $migration = reset($migrations);
      }
      $destination_plugin = $migration->getDestinationPlugin(TRUE);
      // Only keep the process necessary to produce the destination ID.
      $process = $migration->getProcess();
      if (!empty($process[$destination_property])) {
        // Remove process information for the previously set destination since
        // we now don't want to use the migration to lookup the value again.
        unset($process[$destination_property]);
      }

      // We already have the source ID values but need to key them for the Row
      // constructor.
      $source_ids = $migration->getSourcePlugin()->getIds();
      $values = [];
      foreach (array_keys($source_ids) as $index => $source_id) {
        $values[$source_id] = $source_id_values[$migration->id()][$index];
      }

      $stub_row = new Row($values + $migration->getSourceConfiguration(), $source_ids, TRUE);

      // Do a normal migration with the stub row.
      $migrate_executable->processRow($stub_row, $process);
      $destination_ids = [];
      try {
        $destination_ids = $destination_plugin->import($stub_row);
      }
      catch (\Exception $e) {
        $migration->getIdMap()->saveMessage($stub_row->getSourceIdValues(), $e->getMessage());
      }

      if ($destination_ids) {
        $migration->getIdMap()->saveIdMapping($stub_row, $destination_ids, MigrateIdMapInterface::STATUS_NEEDS_UPDATE);
      }
    }
    if ($destination_ids) {
      if (count($destination_ids) == 1) {
        return reset($destination_ids);
      }
      else {
        return $destination_ids;
      }
    }
  }

}
