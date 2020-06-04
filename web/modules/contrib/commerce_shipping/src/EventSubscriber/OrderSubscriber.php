<?php

namespace Drupal\commerce_shipping\EventSubscriber;

use Drupal\commerce_shipping\ShippingOrderManagerInterface;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderSubscriber implements EventSubscriberInterface {

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
      'commerce_order.cancel.post_transition' => ['onCancel'],
      'commerce_order.place.post_transition' => ['onPlace'],
      // @todo Remove onValidate/onFulfill once there is a Shipments admin UI.
      'commerce_order.validate.post_transition' => ['onValidate'],
      'commerce_order.fulfill.post_transition' => ['onFulfill'],
    ];
  }

  /**
   * Cancels the order's shipments when the order is canceled.
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The transition event.
   */
  public function onCancel(WorkflowTransitionEvent $event) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $event->getEntity();
    if (!$this->shippingOrderManager->hasShipments($order)) {
      return;
    }

    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
    foreach ($order->get('shipments')->referencedEntities() as $shipment) {
      $transition = $shipment->getState()->getWorkflow()->getTransition('cancel');
      $shipment->getState()->applyTransition($transition);
      $shipment->save();
    }
  }

  /**
   * Finalizes the order's shipments when the order is placed.
   *
   * Only used if the workflow does not have a validation step.
   * Otherwise the same logic is handled by onValidate().
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The transition event.
   */
  public function onPlace(WorkflowTransitionEvent $event) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $event->getEntity();
    $to_state = $event->getTransition()->getToState();
    if ($to_state->getId() != 'fulfillment' || !$this->shippingOrderManager->hasShipments($order)) {
      return;
    }

    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
    foreach ($order->get('shipments')->referencedEntities() as $shipment) {
      $transition = $shipment->getState()->getWorkflow()->getTransition('finalize');
      $shipment->getState()->applyTransition($transition);
      $shipment->save();
    }
  }

  /**
   * Finalizes the order's shipments when the order is validated.
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The transition event.
   */
  public function onValidate(WorkflowTransitionEvent $event) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $event->getEntity();
    if (!$this->shippingOrderManager->hasShipments($order)) {
      return;
    }

    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
    foreach ($order->get('shipments')->referencedEntities() as $shipment) {
      $transition = $shipment->getState()->getWorkflow()->getTransition('finalize');
      $shipment->getState()->applyTransition($transition);
      $shipment->save();
    }
  }

  /**
   * Ships the order's shipments when the order is fulfilled.
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The transition event.
   */
  public function onFulfill(WorkflowTransitionEvent $event) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $event->getEntity();
    if (!$this->shippingOrderManager->hasShipments($order)) {
      return;
    }

    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
    foreach ($order->get('shipments')->referencedEntities() as $shipment) {
      $transition = $shipment->getState()->getWorkflow()->getTransition('ship');
      $shipment->getState()->applyTransition($transition);
      $shipment->save();
    }
  }

}
