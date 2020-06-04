<?php

namespace Drupal\commerce_shipping\EventSubscriber;

use Drupal\commerce\Event\ReferenceablePluginTypesEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ReferenceablePluginTypesSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      'commerce.referenceable_plugin_types' => 'onPluginTypes',
    ];
  }

  /**
   * Registers the 'commerce_shipping_method' plugin type as referenceable.
   *
   * @param \Drupal\commerce\Event\ReferenceablePluginTypesEvent $event
   *   The event.
   */
  public function onPluginTypes(ReferenceablePluginTypesEvent $event) {
    $plugin_types = $event->getPluginTypes();
    $plugin_types['commerce_shipping_method'] = t('Shipping method');
    $event->setPluginTypes($plugin_types);
  }

}
