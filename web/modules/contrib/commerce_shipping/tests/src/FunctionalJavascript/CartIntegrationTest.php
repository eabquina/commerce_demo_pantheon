<?php

namespace Drupal\Tests\commerce_shipping\FunctionalJavascript;

use Drupal\commerce_order\Entity\OrderType;
use Drupal\commerce_payment\Entity\PaymentGateway;
use Drupal\commerce_product\Entity\ProductVariationType;
use Drupal\Tests\commerce\FunctionalJavascript\CommerceWebDriverTestBase;

/**
 * Tests integration with the Cart module.
 *
 * @group commerce_shipping
 */
class CartIntegrationTest extends CommerceWebDriverTestBase {

  /**
   * First sample product.
   *
   * @var \Drupal\commerce_product\Entity\ProductInterface
   */
  protected $firstProduct;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'commerce_cart',
    'commerce_payment',
    'commerce_payment_example',
    'commerce_shipping_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function getAdministratorPermissions() {
    return array_merge([
      'access checkout',
    ], parent::getAdministratorPermissions());
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Limit the available countries.
    $this->store->shipping_countries = ['US', 'FR', 'DE'];
    $this->store->save();

    /** @var \Drupal\commerce_payment\Entity\PaymentGateway $gateway */
    $gateway = PaymentGateway::create([
      'id' => 'example_onsite',
      'label' => 'Example',
      'plugin' => 'example_onsite',
    ]);
    $gateway->getPlugin()->setConfiguration([
      'api_key' => '2342fewfsfs',
      'payment_method_types' => ['credit_card'],
    ]);
    $gateway->save();

    $product_variation_type = ProductVariationType::load('default');
    $product_variation_type->setTraits(['purchasable_entity_shippable']);
    $product_variation_type->save();

    $order_type = OrderType::load('default');
    $order_type->setThirdPartySetting('commerce_checkout', 'checkout_flow', 'shipping');
    $order_type->setThirdPartySetting('commerce_shipping', 'shipment_type', 'default');
    $order_type->save();

    // Create the order field.
    $field_definition = commerce_shipping_build_shipment_field_definition($order_type->id());
    $this->container->get('commerce.configurable_field_manager')->createField($field_definition);

    // Install the variation trait.
    $trait_manager = $this->container->get('plugin.manager.commerce_entity_trait');
    $trait = $trait_manager->createInstance('purchasable_entity_shippable');
    $trait_manager->installTrait($trait, 'commerce_product_variation', 'default');

    // Create product.
    $variation = $this->createEntity('commerce_product_variation', [
      'type' => 'default',
      'sku' => strtolower($this->randomMachineName()),
      'price' => [
        'number' => '7.99',
        'currency_code' => 'USD',
      ],
      'weight' => [
        'number' => '20',
        'unit' => 'g',
      ],
    ]);
    /** @var \Drupal\commerce_product\Entity\ProductInterface $product */
    $this->firstProduct = $this->createEntity('commerce_product', [
      'type' => 'default',
      'title' => 'Conference hat',
      'variations' => [$variation],
      'stores' => [$this->store],
    ]);

    /** @var \Drupal\commerce_shipping\Entity\PackageType $package_type */
    $package_type = $this->createEntity('commerce_package_type', [
      'id' => 'package_type_a',
      'label' => 'Package Type A',
      'dimensions' => [
        'length' => 20,
        'width' => 20,
        'height' => 20,
        'unit' => 'mm',

      ],
      'weight' => [
        'number' => 20,
        'unit' => 'g',
      ],
    ]);
    $this->container->get('plugin.manager.commerce_package_type')->clearCachedDefinitions();

