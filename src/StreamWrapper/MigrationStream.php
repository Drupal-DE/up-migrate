<?php

namespace Drupal\up_migrate\StreamWrapper;

use Drupal\Core\Site\Settings;
use Drupal\Core\StreamWrapper\LocalReadOnlyStream;

/**
 * Simple read-only stream wrapper for migration files.
 */
class MigrationStream extends LocalReadOnlyStream {

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return t('Migration source files');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return t('Migration files');
  }

  /**
   * {@inheritdoc}
   */
  public function getDirectoryPath() {
    return static::basePath();
  }

  /**
   * {@inheritdoc}
   */
  public function getExternalUrl() {
    throw new \LogicException('Migration file URLs are not meant to be public.');
  }

  /**
   * Returns the base path for migration://.
   *
   * @return string
   *   The base path for migration://.
   */
  public static function basePath() {
    return Settings::get('file_migration_source_path');
  }

}
