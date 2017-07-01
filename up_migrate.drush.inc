<?php

/**
 * @file
 * Command-line tools for "undpaul Migrate".
 */

use Drupal\Core\Config\ConfigInstaller;
use Drupal\Core\Config\FileStorage;
use Drupal\Core\Config\InstallStorage;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Site\Settings;
use Drush\Log\LogLevel;

/**
 * Implements hook_drush_command().
 */
function up_migrate_drush_command() {
  // Refresh default configuration of a module located in config/install.
  $items['config-refresh'] = [
    'description' => 'Refresh default configuration of an extension without the need to reinstall.',
    'core' => ['8+'],
    'aliases' => ['cf5'],
    'arguments' => [
      'module' => 'The name of the extension needing a configuration refresh.',
      'type' => "The type of the extension (one of 'module', 'theme', 'profile'). Defaults to 'module'.",
    ],
    'examples' => [
      'drush config-refresh mymodule' => "Refresh default configuration of a module named 'mymodule'.",
      'drush config-refresh myprofile profile' => "Refresh default configuration of a profile named 'myprofile'.",
    ],
  ];

  // Add migration source database.
  $items['upm-database-add'] = [
    'description' => 'Add a database as migration source',
    'arguments' => [
      'key' => 'Unique database key. This is used in migrations to switch to a different database',
      'database' => 'Name of database to add',
    ],
    'options' => [
      'username' => 'Username for database connection',
      'password' => 'Password for database connection',
      'host' => 'Host of database server (defaults to localhost)',
      'port' => 'Port of database server (defaults to 3306)',
    ],
    'examples' => [
      'upm-database-add phpbb forum_old' => 'Add database "forum_old" with key "phpbb"',
    ],
    'drupal dependencies' => ['up_migrate'],
    'aliases' => ['upm-da'],
  ];

  return $items;
}

/**
 * Implements hook_drush_command_alter().
 *
 * If any other module already defined "config-refresh" we override it here.
 */
function up_migrate_drush_command_alter(&$command) {
  if (!in_array($command['command'], ['config-refresh'])) {
    // Command is not defined yet.
    return;
  }
  // Replace previously declared command "config-refresh".
  $command['commandfile'] = 'up_migrate';
  switch ($command['command']) {
    case 'config-refresh':
      $command['command'] = 'up-migrate-config-refresh';
      $command['command-hook'] = 'up-migrate-config-refresh';
      break;
  }
  // Strange, but necessary.
  drush_set_context('command', $command);
}

/**
 * Config refresh command callback.
 *
 * @param string $name
 *   The extension name.
 * @param string $type
 *   (optional) The extension type.
 *
 * @see ConfigInstaller::installDefaultConfig()
 */
function drush_up_migrate_config_refresh($name, $type = 'module') {
  if (!in_array($type, ['module', 'theme', 'profile'])) {
    $type = 'module';
  }
  $config_installer = Drupal::service('config.installer');
  // Find default configuration of the extension.
  $default_install_path = drupal_get_path($type, $name) . '/' . InstallStorage::CONFIG_INSTALL_DIRECTORY;
  if (is_dir($default_install_path)) {
    if (!$config_installer->isSyncing()) {
      $storage = new FileStorage($default_install_path, StorageInterface::DEFAULT_COLLECTION);
      $prefix = '';
    }
    else {
      $storage = $config_installer->getSourceStorage();
      $prefix = $name . '.';
    }

    // Gets profile storages to search for overrides if necessary.
    $profile = Settings::get('install_profile');
    $profile_storages = [];
    if ($profile && $profile != $name) {
      $profile_path = drupal_get_path('module', $profile);
      foreach ([InstallStorage::CONFIG_INSTALL_DIRECTORY, InstallStorage::CONFIG_OPTIONAL_DIRECTORY] as $directory) {
        if (is_dir($profile_path . '/' . $directory)) {
          $profile_storages[] = new FileStorage($profile_path . '/' . $directory, StorageInterface::DEFAULT_COLLECTION);
        }
      }
    }

    $config_factory = Drupal::service('config.factory');
    $collection_info = Drupal::service('config.manager')->getConfigCollectionInfo();
    foreach ($collection_info->getCollectionNames() as $collection) {
      $config_to_refresh = _up_migrate_config_refresh_get_config($storage, $collection, $prefix, $profile_storages);
      // Remove existing configuration.
      foreach (array_keys($config_to_refresh) as $config_name) {
        $config_factory->getEditable($config_name)->delete();
      }
    }

    // Re-install default config.
    $config_installer->installDefaultConfig($type, $name);
    Drupal::service('router.builder')->setRebuildNeeded();
    if ('theme' === $type) {
      Drupal::moduleHandler()->invokeAll('themes_installed', [[$name]]);
    }
    else {
      Drupal::moduleHandler()->invokeAll('modules_installed', [[$name]]);
    }

    drush_log(sprintf('Default configuration refreshed for %s "%s".', $type, $name), LogLevel::OK);
  }
}

/**
 * Gets configuration data from the provided storage.
 *
 * @param StorageInterface $storage
 *   The configuration storage to read configuration from.
 * @param string $collection
 *   The configuration collection to use.
 * @param string $prefix
 *   (optional) Limit to configuration starting with the provided string.
 * @param StorageInterface[] $profile_storages
 *   An array of storage interfaces containing profile configuration to check
 *   for overrides.
 *
 * @return array
 *   An array of configuration data read from the source storage keyed by the
 *   configuration object name.
 */
function _up_migrate_config_refresh_get_config(StorageInterface $storage, $collection, $prefix = '', array $profile_storages = []) {
  if ($storage->getCollectionName() != $collection) {
    $storage = $storage->createCollection($collection);
  }
  $data = $storage->readMultiple($storage->listAll($prefix));

  // Check to see if the corresponding override storage has any overrides.
  foreach ($profile_storages as $profile_storage) {
    if ($profile_storage->getCollectionName() != $collection) {
      $profile_storage = $profile_storage->createCollection($collection);
    }
    $data = $profile_storage->readMultiple(array_keys($data)) + $data;
  }
  return $data;
}

/**
 * Drush callback to add a database as migration source.
 *
 * @param string $key
 *   Database key to use in migrations.
 * @param string $database
 *   Name of database to add.
 */
function drush_up_migrate_upm_database_add($key, $database) {
  $state = \Drupal::state();
  // Load existing databases.
  $databases = $state->get('up_migrate.databases', []);
  // Create new database information.
  $databases[$key] = [
    'database' => $database,
  ];
  foreach (['username', 'password', 'host', 'port'] as $option) {
    if (drush_get_option($option)) {
      $databases[$key][$option] = drush_get_option($option);
    }
  }
  $state->set('up_migrate.databases', $databases);
}
