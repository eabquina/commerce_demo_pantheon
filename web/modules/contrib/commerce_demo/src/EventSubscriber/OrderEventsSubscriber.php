<?php

namespace Drupal\commerce_demo\EventSubscriber;

use Drupal\commerce_order\Event\OrderEvent;
use Drupal\commerce_order\Event\OrderEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderEventsSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[OrderEvents::ORDER_CREATE][] = ['onOrderCreate'];
    return $events;
  }

  /**
   * Reacts to an order being created..
   *
   * @param \Drupal\commerce_order\Event\OrderEvent $event
   *   The order event.
   */
  public function onOrderCreate(OrderEvent $event) {
    $order = $event->getOrder();
    if ($order->getCustomer()->isAnonymous()) {
      $order->setEmail(sprintf('demo+%s@commercekickstart.com', time()));
    }
  }

}
