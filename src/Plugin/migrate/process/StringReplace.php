<?php

namespace Drupal\up_migrate\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * This plugin allows string replacements in the source value.
 *
 * Example:
 * <code>
 * process:
 *   file_destination:
 *     plugin: up_string_replace
 *     # Remove unused parts in path.
 *     'field/image': ''
 *     # Move all files into sub-directory.
 *     'public://': 'public://imported'
 * </code>
 *
 * @MigrateProcessPlugin(
 *   id = "up_string_replace"
 * )
 */
class StringReplace extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $replacements = $this->configuration['replacements'] ?: [];
    if (!is_array($replacements)) {
      $replacements = [$replacements];
    }
    return strtr($value, $replacements);
  }

}
