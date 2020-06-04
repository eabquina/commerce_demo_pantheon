<?php

namespace Drupal\commerce_shipping\Plugin\Commerce\PromotionOffer;

use Drupal\commerce_promotion\Plugin\Commerce\PromotionOffer\PromotionOfferInterface;

/**
 * Defines the interface for shipment offers.
 */
interface ShipmentPromotionOfferInterface extends PromotionOfferInterface {

  /**
   * Gets whether the offer is display inclusive.
   *
   * @return bool
   *   TRUE if the offer is display inclusive, FALSE otherwise.
   */
  public function isDisplayInclusive();

}
