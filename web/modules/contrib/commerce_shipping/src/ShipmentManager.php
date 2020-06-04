<?php

namespace Drupal\commerce_shipping;

use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\Event\ShippingEvents;
use Drupal\commerce_shipping\Event\ShippingRatesEvent;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ShipmentManager implements ShipmentManagerInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a new ShipmentManager object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityRepositoryInterface $entity_repository, EventDispatcherInterface $event_dispatcher, LoggerInterface $logger) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityRepository = $entity_repository;
    $this->eventDispatcher = $event_dispatcher;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public function applyRate(ShipmentInterface $shipment, ShippingRate $rate) {
    $shipping_method_storage = $this->entityTypeManager->getStorage('commerce_shipping_method');
    /** @var \Drupal\commerce_shipping\Entity\ShippingMethodInterface $shipping_method */
    $shipping_method = $shipping_method_storage->load($rate->getShippingMethodId());
    $shipping_method_plugin = $shipping_method->getPlugin();
    if (empty($shipment->getPackageType())) {
      $shipment->setPackageType($shipping_method_plugin->getDefaultPackageType());
    }
    $shipping_method_plugin->selectRate($shipment, $rate);
  }

  /**
   * {@inheritdoc}
   */
  public function calculateRates(ShipmentInterface $shipment) {
    $all_rates = [];
    /** @var \Drupal\commerce_shipping\ShippingMethodStorageInterface $shipping_method_storage */
    $shipping_method_storage = $this->entityTypeManager->getStorage('commerce_shipping_method');
    $shipping_methods = $shipping_method_storage->loadMultipleForShipment($shipment);
    foreach ($shipping_methods as $shipping_method) {
      /** @var \Drupal\commerce_shipping\Entity\ShippingMethodInterface $shipping_method */
      $shipping_method = $this->entityRepository->getTranslationFromContext($shipping_method);
      $shipping_method_plugin = $shipping_method->getPlugin();
      try {
        $rates = $shipping_method_plugin->calculateRates($shipment);
      }
      catch (\Exception $exception) {
        $this->logger->error('Exception occurred when calculating rates for @name: @message', [
          '@name' => $shipping_method->getName(),
          '@message' => $exception->getMessage(),
        ]);
        continue;
      }
      // Allow the rates to be altered via code.
      $event = new ShippingRatesEvent($rates, $shipping_method, $shipment);
      $this->eventDispatcher->dispatch(ShippingEvents::SHIPPING_RATES, $event);
      $rates = $event->getRates();

      $rates = $this->sortRates($rates);
      foreach ($rates as $rate) {
        $all_rates[$rate->getId()] = $rate;
      }
    }

    return $all_rates;
  }

  /**
   * {@inheritdoc}
   */
  public function selectDefaultRate(ShipmentInterface $shipment, array $rates) {
    /** @var \Drupal\commerce_shipping\ShippingRate[] $rates */
    $default_rate = reset($rates);
    if ($shipment->getShippingMethodId() && $shipment->getShippingService()) {
      // Select the first rate which matches the shipment's selected
      // shipping method and service.
      foreach ($rates as $rate) {
        if ($shipment->getShippingMethodId() != $rate->getShippingMethodId()) {
          continue;
        }
        if ($shipment->getShippingService() != $rate->getService()->getId()) {
          continue;
        }
        $default_rate = $rate;
        break;
      }
    }

    return $default_rate;
  }

  /**
   * Sorts the given rates.
   *
   * @param \Drupal\commerce_shipping\ShippingRate[] $rates
   *   The rates.
   *
   * @return \Drupal\commerce_shipping\ShippingRate[]
   *   The sorted rates.
   */
  protected function sortRates(array $rates) {
    // Sort by original_amount ascending.
    uasort($rates, function (ShippingRate $first_rate, ShippingRate $second_rate) {
      return $first_rate->getOriginalAmount()->compareTo($second_rate->getOriginalAmount());
    });

    return $rates;
  }

}
