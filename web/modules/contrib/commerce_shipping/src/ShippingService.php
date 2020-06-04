<?php

namespace Drupal\commerce_shipping;

/**
 * Represents a shipping service.
 */
final class ShippingService {

  /**
   * The shipping service ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The shipping service label.
   *
   * @var string
   */
  protected $label;

  /**
   * Constructs a new ShippingService instance.
   *
   * @param string $id
   *   The shipping service ID.
   * @param string $label
   *   The shipping service label.
   */
  public function __construct($id, $label) {
    $this->id = $id;
    $this->label = $label;
  }

  /**
   * Gets the shipping service ID.
   *
   * @return string
   *   The shipping service ID.
   */
  public function getId() : string {
    return $this->id;
  }

  /**
   * Gets the shipping service label.
   *
   * @return string
   *   The shipping service label.
   */
  public function getLabel() : string {
    return $this->label;
  }

}
