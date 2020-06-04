<?php

namespace Drupal\commerce_shipping\EventSubscriber;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Event\OrderItemEvent;
use Drupal\commerce_shipping\ShippingOrderManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderItemSubscriber implements EventSubscriberInterface {

  /**
   * The shipping order manager.
   *
   * @var \Drupal\commerce_shipping\ShippingOrderManagerInterface
   */
  protected $shippingOrderManager;

  /**
   * Constructs a new OrderSubscriber object.
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
      'commerce_order.commerce_order_item.update' => ['onOrderItemUpdate'],
      'commerce_order.commerce_order_item.delete' => ['onOrderItemDelete'],
    ];
  }

  /**
   * Force repack/rates recalculation when quantity is updated.
   *
   * @param \Drupal\commerce_order\Event\OrderItemEvent $order_item_event
   *   Order item event.
   */
  public function onOrderItemUpdate(OrderItemEvent $order_item_event) {
    $order_item = $order_item_event->getOrderItem();
    $order = $order_item->getOrder();
    if (!$order || !$this->shouldRefresh($order)) {
      return;
    }
    if ($order_item->getQuantity() !== $order_item->original->getQuantity()) {
      $order->setData(ShippingOrderManagerInterface::FORCE_REFRESH, TRUE);
    }
  }

  /**
   * Force repack/rates recalculation when an order item is removed.
   *
   * @param \Drupal\commerce_order\Event\OrderItemEvent $order_item_event
   *   Order item event.
   */
  public function onOrderItemDelete(OrderItemEvent $order_item_event) {
    $order_item = $order_item_event->getOrderItem();
    $order = $order_item->getOrder();
    if (!$order || !$this->shouldRefresh($order)) {
      return;
    }
    $order->setData(ShippingOrderManagerInterface::FORCE_REFRESH, TRUE);
  }

  /**
   * Checks whether we should force a shipping refresh.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   Order entity.
   *
   * @return bool
   *   Whether we should force a shipping refresh.
   */
  protected function shouldRefresh(OrderInterface $order) {
    return $order->getState()->getId() == 'draft' && $this->shippingOrderManager->hasShipments($order);
  }

}