    // Create a flat rate per item shipping method to make testing adjustments
    // in items easier.
    $this->createEntity('commerce_shipping_method', [
      'name' => 'Flat Rate Per Item',
      'stores' => [$this->store->id()],
      'plugin' => [
        'target_plugin_id' => 'flat_rate_per_item',
        'target_plugin_configuration' => [
          'rate_label' => 'Flat Rate Per Item',
          'rate_amount' => [
            'number' => '10.00',
            'currency_code' => 'USD',
          ],
        ],
      ],
      'conditions' => [
        [
          'target_plugin_id' => 'shipment_weight',
          'target_plugin_configuration' => [
            'operator' => '<',
            'weight' => [
              'number' => '120',
              'unit' => 'g',
            ],
          ],
        ],
      ],
    ]);
  }

  /**
   * Test for Flat Rate Per Item shipping cost updates.
   */
  public function testRecalculatePerItem() {
    // Add product to order and calculate shipping.
    $this->drupalGet($this->firstProduct->toUrl()->toString());
    $this->submitForm([], 'Add to cart');
    $this->drupalGet('checkout/1');
    $address = [
      'given_name' => 'John',
      'family_name' => 'Smith',
      'address_line1' => '1098 Alta Ave',
      'locality' => 'Mountain View',
      'administrative_area' => 'CA',
      'postal_code' => '94043',
    ];
    $address_prefix = 'shipping_information[shipping_profile][address][0][address]';
    $this->getSession()->getPage()->fillField($address_prefix . '[country_code]', 'US');
    $this->assertSession()->assertWaitOnAjaxRequest();
    foreach ($address as $property => $value) {
      $this->getSession()->getPage()->fillField($address_prefix . '[' . $property . ']', $value);
    }
    $this->getSession()->getPage()->findButton('Recalculate shipping')->click();
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->uncheckField('payment_information[add_payment_method][billing_information][copy_fields][enable]');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->submitForm([
      'payment_information[add_payment_method][payment_details][number]' => '4111111111111111',
      'payment_information[add_payment_method][payment_details][expiration][month]' => '02',
      'payment_information[add_payment_method][payment_details][expiration][year]' => '2023',
      'payment_information[add_payment_method][payment_details][security_code]' => '123',
      'payment_information[add_payment_method][billing_information][address][0][address][given_name]' => 'Johnny',
      'payment_information[add_payment_method][billing_information][address][0][address][family_name]' => 'Appleseed',
      'payment_information[add_payment_method][billing_information][address][0][address][address_line1]' => '123 New York Drive',
      'payment_information[add_payment_method][billing_information][address][0][address][locality]' => 'New York City',
      'payment_information[add_payment_method][billing_information][address][0][address][administrative_area]' => 'NY',
      'payment_information[add_payment_method][billing_information][address][0][address][postal_code]' => '10001',
    ], 'Continue to review');
    $this->assertSession()->pageTextContains('Shipping $10.00');

    // Test whether the shipping amount gets updated.
    $this->drupalGet('/cart');
    $this->getSession()->getPage()->fillField('edit_quantity[0]', 5);
    $this->getSession()->getPage()->findButton('Update cart')->click();
    $this->assertSession()->pageTextContains('Shipping $50.00');

    $this->drupalGet('checkout/1');
    $this->assertSession()->pageTextContains('Shipping $50.00');

    $this->getSession()->getPage()->findButton('Recalculate shipping')->click();
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('Shipping method');
    $this->assertSession()->pageTextContains('Shipping $50.00');

    $this->getSession()->getPage()->findButton('Continue to review')->click();
    $this->assertSession()->pageTextContains('Shipping $50.00');

  }

  /**
   * Test for recalculating shipping trough cart/checkout steps.
   */
  public function testRecalculateRatesCart() {
    // Create a flat rate.
    $this->createEntity('commerce_shipping_method', [
      'name' => 'Flat Rate',
      'stores' => [$this->store->id()],
      'plugin' => [
        'target_plugin_id' => 'flat_rate',
        'target_plugin_configuration' => [
          'rate_label' => 'Free Shipping',
          'rate_amount' => [
            'number' => '0.00',
            'currency_code' => 'USD',
          ],
        ],
      ],
      'conditions' => [
        [
          'target_plugin_id' => 'shipment_weight',
          'target_plugin_configuration' => [
            'operator' => '>',
            'weight' => [
              'number' => '120',
              'unit' => 'g',
            ],
          ],
        ],
      ],
    ]);

    // Add product to order and calculate shipping.
    $this->drupalGet($this->firstProduct->toUrl()->toString());
    $this->submitForm([], 'Add to cart');
    $this->drupalGet('checkout/1');
    $address = [
      'given_name' => 'John',
      'family_name' => 'Smith',
      'address_line1' => '1098 Alta Ave',
      'locality' => 'Mountain View',
      'administrative_area' => 'CA',
      'postal_code' => '94043',
    ];
    $address_prefix = 'shipping_information[shipping_profile][address][0][address]';
    $this->getSession()->getPage()->fillField($address_prefix . '[country_code]', 'US');
    $this->assertSession()->assertWaitOnAjaxRequest();
    foreach ($address as $property => $value) {
      $this->getSession()->getPage()->fillField($address_prefix . '[' . $property . ']', $value);
    }
    $this->getSession()->getPage()->findButton('Recalculate shipping')->click();
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->submitForm([
      'payment_information[add_payment_method][payment_details][number]' => '4111111111111111',
      'payment_information[add_payment_method][payment_details][expiration][month]' => '02',
      'payment_information[add_payment_method][payment_details][expiration][year]' => '2023',
      'payment_information[add_payment_method][payment_details][security_code]' => '123',
    ], 'Continue to review');

    $this->assertSession()->pageTextContains('Shipping $10.00');

    // Test whether the shipping amount gets updated.
    $this->drupalGet('/cart');
    $this->getSession()->getPage()->fillField('edit_quantity[0]', 10);
    $this->getSession()->getPage()->findButton('Update cart')->click();
    $this->assertSession()->pageTextContains('Shipping $0.00');

    $this->drupalGet('checkout/1');
    $this->assertSession()->pageTextContains('Shipping $0.00');

    $this->getSession()->getPage()->findButton('Recalculate shipping')->click();
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('Shipping method');
    $this->assertSession()->pageTextContains('Shipping $0.00');

    $this->getSession()->getPage()->findButton('Continue to review')->click();
    $this->assertSession()->pageTextContains('Shipping $0.00');

    // Test whether the shipping amount is cleared if there is no valid methods.
    $this->drupalGet('/cart');
    $this->getSession()->getPage()->fillField('edit_quantity[0]', 6);
    $this->getSession()->getPage()->findButton('Update cart')->click();
    $this->assertSession()->pageTextNotContains('Shipping $0.00');

    $this->drupalGet('checkout/1');
    $this->assertSession()->pageTextNotContains('Shipping $0.00');

    // There is no valid methods.
    $this->getSession()->getPage()->findButton('Recalculate shipping')->click();
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextNotContains('Shipping method');

    // Test adding a new product to the cart to see if the rates are
    // recalculated.
    $variation = $this->createEntity('commerce_product_variation', [
      'type' => 'default',
      'sku' => strtolower($this->randomMachineName()),
      'price' => [
        'number' => '7.99',
        'currency_code' => 'USD',
      ],
      'weight' => [
        'number' => '20',
        'unit' => 'g',
      ],
    ]);
    /** @var \Drupal\commerce_product\Entity\ProductInterface $product */
    $another_product = $this->createEntity('commerce_product', [
      'type' => 'default',
      'title' => $this->randomString(),
      'variations' => [$variation],
      'stores' => [$this->store],
    ]);
    $this->drupalGet($another_product->toUrl()->toString());
    $this->submitForm([], 'Add to cart');
    $this->drupalGet('/cart');
    $this->assertSession()->pageTextContains('Shipping $0.00');
  }

}
