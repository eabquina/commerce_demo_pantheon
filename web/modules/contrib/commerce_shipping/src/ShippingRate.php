<?php

namespace Drupal\commerce_shipping;

use Drupal\commerce_price\Price;
use Drupal\Core\Datetime\DrupalDateTime;

/**
 * Represents a shipping rate.
 */
final class ShippingRate {

  /**
   * The ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The shipping method ID.
   *
   * @var string
   */
  protected $shippingMethodId;

  /**
   * The shipping service.
   *
   * @var \Drupal\commerce_shipping\ShippingService
   */
  protected $service;

  /**
   * The original amount.
   *
   * @var \Drupal\commerce_price\Price
   */
  protected $originalAmount;

  /**
   * The amount.
   *
   * @var \Drupal\commerce_price\Price
   */
  protected $amount;

  /**
   * The description.
   *
   * @var string
   */
  protected $description;

  /**
   * The delivery date.
   *
   * @var \Drupal\Core\Datetime\DrupalDateTime
   */
  protected $deliveryDate;

  /**
   * Constructs a new ShippingRate object.
   *
   * @param array $definition
   *   The definition.
   */
  public function __construct(array $definition) {
    foreach (['shipping_method_id', 'service', 'amount'] as $required_property) {
      if (empty($definition[$required_property])) {
        throw new \InvalidArgumentException(sprintf('Missing required property %s.', $required_property));
      }
    }
    if (!$definition['service'] instanceof ShippingService) {
      throw new \InvalidArgumentException(sprintf('Property "service" should be an instance of %s.', ShippingService::class));
    }
    if (!$definition['amount'] instanceof Price) {
      throw new \InvalidArgumentException(sprintf('Property "amount" should be an instance of %s.', Price::class));
    }
    // The ID is not required because most shipping methods generate one
    // rate per service, and use the service ID when purchasing labels.
    if (empty($definition['id'])) {
      $shipping_method_id = $definition['shipping_method_id'];
      $service_id = $definition['service']->getId();
      $definition['id'] = $shipping_method_id . '--' . $service_id;
    }

    $this->id = $definition['id'];
    $this->shippingMethodId = $definition['shipping_method_id'];
    $this->service = $definition['service'];
    $this->originalAmount = $definition['original_amount'] ?? $definition['amount'];
    $this->amount = $definition['amount'];
    $this->description = $definition['description'] ?? '';
    $this->deliveryDate = $definition['delivery_date'] ?? NULL;
  }

  /**
   * Gets the ID.
   *
   * @return string
   *   The ID.
   */
  public function getId() : string {
    return $this->id;
  }

  /**
   * Gets the shipping method ID.
   *
   * @return string
   *   The shipping method ID.
   */
  public function getShippingMethodId() : string {
    return $this->shippingMethodId;
  }

  /**
   * Gets the shipping service.
   *
   * The shipping service label is meant to be displayed when presenting rates
   * for selection.
   *
   * @return \Drupal\commerce_shipping\ShippingService
   *   The shipping service.
   */
  public function getService() : ShippingService {
    return $this->service;
  }

  /**
   * Gets the original amount.
   *
   * This is the amount before promotions and fees are applied.
   *
   * @return \Drupal\commerce_price\Price
   *   The original amount.
   */
  public function getOriginalAmount() : Price {
    return $this->originalAmount;
  }

  /**
   * Sets the original amount.
   *
   * @param \Drupal\commerce_price\Price $original_amount
   *   The original amount.
   *
   * @return $this
   */
  public function setOriginalAmount(Price $original_amount) {
    $this->originalAmount = $original_amount;
    return $this;
  }

  /**
   * Gets the amount.
   *
   * @return \Drupal\commerce_price\Price
   *   The amount.
   */
  public function getAmount() : Price {
    return $this->amount;
  }

  /**
   * Sets the amount.
   *
   * @param \Drupal\commerce_price\Price $amount
   *   The amount.
   *
   * @return $this
   */
  public function setAmount(Price $amount) {
    $this->amount = $amount;
    return $this;
  }

  /**
   * Gets the description.
   *
   * Displayed to the end-user when available.
   *
   * @return string
   *   The description
   */
  public function getDescription() : string {
    return $this->description;
  }

  /**
   * Sets the description.
   *
   * @param string $description
   *   The description.
   *
   * @return $this
   */
  public function setDescription(string $description) {
    $this->description = $description;
    return $this;
  }

  /**
   * Gets the delivery date, if known.
   *
   * @return \Drupal\Core\Datetime\DrupalDateTime|null
   *   The delivery date, or NULL.
   */
  public function getDeliveryDate() {
    return $this->deliveryDate;
  }

  /**
   * Sets the delivery date.
   *
   * @param \Drupal\Core\Datetime\DrupalDateTime $delivery_date
   *   The delivery date.
   *
   * @return $this
   */
  public function setDeliveryDate(DrupalDateTime $delivery_date) {
    $this->deliveryDate = $delivery_date;
    return $this;
  }

  /**
   * Gets the array representation of the shipping rate.
   *
   * @return array
   *   The array representation of the shipping rate.
   */
  public function toArray() : array {
    return [
      'id' => $this->id,
      'shipping_method_id' => $this->shippingMethodId,
      'service' => $this->service,
      'original_amount' => $this->originalAmount,
      'amount' => $this->amount,
      'description' => $this->description,
      'delivery_date' => $this->deliveryDate,
    ];
  }

}
