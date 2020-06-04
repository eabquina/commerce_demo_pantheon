<?php

namespace Drupal\commerce_shipping\Event;

use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\Entity\ShippingMethodInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * Defines the event for reacting to shipping rate calculation.
 *
 * @see \Drupal\commerce_shipping\Event\ShippingEvents
 */
class ShippingRatesEvent extends Event {

  /**
   * The shipping rates.
   *
   * @var \Drupal\commerce_shipping\ShippingRate[]
   */
  protected $rates;

  /**
   * The shipping method.
   *
   * @var \Drupal\commerce_shipping\Entity\ShippingMethodInterface
   */
  protected $shippingMethod;

  /**
   * The shipment.
   *
   * @var \Drupal\commerce_shipping\Entity\ShipmentInterface
   */
  protected $shipment;

  /**
   * Constructs a new ShippingRatesEvent.
   *
   * @param \Drupal\commerce_shipping\ShippingRate[] $rates
   *   The shipping rates.
   * @param \Drupal\commerce_shipping\Entity\ShippingMethodInterface $shipping_method
   *   The shipping method calculating the rates.
   * @param \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment
   *   The shipment.
   */
  public function __construct(array $rates, ShippingMethodInterface $shipping_method, ShipmentInterface $shipment) {
    $this->rates = $rates;
    $this->shippingMethod = $shipping_method;
    $this->shipment = $shipment;
  }

  /**
   * Gets the shipping rates.
   *
   * @return \Drupal\commerce_shipping\ShippingRate[]
   *   The shipping rates.
   */
  public function getRates() {
    return $this->rates;
  }

  /**
   * Sets the shipping rates.
   *
   * @param \Drupal\commerce_shipping\ShippingRate[] $rates
   *   The shipping rates.
   */
  public function setRates(array $rates) {
    $this->rates = $rates;
  }

  /**
   * Gets the shipping method.
   *
   * @return \Drupal\commerce_shipping\Entity\ShippingMethodInterface
   *   The shipping method.
   */
  public function getShippingMethod() {
    return $this->shippingMethod;
  }

  /**
   * Gets the shipment.
   *
   * @return \Drupal\commerce_shipping\Entity\ShipmentInterface
   *   The shipment.
   */
  public function getShipment() {
    return $this->shipment;
  }

}
