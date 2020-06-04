<?php

namespace Drupal\commerce_shipping;

use Drupal\commerce_shipping\Entity\ShipmentInterface;

/**
 * Manages shipments.
 */
interface ShipmentManagerInterface {

  /**
   * Applies the given shipping rate to the given shipment.
   *
   * @param \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment
   *   The shipment.
   * @param \Drupal\commerce_shipping\ShippingRate $rate
   *   The shipping rate.
   */
  public function applyRate(ShipmentInterface $shipment, ShippingRate $rate);

  /**
   * Calculates rates for the given shipment.
   *
   * @param \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment
   *   The shipment.
   *
   * @return \Drupal\commerce_shipping\ShippingRate[]
   *   The rates.
   */
  public function calculateRates(ShipmentInterface $shipment);

  /**
   * Selects the default rate for the given shipment.
   *
   * @param \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment
   *   The shipment.
   * @param \Drupal\commerce_shipping\ShippingRate[] $rates
   *   The available rates.
   *
   * @return \Drupal\commerce_shipping\ShippingRate
   *   The selected rate.
   */
  public function selectDefaultRate(ShipmentInterface $shipment, array $rates);

}
