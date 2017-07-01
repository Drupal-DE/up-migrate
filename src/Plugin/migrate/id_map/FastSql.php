<?php

namespace Drupal\up_migrate\Plugin\migrate\id_map;

use Drupal\migrate\Plugin\migrate\id_map\Sql;

/**
 * Defines a faster SQL based ID map implementation.
 *
 * Creates unique keys over source columns so joins and lookups are much faster.
 *
 * @PluginID("fastsql")
 */
class FastSql extends Sql {

  /**
   * {@inheritdoc}
   */
  protected function ensureTables() {
    parent::ensureTables();

    if ($this->getDatabase()->schema()->indexExists($this->mapTableName, 'source')) {
      return;
    }
    // Add unique index over all source columns.
    $count = 1;
    $string_fields = 0;
    $unique_fields = [];
    foreach ($this->migration->getSourcePlugin()->getIds() as $id_definition) {
      $unique_fields[] = 'sourceid' . $count++;
      if (isset($id_definition['type']) && ('string' === $id_definition['type'])) {
        $string_fields++;
      }
    }

    if ($string_fields > 2) {
      // Do not create unique index over more than 2 string fields. Otherwise
      // there is a great chance the key is too long (>767 bytes).
      return;
    }

    $this->getDatabase()->schema()->addUniqueKey($this->mapTableName, 'source', $unique_fields);
  }

}
