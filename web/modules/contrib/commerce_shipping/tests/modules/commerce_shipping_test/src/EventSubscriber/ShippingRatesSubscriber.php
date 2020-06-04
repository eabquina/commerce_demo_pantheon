<?php

namespace Drupal\commerce_shipping_test\EventSubscriber;

use Drupal\commerce_shipping\Event\ShippingEvents;
use Drupal\commerce_shipping\Event\ShippingRatesEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ShippingRatesSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      ShippingEvents::SHIPPING_RATES => 'onCalculate',
    ];
  }

  /**
   * Doubles each shipping rate.
   *
   * @param \Drupal\commerce_shipping\Event\ShippingRatesEvent $event
   *   The event.
   */
  public function onCalculate(ShippingRatesEvent $event) {
    $rates = $event->getRates();
    $shipment = $event->getShipment();
    if (empty($rates) || !$shipment->getData('alter_rate')) {
      return;
    }

    $rate = reset($rates);
    $new_amount = $rate->getAmount()->multiply('2');
    $rate->setAmount($new_amount);
  }

}
