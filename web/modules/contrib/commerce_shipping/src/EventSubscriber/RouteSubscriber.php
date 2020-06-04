<?php

namespace Drupal\commerce_shipping\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\RouteCollection;

/**
 * Re-add the route requirement for the shipment collection route.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = parent::getSubscribedEvents();
    // Ensure to run after the Views route subscriber.
    // @see \Drupal\views\EventSubscriber\RouteSubscriber.
    $events[RoutingEvents::ALTER] = ['onAlterRoutes', -200];

    return $events;
  }

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    // ShipmentRouteProvider sets the "_shipment_collection_access" requirement
    // to the shipment collection route but it's being removed by the route
    // subscriber provided by Views, so put it back.
    // @todo: Remove once Views will merge the route requirements.
    $route = $collection->get('entity.commerce_shipment.collection');
    if ($route) {
      $route->setRequirement('_shipment_collection_access', 'TRUE');
    }
  }

}
