<?php

namespace Drupal\commerce_shipping;

use Drupal\commerce_shipping\Controller\ShipmentController;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\entity\Routing\AdminHtmlRouteProvider;

/**
 * Provides routes for the Shipment entity.
 */
class ShipmentRouteProvider extends AdminHtmlRouteProvider {

  /**
   * {@inheritdoc}
   */
  protected function getAddFormRoute(EntityTypeInterface $entity_type) {
    $route = parent::getAddFormRoute($entity_type);
    if ($route) {
      $route->setOption('parameters', [
        'commerce_order' => [
          'type' => 'entity:commerce_order',
        ],
        'commerce_shipment_type' => [
          'type' => 'entity:commerce_shipment_type',
        ],
      ]);
    }
    return $route;
  }

  /**
   * {@inheritdoc}
   */
  protected function getAddPageRoute(EntityTypeInterface $entity_type) {
    $route = parent::getAddPageRoute($entity_type);
    if ($route) {
      $route->setDefault('_controller', ShipmentController::class . '::addShipmentPage');
      $route->setOption('parameters', [
        'commerce_order' => [
          'type' => 'entity:commerce_order',
        ],
      ]);
    }
    return $route;
  }

  /**
   * {@inheritdoc}
   */
  protected function getCollectionRoute(EntityTypeInterface $entity_type) {
    $route = parent::getCollectionRoute($entity_type);
    if ($route) {
      $route->setOption('parameters', [
        'commerce_order' => [
          'type' => 'entity:commerce_order',
        ],
      ]);
      $route->setRequirement('_shipment_collection_access', 'TRUE');
    }
    return $route;
  }

}
