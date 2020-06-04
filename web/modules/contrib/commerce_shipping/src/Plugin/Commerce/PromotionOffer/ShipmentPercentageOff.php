<?php

namespace Drupal\commerce_shipping\Plugin\Commerce\PromotionOffer;

use Drupal\commerce_order\Adjustment;
use Drupal\commerce_promotion\Entity\PromotionInterface;
use Drupal\commerce_promotion\Plugin\Commerce\PromotionOffer\PercentageOffTrait;
use Drupal\commerce_shipping\Entity\ShipmentInterface;

/**
 * Provides the percentage off offer for shipments.
 *
 * @CommercePromotionOffer(
 *   id = "shipment_percentage_off",
 *   label = @Translation("Percentage off the shipment amount"),
 *   entity_type = "commerce_order"
 * )
 */
class ShipmentPercentageOff extends ShipmentPromotionOfferBase {

  use PercentageOffTrait;

  /**
   * {@inheritdoc}
   */
  public function applyToShipment(ShipmentInterface $shipment, PromotionInterface $promotion) {
    $percentage = $this->getPercentage();
    // The offer amount is calculated from the unreduced shipment amount.
    $amount = $shipment->getAmount()->multiply($percentage);
    $amount = $this->rounder->round($amount);
    // The offer amount can't be larger than the remaining shipment amount,
    // to avoid a negative total.
    $remaining_amount = $shipment->getAdjustedAmount();
    if ($amount->greaterThan($remaining_amount)) {
      $amount = $remaining_amount;
    }
    // Display-inclusive promotions must first be applied to the amount.
    if ($this->isDisplayInclusive()) {
      $new_shipment_amount = $shipment->getAmount()->subtract($amount);
      $shipment->setAmount($new_shipment_amount);
    }

    $shipment->addAdjustment(new Adjustment([
      'type' => 'shipping_promotion',
      'label' => $promotion->getDisplayName() ?: $this->t('Discount'),
      'amount' => $amount->multiply('-1'),
      'percentage' => $percentage,
      'source_id' => $promotion->id(),
      'included' => $this->isDisplayInclusive(),
    ]));
  }

}
