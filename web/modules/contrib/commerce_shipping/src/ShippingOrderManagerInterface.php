<?php

namespace Drupal\commerce_shipping;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\profile\Entity\ProfileInterface;

interface ShippingOrderManagerInterface {

  /**
   * Data key used to flag an order's shipments for repacking and calculation.
   */
  const FORCE_REFRESH = 'shipping_force_refresh';

  /**
   * Creates a shipping profile for the given order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   * @param array $values
   *   (optional) An array of field values to set on the profile.
   *
   * @return \Drupal\profile\Entity\ProfileInterface
   *   A shipping profile.
   */
  public function createProfile(OrderInterface $order, array $values = []);

  /**
   * Gets the shipping profile for the given order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   *
   * @return \Drupal\profile\Entity\ProfileInterface|null
   *   The shipping profile, or NULL if none found.
   */
  public function getProfile(OrderInterface $order);

  /**
   * Checks if the given order has shipments.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   *
   * @return bool
   *   TRUE if the order has shipments, FALSE otherwise.
   */
  public function hasShipments(OrderInterface $order);

  /**
   * Determines whether the order is shippable.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   *
   * @return bool
   *   TRUE if the order is shippable, FALSE otherwise.
   */
  public function isShippable(OrderInterface $order);

  /**
   * Packs the given order into shipments.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   * @param \Drupal\profile\Entity\ProfileInterface $profile
   *   The shipping profile.
   *
   * @return \Drupal\commerce_shipping\\Entity\ShipmentInterface[]
   *   The unsaved shipments.
   */
  public function pack(OrderInterface $order, ProfileInterface $profile = NULL);

}
