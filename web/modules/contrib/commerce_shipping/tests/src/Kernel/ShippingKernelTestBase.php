<?php

namespace Drupal\Tests\commerce_shipping\Kernel;

use Drupal\commerce_order\Entity\OrderType;
use Drupal\commerce_product\Entity\ProductVariationType;
use Drupal\Tests\commerce_order\Kernel\OrderKernelTestBase;

/**
 * Provides a base class for Shipping kernel tests.
 */
abstract class ShippingKernelTestBase extends OrderKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'physical',
    'path',
    'commerce_shipping',
    'commerce_shipping_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('commerce_shipping_method');
    $this->installEntitySchema('commerce_shipment');
    $this->installConfig([
      'profile',
      'commerce_product',
      'commerce_order',
      'commerce_shipping',
    ]);
    /** @var \Drupal\commerce_product\Entity\ProductVariationTypeInterface $product_variation_type */
    $product_variation_type = ProductVariationType::load('default');
    $product_variation_type->setGenerateTitle(FALSE);
    $product_variation_type->save();
    // Install the variation trait.
    $trait_manager = $this->container->get('plugin.manager.commerce_entity_trait');
    $trait = $trait_manager->createInstance('purchasable_entity_shippable');
    $trait_manager->installTrait($trait, 'commerce_product_variation', 'default');

    /** @var \Drupal\commerce_order\Entity\OrderTypeInterface $order_type */
    $order_type = OrderType::load('default');
    $order_type->setThirdPartySetting('commerce_shipping', 'shipment_type', 'default');
    $order_type->save();
    // Create the order field.
    $field_definition = commerce_shipping_build_shipment_field_definition($order_type->id());
    $this->container->get('commerce.configurable_field_manager')->createField($field_definition);
  }

}
