<?php

namespace Drupal\commerce_shipping_test\EventSubscriber;

use Drupal\commerce_shipping\Event\FilterShippingMethodsEvent;
use Drupal\commerce_shipping\Event\ShippingEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class FilterShippingMethodsSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      ShippingEvents::FILTER_SHIPPING_METHODS => 'onFilter',
    ];
  }

  /**
   * Filters out shipping methods listed in a shipment's data attribute.
   *
   * @param \Drupal\commerce_shipping\Event\FilterShippingMethodsEvent $event
   *   The event.
   */
  public function onFilter(FilterShippingMethodsEvent $event) {
    $shipping_methods = $event->getShippingMethods();
    $excluded_methods = $event->getShipment()->getData('excluded_methods', []);
    foreach ($shipping_methods as $shipping_method_id => $shipping_method) {
      if (in_array($shipping_method->id(), $excluded_methods)) {
        unset($shipping_methods[$shipping_method_id]);
      }
    }
    $event->setShippingMethods($shipping_methods);
  }

}
