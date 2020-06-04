<?php

namespace Drupal\commerce_shipping\Plugin\Commerce\ShippingMethod;

use Drupal\commerce_price\Price;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\ShippingRate;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the FlatRatePerItem shipping method.
 *
 * @CommerceShippingMethod(
 *   id = "flat_rate_per_item",
 *   label = @Translation("Flat rate per item"),
 * )
 */
class FlatRatePerItem extends FlatRate {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['rate_amount']['#description'] = t('Charged for each quantity of each shipment item.');

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateRates(ShipmentInterface $shipment) {
    $amount = Price::fromArray($this->configuration['rate_amount']);
    $quantity = $shipment->getTotalQuantity();
    $rates = [];
    $rates[] = new ShippingRate([
      'shipping_method_id' => $this->parentEntity->id(),
      'service' => $this->services['default'],
      'amount' => $amount->multiply($quantity),
    ]);

    return $rates;
  }

}
