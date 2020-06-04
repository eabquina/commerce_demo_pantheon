<?php

namespace Drupal\commerce_demo\EventSubscriber;

use Drupal\address\Event\AddressEvents;
use Drupal\address\Event\InitialValuesEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AddressEventsSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[AddressEvents::INITIAL_VALUES][] = ['onInitialValues'];
    return $events;
  }

  /**
   * Alters the initial values.
   *
   * @param \Drupal\address\Event\InitialValuesEvent $event
   *   The initial values event.
   */
  public function onInitialValues(InitialValuesEvent $event) {
    if ($event->getFieldDefinition()->getTargetEntityTypeId() == 'profile' && $event->getFieldDefinition()->getTargetBundle() == 'customer') {
      $new_values = $this->getRandomAddress() + $event->getInitialValues();
      $event->setInitialValues($new_values);
    }
  }

  /**
   * Gets a random address value.
   *
   * @return array
   */
  protected function getRandomAddress() {
    $addresses = [
      [
        'given_name' => 'Celia',
        'family_name' => 'Engeseth',
        'address_line1' => '8502 Pilgrim St.',
        'locality' => 'Mokena',
        'administrative_area' => 'IL',
        'postal_code' => '60448',
      ],
      [
        'given_name' => 'Josefine',
        'family_name' => 'Christiansen',
        'address_line1' => '7691 East 6th St',
        'locality' => 'Lewiston',
        'administrative_area' => 'ME',
        'postal_code' => '04240',
      ],
      [
        'given_name' => 'Oliver',
        'family_name' => 'Mantyla',
        'address_line1' => '315 Addison Court ',
        'locality' => 'New Windsor',
        'administrative_area' => 'NY',
        'postal_code' => '12553',
      ],
      [
        'given_name' => 'Heinz Dieter',
        'family_name' => 'Reinke',
        'address_line1' => '45 Bow Ridge Ave',
        'locality' => 'West Chicago',
        'administrative_area' => 'IL',
        'postal_code' => '60185',
      ],
    ];
    return $addresses[array_rand($addresses)];
  }

}
