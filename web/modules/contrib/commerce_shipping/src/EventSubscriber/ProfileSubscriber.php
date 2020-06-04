<?php

namespace Drupal\commerce_shipping\EventSubscriber;

use Drupal\commerce_order\Event\OrderProfilesEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProfileSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      'commerce_order.profiles' => ['onProfiles'],
    ];
  }

  /**
   * Adds the shipping profile to the order profiles.
   *
   * The shipping profile is assumed to be the same for all shipments.
   *
   * @param \Drupal\commerce_order\Event\OrderProfilesEvent $event
   *   The order profiles event.
   */
  public function onProfiles(OrderProfilesEvent $event) {
    $order = $event->getOrder();
    if (!$order->hasField('shipments')) {
      return;
    }
    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface[] $shipments */
    $shipments = $order->get('shipments')->referencedEntities();
    $shipment = reset($shipments);
    if ($shipment && $shipment->getShippingProfile()) {
      $event->addProfile('shipping', $shipment->getShippingProfile());
    }
  }

}
