<?php

namespace Drupal\up_migrate\Plugin\migrate\source;

use Drupal\Core\Database\Database;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\State\StateInterface;
use Drupal\migrate\MigrateException;
use Drupal\migrate\Plugin\migrate\source\SqlBase as CoreSqlBase;
use Drupal\migrate\Plugin\MigrationInterface;

/**
 * Base SQL source for migrations.
 */
abstract class SqlBase extends CoreSqlBase {

  /**
   * Current site state.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Information about the base table.
   *
   * @var array
   */
  protected $table = [];

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration, StateInterface $state) {
    $this->state = $state;

    // Initialize databases.
    $this->initDatabases();

    if (empty($configuration['key']) && empty($configuration['target']) && empty($configuration['database_state_key'])) {
      // Use first database as default.
      $configuration['key'] = reset(array_keys($this->state->get('up_migrate.databases', [])));
    }

    // Now we can safely call the parent constructor.
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration, $state);

    if (($this->table = $this->getConfig('table')) === NULL || empty($this->table['name'])) {
      // No table defined as source for user data.
      throw new MigrateException('No source table defined. Set this in the migration yml file or in the source plugin.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select($this->getTableName(), $this->getTableAlias())
      ->fields($this->getTableAlias());

    // Extending classes should alter the query.
    $this->alterQuery($query);

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    // Define base fields for user accounts.
    $fields = [];

    // Add required fields from extending classes.
    $this->alterFields($fields);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    if (empty($this->table['ids'])) {
      throw new MigrateException('No source table ids defined. Set this in the migration yml file or in the source plugin or override the function getIds() in the source plugin.');
    }
    $ids = [];
    foreach ($this->table['ids'] as $key => $type) {
      $ids[$key] = [
        'type' => $type,
        'alias' => $this->getTableAlias(),
      ];
    }
    return $ids;
  }

  /**
   * Alter the base query.
   *
   * @param \Drupal\Core\Database\Query\SelectInterface $query
   *   The query to alter.
   */
  protected function alterQuery(SelectInterface $query) {
    // Give extending classes the possibility to alter the source query.
  }

  /**
   * Alter the list of available fields.
   *
   * @param array $fields
   *   List of fields available for migration.
   */
  protected function alterFields(array $fields = []) {
    // Give extending classes the possibility to alter or add fields.
  }

  /**
   * Get configuration values for the current migration.
   *
   * Note: Configuration made in the source plugin definition may be overriden
   *   by the migration itself.
   *
   * @param string|null $name
   *   The configuration value or NULL if there is no configuration with that
   *   name.
   * @param mixed $default
   *   Default value.
   *
   * @return mixed
   *   The configuration value.
   */
  public function getConfig($name, $default = NULL) {
    $plugin_definition = $this->pluginDefinition;
    $source_configuration = $this->migration->getSourceConfiguration();

    $value = $default;
    if (isset($plugin_definition[$name])) {
      // Empty value is allowed.
      $value = $plugin_definition[$name];
    }
    if (isset($source_configuration[$name])) {
      // Empty value is allowed.
      $value = is_array($value) ? array_merge($value, $source_configuration[$name]) : $source_configuration[$name];
    }

    return $value;
  }

  /**
   * Initialize databases used as migration sources.
   */
  protected function initDatabases() {
    if (($databases = $this->state->get('up_migrate.databases')) === NULL) {
      throw new MigrateException('No source databases defined. Run `drush upm-database-add` to add at a database.');
    }
    foreach ($databases as $key => $info) {
      // No need to check if the connection has been added before, since
      // addConnectionInfo() does this.
      Database::addConnectionInfo($key, 'default', $this->databaseConfigAddDefaults($info));
    }
  }

  /**
   * Helper function to expand database configuration with default values.
   *
   * @param array $config
   *   The given database configuration.
   *
   * @return array
   *   Full database configuration array.
   */
  protected function databaseConfigAddDefaults(array $config) {
    // Add default configuration.
    return array_filter($config) + [
      'host' => 'localhost',
      'port' => '3306',
      'username' => '',
      'password' => '',
      'driver' => 'mysql',
      'namespace' => 'Drupal\Core\Database\Driver\mysql',
      'init_commands' => [
        // Use custom sql_mode so we disable "ONLY_FULL_GROUP_BY".
        'sql_mode' => "SET sql_mode = 'ANSI,STRICT_TRANS_TABLES,STRICT_ALL_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER'",
      ],
    ];
  }

  /**
   * Get the base table name for the migration query.
   *
   * @return string
   *   Name of base table.
   */
  protected function getTableName() {
    return $this->table['name'];
  }

  /**
   * Get the base table alias for the migration query.
   *
   * @return string
   *   Alias of base table.
   */
  protected function getTableAlias() {
    return empty($this->table['alias']) ? $this->table['name'] : $this->table['alias'];
  }

}
