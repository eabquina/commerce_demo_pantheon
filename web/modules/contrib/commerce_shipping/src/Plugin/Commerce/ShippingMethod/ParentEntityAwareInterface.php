<?php

namespace Drupal\commerce_shipping\Plugin\Commerce\ShippingMethod;

use Drupal\Core\Entity\EntityInterface;

/**
 * Defines the interface for shipping methods that depend on the parent entity.
 *
 * @todo Replace with the generic ParentEntityAwareInterface from Commerce,
 *       once there is one.
 */
interface ParentEntityAwareInterface {

  /**
   * Sets the parent entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $parent_entity
   *   The parent entity.
   *
   * @return $this
   */
  public function setParentEntity(EntityInterface $parent_entity);

}
