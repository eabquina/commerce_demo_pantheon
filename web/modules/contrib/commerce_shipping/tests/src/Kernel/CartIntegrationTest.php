<?php

namespace Drupal\Tests\commerce_shipping\Kernel;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\physical\Weight;
use Drupal\profile\Entity\Profile;
use Drupal\Tests\commerce_cart\Traits\CartManagerTestTrait;

/**
 * Tests integration with the Cart module.
 *
 * @group commerce_shipping
 */
class CartIntegrationTest extends ShippingKernelTestBase {

  use CartManagerTestTrait;

  /**
   * The sample product variations.
   *
   * @var \Drupal\commerce_product\Entity\ProductVariationInterface[[
   */
  protected $variations = [];

  /**
   * The cart provider.
   *
   * @var \Drupal\commerce_cart\CartProviderInterface
   */
  protected $cartProvider;

  /**
   * The cart manager.
   *
   * @var \Drupal\commerce_cart\CartManagerInterface
   */
  protected $cartManager;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->installCommerceCart();

    $first_variation = ProductVariation::create([
      'type' => 'default',
      'sku' => 'test-product-01',
      'title' => 'Hat',
      'price' => new Price('10', 'USD'),
      'weight' => new Weight('0', 'g'),
    ]);
    $first_variation->save();
    $this->variations[] = $first_variation;

    $second_variation = ProductVariation::create([
      'type' => 'default',
      'sku' => 'test-product-02',
      'title' => 'Mug',
      'price' => new Price('10', 'USD'),
      'weight' => new Weight('0', 'g'),
    ]);
    $second_variation->save();
    $this->variations[] = $second_variation;

    $this->cartProvider = $this->container->get('commerce_cart.cart_provider');
    $this->cartManager = $this->container->get('commerce_cart.cart_manager');

  }

  /**
   * Tests that emptying a cart removes its shipments.
   */
  public function testEmptyCart() {
    $cart = $this->cartProvider->createCart('default');
    $this->cartManager->addEntity($cart, $this->variations[0]);

    $shipping_profile = Profile::create([
      'type' => 'customer',
      'address' => [
        'country_code' => 'US',
      ],
    ]);
    $shipping_profile->save();

    $shipping_order_manager = $this->container->get('commerce_shipping.order_manager');
    $shipments = $shipping_order_manager->pack($cart, $shipping_profile);
    $cart->set('shipments', $shipments);
    $cart->setRefreshState(OrderInterface::REFRESH_SKIP);
    $cart->save();

    $this->assertCount(1, $cart->get('shipments')->referencedEntities());
    $this->cartManager->emptyCart($cart);
    $this->assertCount(0, $cart->get('shipments')->referencedEntities());

    $storage = $this->container->get('entity_type.manager')->getStorage('commerce_shipment');
    $shipments = $storage->loadMultiple();
    $this->assertEmpty($shipments);
  }

}
