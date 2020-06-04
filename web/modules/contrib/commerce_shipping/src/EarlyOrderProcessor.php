<?php

namespace Drupal\commerce_shipping;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\OrderProcessorInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Prepares shipments for the order refresh process.
 *
 * Runs before other order processors (promotion, tax, etc).
 * Packs the shipments, resets their amounts and adjustments.
 *
 * Once the other order processors perform their changes, the
 * LateOrderProcessor transfers the shipment adjustments to the order.
 *
 * @see \Drupal\commerce_shipping\LateOrderProcessor
 */
class EarlyOrderProcessor implements OrderProcessorInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The shipping order manager.
   *
   * @var \Drupal\commerce_shipping\ShippingOrderManagerInterface
   */
  protected $shippingOrderManager;

  /**
   * The shipment manager.
   *
   * @var \Drupal\commerce_shipping\ShipmentManagerInterface
   */
  protected $shipmentManager;

  /**
   * Constructs a new EarlyOrderProcessor object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\commerce_shipping\ShippingOrderManagerInterface $shipping_order_manager
   *   The shipping order manager.
   * @param \Drupal\commerce_shipping\ShipmentManagerInterface $shipment_manager
   *   The shipment manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ShippingOrderManagerInterface $shipping_order_manager, ShipmentManagerInterface $shipment_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->shippingOrderManager = $shipping_order_manager;
    $this->shipmentManager = $shipment_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function process(OrderInterface $order) {
    if (!$this->shippingOrderManager->hasShipments($order)) {
      return;
    }
    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface[] $shipments */
    $shipments = $order->get('shipments')->referencedEntities();
    if ($shipments && $this->shouldRepack($order, $shipments)) {
      $shipping_profile = $this->shippingOrderManager->getProfile($order);
      // If the shipping profile does not exist, delete all shipments.
      if (!$shipping_profile) {
        $shipment_storage = $this->entityTypeManager->getStorage('commerce_shipment');
        $shipment_storage->delete($shipments);
        return;
      }
      $shipments = $this->shippingOrderManager->pack($order, $shipping_profile);
    }

    $should_refresh = $this->shouldRefresh($order);
    foreach ($shipments as $key => $shipment) {
      if ($original_amount = $shipment->getOriginalAmount()) {
        $shipment->setAmount($original_amount);
      }
      $shipment->clearAdjustments();

      if (!$should_refresh) {
        continue;
      }
      $rates = $this->shipmentManager->calculateRates($shipment);

      // There is no rates for shipping. "clear" the rate...
      // Note that we don't remove the shipment to prevent data loss (we're
      // mainly interested in preserving the shipping profile).
      if (empty($rates)) {
        $shipment->clearRate();
        continue;
      }
      $rate = $this->shipmentManager->selectDefaultRate($shipment, $rates);
      $this->shipmentManager->applyRate($shipment, $rate);
    }
    // Unset flag before returning updated shipments.
    if ($should_refresh) {
      $order->unsetData(ShippingOrderManagerInterface::FORCE_REFRESH);
    }

    $order->set('shipments', $shipments);
  }

  /**
   * Determines whether the given order's shipments should be repacked.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   * @param \Drupal\commerce_shipping\Entity\ShipmentInterface[] $shipments
   *   The shipments.
   *
   * @return bool
   *   TRUE if the order should be repacked, FALSE otherwise.
   */
  protected function shouldRepack(OrderInterface $order, array $shipments) {
    // Skip repacking if there's at least one shipment that was created outside
    // of the packing process (via the admin UI, for example).
    foreach ($shipments as $shipment) {
      if (!$shipment->getData('owned_by_packer')) {
        return FALSE;
      }
    }

    // Flag used for force repacking shipments and possible recalculation
    // of rates.
    if ($this->shouldRefresh($order)) {
      return TRUE;
    }

    // Ideally repacking would happen only if the order items changed.
    // However, it is not possible to detect order item quantity changes,
    // because the order items are saved before the order itself.
    // Therefore, repacking runs on every refresh, but as a minimal
    // optimization, this processor ignores refreshes caused by moving
    // through checkout, unless an order item was added/removed along the way.
    if (isset($order->original) && $order->hasField('checkout_step')) {
      $previous_step = $order->original->get('checkout_step')->value;
      $current_step = $order->get('checkout_step')->value;
      $previous_order_item_ids = array_map(function ($value) {
        return $value['target_id'];
      }, $order->original->get('order_items')->getValue());
      $current_order_item_ids = array_map(function ($value) {
        return $value['target_id'];
      }, $order->get('order_items')->getValue());
      if ($previous_step != $current_step && $previous_order_item_ids == $current_order_item_ids) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Determines whether the order needs to be repacked and/or whether the
   * shipping rates should be recalculated.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   *
   * @return bool
   *   TRUE if it should refresh, FALSE otherwise.
   */
  protected function shouldRefresh(OrderInterface $order) {
    return (bool) $order->getData(ShippingOrderManagerInterface::FORCE_REFRESH, FALSE);
  }

}
