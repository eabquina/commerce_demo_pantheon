<?php

namespace Drupal\commerce_shipping\EventSubscriber;

use Drupal\commerce_cart\Event\CartEmptyEvent;
use Drupal\commerce_cart\Event\CartEntityAddEvent;
use Drupal\commerce_cart\Event\CartEvents;
use Drupal\commerce_shipping\ShippingOrderManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CartSubscriber implements EventSubscriberInterface {

  /**
   * The shipping order manager.
   *
   * @var \Drupal\commerce_shipping\ShippingOrderManagerInterface
   */
  protected $shippingOrderManager;

  /**
   * Constructs a new CartSubscriber object.
   *
   * @param \Drupal\commerce_shipping\ShippingOrderManagerInterface $shipping_order_manager
   *   The shipping order manager.
   */
  public function __construct(ShippingOrderManagerInterface $shipping_order_manager) {
    $this->shippingOrderManager = $shipping_order_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      CartEvents::CART_EMPTY => 'onCartEmpty',
      CartEvents::CART_ENTITY_ADD => ['onCartEntityAdd', -100],
    ];
  }

  /**
   * Remove shipments from an emptied cart.
   *
   * The order refresh does not process orders which have no order items,
   * preventing the shipping order processor from removing shipments.
   *
   * @todo Re-evaluate after #3062594 is fixed.
   *
   * @param \Drupal\commerce_cart\Event\CartEmptyEvent $event
   *   The event.
   */
  public function onCartEmpty(CartEmptyEvent $event) {
    $cart = $event->getCart();
    if (!$this->shippingOrderManager->hasShipments($cart)) {
      return;
    }

    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface[] $shipments */
    $shipments = $cart->get('shipments')->referencedEntities();
    foreach ($shipments as $shipment) {
      $shipment->delete();
    }
    $cart->set('shipments', []);
  }

  /**
   * Force repack/rates recalculation when an order item is added to the cart.
   *
   * @param \Drupal\commerce_cart\Event\CartEntityAddEvent $event
   *   The cart event.
   */
  public function onCartEntityAdd(CartEntityAddEvent $event) {
    $cart = $event->getCart();
    if ($this->shippingOrderManager->hasShipments($cart)) {
      $cart->setData(ShippingOrderManagerInterface::FORCE_REFRESH, TRUE);
    }
  }

}
