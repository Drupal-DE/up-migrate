<?php

namespace Drupal\up_migrate\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Config\Entity\ConfigEntityStorageInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides menu links for migration groups.
 */
class MigrationGroupMenuLink extends DeriverBase implements ContainerDeriverInterface {

  /**
   * The config storage.
   *
   * @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface
   */
  protected $configStorage;

  /**
   * Constructs a MigrationGroupMenuLink instance.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityStorageInterface $config_storage
   *   The config storage.
   */
  public function __construct(ConfigEntityStorageInterface $config_storage) {
    $this->configStorage = $config_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('entity.manager')->getStorage('migration_group')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $links = [];

    $groups = $this->configStorage->loadMultiple();
    /* @var $group \Drupal\migrate_plus\Entity\MigrationGroupInterface */
    foreach ($groups as $group) {
      $menu_link_id = "migration_group.{$group->id()}";
      $links[$menu_link_id] = [
        'id' => $menu_link_id,
        'title' => $group->label(),
        'description' => $group->get('description'),
        'route_name' => 'entity.migration.list',
        'route_parameters' => [
          'migration_group' => $group->id(),
        ],
      ] + $base_plugin_definition;
    }

    return $links;
  }

}
