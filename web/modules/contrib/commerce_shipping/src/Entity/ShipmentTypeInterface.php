<?php

namespace Drupal\commerce_shipping\Entity;

use Drupal\commerce\Entity\CommerceBundleEntityInterface;

/**
 * Defines the interface for shipment types.
 */
interface ShipmentTypeInterface extends CommerceBundleEntityInterface {

  /**
   * Gets the profile type ID.
   *
   * @return string
   *   The profile type ID.
   */
  public function getProfileTypeId();

  /**
   * Sets the profile type ID.
   *
   * @param string $profile_type_id
   *   The profile type ID.
   *
   * @return $this
   */
  public function setProfileTypeId($profile_type_id);

}
