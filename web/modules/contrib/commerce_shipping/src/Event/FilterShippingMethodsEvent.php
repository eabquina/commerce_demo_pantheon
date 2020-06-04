<?php

namespace Drupal\commerce_shipping\Event;

use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * Defines the event for filtering the available shipping methods.
 *
 * @see \Drupal\commerce_shipping\Event\ShippingEvents
 */
class FilterShippingMethodsEvent extends Event {

  /**
   * The shipping methods.
   *
   * @var \Drupal\commerce_shipping\Entity\ShippingMethodInterface[]
   */
  protected $shippingMethods;

  /**
   * The shipment.
   *
   * @var \Drupal\commerce_shipping\Entity\ShipmentInterface
   */
  protected $shipment;

  /**
   * Constructs a new FilterShippingMethodsEvent object.
   *
   * @param \Drupal\commerce_shipping\Entity\ShippingMethodInterface[] $shipping_methods
   *   The shipping methods.
   * @param \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment
   *   The shipment.
   */
  public function __construct(array $shipping_methods, ShipmentInterface $shipment) {
    $this->shippingMethods = $shipping_methods;
    $this->shipment = $shipment;
  }

  /**
   * Gets the shipping methods.
   *
   * @return \Drupal\commerce_shipping\Entity\ShippingMethodInterface[]
   *   The shipping methods.
   */
  public function getShippingMethods() {
    return $this->shippingMethods;
  }

  /**
   * Sets the shipping methods.
   *
   * @param \Drupal\commerce_shipping\Entity\ShippingMethodInterface[] $shipping_methods
   *   The shipping methods.
   *
   * @return $this
   */
  public function setShippingMethods(array $shipping_methods) {
    $this->shippingMethods = $shipping_methods;
    return $this;
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
