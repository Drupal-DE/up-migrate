<?php

namespace Drupal\up_migrate_examples\Plugin\migrate\source;

use Drupal\Core\Database\Query\SelectInterface;
use Drupal\migrate\Row;
use Drupal\up_migrate\Plugin\migrate\source\SqlBase;

/**
 * Sample source plugin for user accounts based on Drupal 7.
 *
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
class UserAccounts extends SqlBase {

  /**
   * {@inheritdoc}
   */
  protected function alterQuery(SelectInterface $query) {
    // Do not import anonymous user.
    $query->condition('u.uid', 0, '>');
  }

  /**
   * {@inheritdoc}
   */
  protected function alterFields(array &$fields = []) {
    // Add fields not defined in yml-file.
    $fields['signature'] = $this->t('Signature');
    $fields['signature_format'] = $this->t('Signature format');
    $fields['created'] = $this->t('Registered timestamp');
    $fields['access'] = $this->t('Last access timestamp');
    $fields['login'] = $this->t('Last login timestamp');
    $fields['status'] = $this->t('Status');
    $fields['timezone'] = $this->t('Timezone');
    $fields['language'] = $this->t('Language');
    $fields['picture'] = $this->t('Picture');
    $fields['init'] = $this->t('Init');
    $fields['data'] = $this->t('User data');
    $fields['roles'] = $this->t('Roles');
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    if (!parent::prepareRow($row)) {
      return FALSE;
    }
    $roles = $this->select('users_roles', 'ur')
      ->fields('ur', ['rid'])
      ->condition('ur.uid', $row->getSourceProperty('uid'))
      ->execute()
      ->fetchCol();
    $row->setSourceProperty('roles', $roles);

    $row->setSourceProperty('data', unserialize($row->getSourceProperty('data')));
    return TRUE;
  }

}
