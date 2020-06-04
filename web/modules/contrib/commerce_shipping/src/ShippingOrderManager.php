<?php

namespace Drupal\commerce_shipping;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\profile\Entity\ProfileInterface;

class ShippingOrderManager implements ShippingOrderManagerInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * The packer manager.
   *
   * @var \Drupal\commerce_shipping\PackerManagerInterface
   */
  protected $packerManager;

  /**
   * Constructs a new ShippingOrderManager object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info service.
   * @param \Drupal\commerce_shipping\PackerManagerInterface $packer_manager
   *   The packer manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info, PackerManagerInterface $packer_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->packerManager = $packer_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function createProfile(OrderInterface $order, array $values = []) {
    $values += [
      'type' => 'customer',
      'uid' => 0,
    ];
    // Check whether the order type has another profile type ID specified.
    $order_type_id = $order->bundle();
    $order_bundle_info = $this->entityTypeBundleInfo->getBundleInfo('commerce_order');
    if (!empty($order_bundle_info[$order_type_id]['shipping_profile_type'])) {
      $values['type'] = $order_bundle_info[$order_type_id]['shipping_profile_type'];
    }
    /** @var \Drupal\profile\ProfileStorageInterface $profile_storage */
    $profile_storage = $this->entityTypeManager->getStorage('profile');

    return $profile_storage->create($values);
  }

  /**
   * {@inheritdoc}
   */
  public function getProfile(OrderInterface $order) {
    $profiles = $order->collectProfiles();
    return isset($profiles['shipping']) ? $profiles['shipping'] : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function hasShipments(OrderInterface $order) {
    return $order->hasField('shipments') && !$order->get('shipments')->isEmpty();
  }

  /**
   * {@inheritdoc}
   */
  public function isShippable(OrderInterface $order) {
    if (!$order->hasField('shipments')) {
      return FALSE;
    }

    // The order must contain at least one shippable purchasable entity.
    foreach ($order->getItems() as $order_item) {
      $purchased_entity = $order_item->getPurchasedEntity();
      if ($purchased_entity && $purchased_entity->hasField('weight')) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function pack(OrderInterface $order, ProfileInterface $profile = NULL) {
    if (!$profile) {
      $profile = $this->getProfile($order) ?: $this->createProfile($order);
    }
    $shipments = $order->get('shipments')->referencedEntities();
    list($shipments, $removed_shipments) = $this->packerManager->packToShipments($order, $profile, $shipments);
    // Delete any shipments that are no longer used.
    if (!empty($removed_shipments)) {
      $shipment_storage = $this->entityTypeManager->getStorage('commerce_shipment');
      $shipment_storage->delete($removed_shipments);
    }

    return $shipments;
  }

}
