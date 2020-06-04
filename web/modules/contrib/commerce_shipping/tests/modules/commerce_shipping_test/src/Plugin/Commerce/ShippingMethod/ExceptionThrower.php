<?php

namespace Drupal\commerce_shipping_test\Plugin\Commerce\ShippingMethod;

use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\Plugin\Commerce\ShippingMethod\ShippingMethodBase;

/**
 * Provides a shipping method that throws an exception when calculating rates.
 *
 * @CommerceShippingMethod(
 *   id = "exception_thrower",
 *   label = @Translation("Exception Thrower"),
 * )
 */
class ExceptionThrower extends ShippingMethodBase {

  /**
   * {@inheritdoc}
   */
  public function calculateRates(ShipmentInterface $shipment) {
    throw new \Exception('Simulate a shipping plugin that throws an exception when calculating rates.');
  }

}
