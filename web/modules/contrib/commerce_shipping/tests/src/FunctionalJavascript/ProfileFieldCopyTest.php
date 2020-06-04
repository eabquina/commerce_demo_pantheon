<?php

namespace Drupal\Tests\commerce_shipping\FunctionalJavascript;

use Drupal\commerce_order\Entity\OrderType;
use Drupal\commerce_payment\Entity\PaymentGateway;
use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\ProductType;
use Drupal\commerce_product\Entity\ProductVariationType;
use Drupal\Core\Url;
use Drupal\field\Entity\FieldConfig;
use Drupal\Tests\commerce\FunctionalJavascript\CommerceWebDriverTestBase;

/**
 * Tests the "Shipping information" checkout pane.
 *
 * @group commerce_shipping
 */
class ProfileFieldCopyTest extends CommerceWebDriverTestBase {

  /**
   * A French address.
   *
   * @var array
   */
  protected $frenchAddress = [
    'country_code' => 'FR',
    'locality' => 'Paris',
    'postal_code' => '75002',
    'address_line1' => '38 Rue du Sentier',
    'given_name' => 'Leon',
    'family_name' => 'Blum',
  ];

  /**
   * A US address.
   *
   * @var array
   */
  protected $usAddress = [
    'country_code' => 'US',
    'administrative_area' => 'SC',
    'locality' => 'Greenville',
    'postal_code' => '29616',
    'address_line1' => '9 Drupal Ave',
    'given_name' => 'Bryan',
    'family_name' => 'Centarro',
  ];

  /**
   * First sample product.
   *
   * @var \Drupal\commerce_product\Entity\ProductInterface
   */
  protected $firstProduct;

  /**
   * Second sample product.
   *
   * @var \Drupal\commerce_product\Entity\ProductInterface
   */
  protected $secondProduct;

  /**
   * A sample order.
   *
   * @var \Drupal\commerce_order\Entity\OrderInterface
   */
  protected $order;

  /**
   * The shipping order manager.
   *
   * @var \Drupal\commerce_shipping\ShippingOrderManagerInterface
   */
  protected $shippingOrderManager;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'commerce_payment',
    'commerce_payment_example',
    'commerce_promotion',
    'commerce_tax',
    'commerce_shipping_test',
    'telephone',
  ];

  /**
   * {@inheritdoc}
   */
  protected function getAdministratorPermissions() {
    return array_merge([
      'administer commerce_order',
      'administer commerce_shipment',
      'access commerce_order overview',
    ], parent::getAdministratorPermissions());
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->store->set('billing_countries', ['FR', 'US']);
    $this->store->save();

    // Turn off verification via external services.
    $tax_number_field = FieldConfig::loadByName('profile', 'customer', 'tax_number');
    $tax_number_field->setSetting('verify', FALSE);
    $tax_number_field->save();

    /** @var \Drupal\commerce_payment\Entity\PaymentGatewayInterface $payment_gateway */
    $payment_gateway = PaymentGateway::create([
      'id' => 'cod',
      'label' => 'Manual',
      'plugin' => 'manual',
      'configuration' => [
        'display_label' => 'Cash on delivery',
        'instructions' => [
          'value' => 'Sample payment instructions.',
          'format' => 'plain_text',
        ],
      ],
    ]);
    $payment_gateway->save();

    $variation_type = ProductVariationType::load('default');
    $variation_type->setTraits(['purchasable_entity_shippable']);
    $variation_type->save();

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

    // Create a non-shippable product/variation type set.
    $variation_type = ProductVariationType::create([
      'id' => 'digital',
      'label' => 'Digital',
      'orderItemType' => 'default',
      'generateTitle' => TRUE,
    ]);
    $variation_type->save();

    $product_type = ProductType::create([
      'id' => 'Digital',
      'label' => 'Digital',
      'variationType' => $variation_type->id(),
    ]);
    $product_type->save();

    // Create two products. One shippable, one non-shippable.
    $variation = $this->createEntity('commerce_product_variation', [
      'type' => 'default',
      'sku' => strtolower($this->randomMachineName()),
      'price' => [
        'number' => '7.99',
        'currency_code' => 'USD',
      ],
    ]);
    /** @var \Drupal\commerce_product\Entity\ProductInterface $product */
    $this->firstProduct = $this->createEntity('commerce_product', [
      'type' => 'default',
      'title' => 'Conference hat',
      'variations' => [$variation],
      'stores' => [$this->store],
    ]);

    $variation = $this->createEntity('commerce_product_variation', [
      'type' => 'digital',
      'sku' => strtolower($this->randomMachineName()),
      'price' => [
        'number' => '8.99',
        'currency_code' => 'USD',
      ],
    ]);
    /** @var \Drupal\commerce_product\Entity\ProductInterface $product */
    $this->secondProduct = $this->createEntity('commerce_product', [
      'type' => 'digital',
      'title' => 'Conference ticket',
      'variations' => [$variation],
      'stores' => [$this->store],
    ]);

    $order_item = $this->createEntity('commerce_order_item', [
      'type' => 'default',
      'title' => $this->randomMachineName(),
      'quantity' => 1,
      'unit_price' => new Price('7.99', 'USD'),
      'purchased_entity' => $this->firstProduct->getDefaultVariation(),
    ]);
    $order_item->save();
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $this->order = $this->createEntity('commerce_order', [
      'type' => 'default',
      'order_number' => '2020/01',
      'store_id' => $this->store,
      'uid' => $this->adminUser->id(),
      'order_items' => [$order_item],
      'state' => 'draft',
      'payment_gateway' => $payment_gateway->id(),
    ]);

    /** @var \Drupal\commerce_shipping\Entity\PackageType $package_type */
    $package_type = $this->createEntity('commerce_package_type', [
      'id' => 'package_type_a',
      'label' => 'Package Type A',
      'dimensions' => [
        'length' => '20',
        'width' => '20',
        'height' => '20',
        'unit' => 'mm',

      ],
      'weight' => [
        'number' => '20',
        'unit' => 'g',
      ],
    ]);
    $this->container->get('plugin.manager.commerce_package_type')->clearCachedDefinitions();

    $shipping_method = $this->createEntity('commerce_shipping_method', [
      'name' => 'Standard shipping',
      'stores' => [$this->store->id()],
      // Ensure that Standard shipping shows before overnight shipping.
      'weight' => -10,
      'plugin' => [
        'target_plugin_id' => 'flat_rate',
        'target_plugin_configuration' => [
          'rate_label' => 'Standard shipping',
          'rate_amount' => [
            'number' => '9.99',
            'currency_code' => 'USD',
          ],
        ],
      ],
    ]);

    $this->shippingOrderManager = $this->container->get('commerce_shipping.order_manager');
  }

  /**
   * Tests the admin UI.
   */
  public function testAdmin() {
    $billing_prefix = 'billing_profile[0][profile]';
    // Confirm that the checkbox is not shown until a shipping profile is added.
    $this->drupalGet($this->order->toUrl('edit-form'));
    $this->assertSession()->fieldNotExists($billing_prefix . '[copy_fields][enable]');
    $this->assertSession()->fieldExists($billing_prefix . '[address][0][address][address_line1]');
    $this->assertSession()->fieldExists($billing_prefix . '[copy_to_address_book]');

    $shipping_profile = $this->shippingOrderManager->createProfile($this->order, [
      'address' => $this->frenchAddress,
    ]);
    $shipping_profile->save();
    $shipments = $this->shippingOrderManager->pack($this->order, $shipping_profile);
    $this->order->set('shipments', $shipments);
    $this->order->save();
    // Confirm that the checkbox is now shown and checked.
    $this->drupalGet($this->order->toUrl('edit-form'));
    $this->assertSession()->checkboxChecked($billing_prefix . '[copy_fields][enable]');
    $this->assertSession()->fieldNotExists($billing_prefix . '[address][0][address][address_line1]');
    $this->assertSession()->fieldNotExists($billing_prefix . '[copy_to_address_book]');

    // Confirm that submitting the form populates the billing profile.
    $this->submitForm([], 'Save');
    $this->assertSession()->pageTextContains('The order 2020/01 has been successfully saved.');
    $this->order = $this->reloadEntity($this->order);
    $billing_profile = $this->order->getBillingProfile();
    /** @var \Drupal\address\AddressInterface $address */
    $address = $billing_profile->get('address')->first();
    $this->assertEquals($this->frenchAddress, array_filter($address->toArray()));
    $this->assertNotEmpty($billing_profile->getData('copy_fields'));
    $this->assertEmpty($billing_profile->getData('address_book_profile_id'));

    // Confirm that the checkbox can be unchecked.
    $this->drupalGet($this->order->toUrl('edit-form'));
    $this->assertSession()->checkboxChecked($billing_prefix . '[copy_fields][enable]');
    $this->getSession()->getPage()->uncheckField($billing_prefix . '[copy_fields][enable]');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertRenderedAddress($this->frenchAddress);

    // Confirm that the address book form still works.
    $this->getSession()->getPage()->pressButton('billing_edit');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->fieldValueEquals($billing_prefix . '[address][0][address][address_line1]', $this->frenchAddress['address_line1']);
    $this->submitForm([
      $billing_prefix . '[address][0][address][address_line1]' => '37 Rue du Sentier',
      $billing_prefix . '[copy_to_address_book]' => TRUE,
    ], 'Save');
    $this->assertSession()->pageTextContains('The order 2020/01 has been successfully saved.');

    $expected_address = [
      'address_line1' => '37 Rue du Sentier',
    ] + $this->frenchAddress;
    /** @var \Drupal\profile\Entity\ProfileInterface $billing_profile */
    $billing_profile = $this->reloadEntity($billing_profile);
    /** @var \Drupal\address\AddressInterface $address */
    $address = $billing_profile->get('address')->first();
    $this->assertEquals($expected_address, array_filter($address->toArray()));
    $this->assertEmpty($billing_profile->getData('copy_fields'));
    $this->assertNotEmpty($billing_profile->getData('address_book_profile_id'));

    // Confirm that the checkbox is still unchecked after the page is reloaded.
    $this->drupalGet($this->order->toUrl('edit-form'));
    $this->assertSession()->checkboxNotChecked($billing_prefix . '[copy_fields][enable]');
    $this->assertRenderedAddress($expected_address);
  }

  /**
   * Tests the admin UI with additional billing fields.
   */
  public function testAdminWithFields() {
    $billing_prefix = 'billing_profile[0][profile]';
    // Expose the tax_number field on the default form mode.
    // commerce_shipping_entity_form_display_alter() will hide it for shipping.
    $form_display = commerce_get_entity_display('profile', 'customer', 'form');
    $form_display->setComponent('tax_number', [
      'type' => 'commerce_tax_number_default',
    ]);
    $form_display->save();

    $shipping_profile = $this->shippingOrderManager->createProfile($this->order, [
      'address' => $this->frenchAddress,
    ]);
    $shipping_profile->save();
    $shipments = $this->shippingOrderManager->pack($this->order, $shipping_profile);
    $this->order->set('shipments', $shipments);
    $this->order->save();

    // Confirm that the tax_number field is shown when copying is enabled.
    $this->drupalGet($this->order->toUrl('edit-form'));
    $this->assertSession()->checkboxChecked($billing_prefix . '[copy_fields][enable]');
    $this->assertSession()->fieldExists($billing_prefix . '[copy_fields][tax_number][0][value]');
    $this->assertSession()->fieldNotExists($billing_prefix . '[address][0][address][address_line1]');
    $this->assertSession()->fieldNotExists($billing_prefix . '[copy_to_address_book]');

    // Confirm that validation is performed, based on the
    // shipping profile's country code (FR).
    $this->submitForm([
      $billing_prefix . '[copy_fields][tax_number][0][value]' => 'ABC123456',
    ], 'Save');
    $this->assertSession()->pageTextContains('Tax number is not in the right format. Examples: DE123456789, HU12345678.');

    // Confirm that the tax_number value is saved on the billing profile.
    $this->submitForm([
      $billing_prefix . '[copy_fields][tax_number][0][value]' => 'FR40303265045',
    ], 'Save');
    $this->assertSession()->pageTextContains('The order 2020/01 has been successfully saved.');
    $this->order = $this->reloadEntity($this->order);
    $billing_profile = $this->order->getBillingProfile();
    /** @var \Drupal\address\AddressInterface $address */
    $address = $billing_profile->get('address')->first();
    $this->assertEquals($this->frenchAddress, array_filter($address->toArray()));
    $this->assertEquals('FR40303265045', $billing_profile->get('tax_number')->value);
    $this->assertNotEmpty($billing_profile->getData('copy_fields'));
    $this->assertEmpty($billing_profile->getData('address_book_profile_id'));

    // Confirm that the tax_number value is available on the edit form.
    $this->drupalGet($this->order->toUrl('edit-form'));
    $this->assertSession()->fieldValueEquals($billing_prefix . '[copy_fields][tax_number][0][value]', 'FR40303265045');
    $this->assertSession()->checkboxChecked($billing_prefix . '[copy_fields][enable]');

    $this->getSession()->getPage()->uncheckField($billing_prefix . '[copy_fields][enable]');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertRenderedAddress($this->frenchAddress);
    $this->assertSession()->pageTextContains('FR40303265045');
    $this->getSession()->getPage()->pressButton('billing_edit');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Confirm that the tax_number can be edited via the address book form.
    $this->assertSession()->fieldValueEquals($billing_prefix . '[tax_number][0][value]', 'FR40303265045');
    $this->submitForm([
      $billing_prefix . '[tax_number][0][value]' => 'FRK7399859412',
    ], 'Save');
    $this->assertSession()->pageTextContains('The order 2020/01 has been successfully saved.');
    /** @var \Drupal\profile\Entity\ProfileInterface $billing_profile */
    $billing_profile = $this->reloadEntity($billing_profile);
    /** @var \Drupal\address\AddressInterface $address */
    $address = $billing_profile->get('address')->first();
    $this->assertEquals($this->frenchAddress, array_filter($address->toArray()));
    $this->assertEquals('FRK7399859412', $billing_profile->get('tax_number')->value);
    $this->assertEmpty($billing_profile->getData('copy_fields'));
  }

  /**
   * Tests checkout with non-shippable products.
   */
  public function testCheckoutWithoutShipping() {
    // Switch the order item to the non-purchasable product.
    $order_items = $this->order->getItems();
    $order_item = reset($order_items);
    $order_item->set('purchased_entity', $this->secondProduct->getDefaultVariation());
    $order_item->save();

    // Confirm that shipping information is not displayed.
    $this->drupalGet(Url::fromRoute('commerce_checkout.form', [
      'commerce_order' => $this->order->id(),
    ]));
    $this->assertSession()->pageTextNotContains('Shipping information');

    $this->assertSession()->pageTextContains('Payment information');
    $billing_prefix = 'payment_information[billing_information]';
    $this->assertSession()->fieldNotExists($billing_prefix . '[copy_fields][enable]');
    $this->assertSession()->fieldExists($billing_prefix . '[address][0][address][address_line1]');
  }

  /**
   * Tests checkout.
   */
  public function testCheckout() {
    $first_address_book_profile = $this->createEntity('profile', [
      'type' => 'customer',
      'uid' => $this->adminUser->id(),
      'address' => $this->frenchAddress,
      'is_default' => TRUE,
    ]);
    $second_address_book_profile = $this->createEntity('profile', [
      'type' => 'customer',
      'uid' => $this->adminUser->id(),
      'address' => $this->usAddress,
    ]);
    $billing_prefix = 'payment_information[billing_information]';

    $this->drupalGet(Url::fromRoute('commerce_checkout.form', [
      'commerce_order' => $this->order->id(),
    ]));
    $this->assertSession()->pageTextContains('Shipping information');
    $this->assertRenderedAddress($this->frenchAddress);

    $this->assertSession()->pageTextContains('Payment information');
    $this->assertSession()->checkboxChecked($billing_prefix . '[copy_fields][enable]');
    $this->assertSession()->fieldNotExists($billing_prefix . '[address][0][address][address_line1]');
    $billing_profile = $this->order->getBillingProfile();
    $this->assertEmpty($billing_profile);

    // Confirm that the shipping fields were copied on page submit.
    $this->submitForm([], 'Continue to review');
    $this->order = $this->reloadEntity($this->order);
    $billing_profile = $this->order->getBillingProfile();
    $this->assertNotEmpty($billing_profile);
    /** @var \Drupal\address\AddressInterface $address */
    $address = $billing_profile->get('address')->first();
    $this->assertEquals($this->frenchAddress, array_filter($address->toArray()));
    $this->assertNotEmpty($billing_profile->getData('copy_fields'));
    $this->assertEmpty($billing_profile->getData('copy_to_address_book'));
    $this->assertEquals($first_address_book_profile->id(), $billing_profile->getData('address_book_profile_id'));

    // Go back, and edit the shipping profile. Confirm changes are carried over.
    $this->clickLink('Go back');
    $this->assertSession()->checkboxChecked($billing_prefix . '[copy_fields][enable]');
    $this->getSession()->getPage()->pressButton('shipping_edit');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->submitForm([
      'shipping_information[shipping_profile][address][0][address][postal_code]' => '75003',
    ], 'Continue to review');

    $expected_address = [
      'postal_code' => '75003',
    ] + $this->frenchAddress;
    $this->assertRenderedAddress($expected_address, $billing_profile->id());
    /** @var \Drupal\profile\Entity\ProfileInterface $billing_profile */
    $billing_profile = $this->reloadEntity($billing_profile);
    $address = $billing_profile->get('address')->first();
    $this->assertEquals($expected_address, array_filter($address->toArray()));
    $this->assertNotEmpty($billing_profile->getData('copy_fields'));
    $this->assertEmpty($billing_profile->getData('copy_to_address_book'));
    $this->assertEquals($first_address_book_profile->id(), $billing_profile->getData('address_book_profile_id'));

    // Confirm that copy_fields can be unchecked, showing the address book.
    $this->clickLink('Go back');
    $this->assertSession()->checkboxChecked($billing_prefix . '[copy_fields][enable]');
    $this->getSession()->getPage()->uncheckField($billing_prefix . '[copy_fields][enable]');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $options = $this->xpath('//select[@name="payment_information[billing_information][select_address]"]/option');
    $this->assertCount(4, $options);
    $this->assertEquals($first_address_book_profile->id(), $options[0]->getValue());
    $this->assertEquals($second_address_book_profile->id(), $options[1]->getValue());
    $this->assertEquals('_original', $options[2]->getValue());
    $this->assertEquals('_new', $options[3]->getValue());

    // Confirm that a different profile can be selected.
    $this->getSession()->getPage()->fillField('payment_information[billing_information][select_address]', $second_address_book_profile->id());
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->submitForm([], 'Continue to review');

    /** @var \Drupal\profile\Entity\ProfileInterface $billing_profile */
    $billing_profile = $this->reloadEntity($billing_profile);
    $address = $billing_profile->get('address')->first();
    $this->assertEquals($this->usAddress, array_filter($address->toArray()));
    $this->assertEmpty($billing_profile->getData('copy_fields'));
    $this->assertEquals($second_address_book_profile->id(), $billing_profile->getData('address_book_profile_id'));

    // Confirm that the copy_fields checkbox is still unchecked.
    $this->clickLink('Go back');
    $this->saveHtmlOutput();
    $this->assertSession()->checkboxNotChecked($billing_prefix . '[copy_fields][enable]');
    $this->assertRenderedAddress($this->usAddress, $billing_profile->id());
    // Confirm that the _original option is no longer present.
    $options = $this->xpath('//select[@name="payment_information[billing_information][select_address]"]/option');
    $this->assertCount(3, $options);
    $this->assertEquals($first_address_book_profile->id(), $options[0]->getValue());
    $this->assertEquals($second_address_book_profile->id(), $options[1]->getValue());
    $this->assertEquals('_new', $options[2]->getValue());
  }

  /**
   * Tests checkout with additional billing fields.
   */
  public function testCheckoutWithFields() {
    $first_address_book_profile = $this->createEntity('profile', [
      'type' => 'customer',
      'uid' => $this->adminUser->id(),
      'address' => $this->frenchAddress,
      'is_default' => TRUE,
    ]);
    $second_address_book_profile = $this->createEntity('profile', [
      'type' => 'customer',
      'uid' => $this->adminUser->id(),
      'address' => $this->usAddress,
    ]);
    $billing_prefix = 'payment_information[billing_information]';
    // Expose the tax_number field on the default form mode.
    // commerce_shipping_entity_form_display_alter() will hide it for shipping.
    $form_display = commerce_get_entity_display('profile', 'customer', 'form');
    $form_display->setComponent('tax_number', [
      'type' => 'commerce_tax_number_default',
    ]);
    $form_display->save();

    $this->drupalGet(Url::fromRoute('commerce_checkout.form', [
      'commerce_order' => $this->order->id(),
    ]));
    $this->assertSession()->pageTextContains('Shipping information');
    $this->assertRenderedAddress($this->frenchAddress);

    $this->assertSession()->pageTextContains('Payment information');
    $this->assertSession()->checkboxChecked($billing_prefix . '[copy_fields][enable]');
    $this->assertSession()->fieldExists($billing_prefix . '[copy_fields][tax_number][0][value]');
    $this->assertSession()->fieldNotExists($billing_prefix . '[address][0][address][address_line1]');
    $billing_profile = $this->order->getBillingProfile();
    $this->assertEmpty($billing_profile);

    // Confirm that validation is performed, based on the
    // shipping profile's country code (FR).
    $this->submitForm([
      $billing_prefix . '[copy_fields][tax_number][0][value]' => 'ABC123456',
    ], 'Continue to review');
    $this->assertSession()->pageTextContains('Tax number is not in the right format. Examples: DE123456789, HU12345678.');

    // Confirm that the shipping fields and the tax_number value
    // were copied on page submit.
    $this->submitForm([
      $billing_prefix . '[copy_fields][tax_number][0][value]' => 'FR40303265045',
    ], 'Continue to review');
    $this->order = $this->reloadEntity($this->order);
    $billing_profile = $this->order->getBillingProfile();
    $this->assertNotEmpty($billing_profile);
    /** @var \Drupal\address\AddressInterface $address */
    $address = $billing_profile->get('address')->first();
    $this->assertEquals($this->frenchAddress, array_filter($address->toArray()));
    $this->assertEquals('FR40303265045', $billing_profile->get('tax_number')->value);
    $this->assertNotEmpty($billing_profile->getData('copy_fields'));
    $this->assertEmpty($billing_profile->getData('copy_to_address_book'));
    $this->assertEquals($first_address_book_profile->id(), $billing_profile->getData('address_book_profile_id'));

    // Go back, and select the US profile for shipping.
    $this->clickLink('Go back');
    $this->assertSession()->checkboxChecked($billing_prefix . '[copy_fields][enable]');
    $this->getSession()->getPage()->fillField('shipping_information[shipping_profile][select_address]', $second_address_book_profile->id());
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->saveHtmlOutput();

    // Try to re-enter the dummy tax number. This should work because a
    // French address is no longer selected, turning off EU validation.
    $this->assertSession()->fieldValueEquals($billing_prefix . '[copy_fields][tax_number][0][value]', 'FR40303265045');
    $this->submitForm([
      $billing_prefix . '[copy_fields][tax_number][0][value]' => 'ABC123456',
    ], 'Continue to review');
    $this->assertSession()->pageTextNotContains('Tax number is not in the right format. Examples: DE123456789, HU12345678.');

    $this->assertRenderedAddress($this->usAddress, $billing_profile->id());
    /** @var \Drupal\profile\Entity\ProfileInterface $billing_profile */
    $billing_profile = $this->reloadEntity($billing_profile);
    $address = $billing_profile->get('address')->first();
    $this->assertEquals($this->usAddress, array_filter($address->toArray()));
    $this->assertEquals('ABC123456', $billing_profile->get('tax_number')->value);
    $this->assertNotEmpty($billing_profile->getData('copy_fields'));
    $this->assertEmpty($billing_profile->getData('copy_to_address_book'));
    $this->assertEquals($second_address_book_profile->id(), $billing_profile->getData('address_book_profile_id'));

    // Confirm that copy_fields can be unchecked, showing the address book.
    $this->clickLink('Go back');
    $this->assertSession()->checkboxChecked($billing_prefix . '[copy_fields][enable]');
    $this->getSession()->getPage()->uncheckField($billing_prefix . '[copy_fields][enable]');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertRenderedAddress($this->usAddress, $billing_profile->id());
    $this->assertSession()->pageTextContains('ABC123456');

    // Confirm that the profile can be edited.
    $this->getSession()->getPage()->pressButton('billing_edit');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->fieldValueEquals($billing_prefix . '[tax_number][0][value]', 'ABC123456');
    $this->submitForm([
      $billing_prefix . '[tax_number][0][value]' => 'ABC987',
    ], 'Continue to review');
    /** @var \Drupal\profile\Entity\ProfileInterface $billing_profile */
    $billing_profile = $this->reloadEntity($billing_profile);
    $address = $billing_profile->get('address')->first();
    $this->assertEquals($this->usAddress, array_filter($address->toArray()));
    $this->assertEquals('ABC987', $billing_profile->get('tax_number')->value);
    $this->assertEmpty($billing_profile->getData('copy_fields'));
    $this->assertEmpty($billing_profile->getData('copy_to_address_book'));
    $this->assertEquals($second_address_book_profile->id(), $billing_profile->getData('address_book_profile_id'));
  }

  /**
   * Tests checkout with multiple payment gateways.
   */
  public function testCheckoutWithMultipleGateways() {
    /** @var \Drupal\commerce_payment\Entity\PaymentGatewayInterface $onsite_gateway */
    $onsite_gateway = PaymentGateway::create([
      'id' => 'onsite',
      'label' => 'On-site',
      'plugin' => 'example_onsite',
      'configuration' => [
        'api_key' => '2342fewfsfs',
        'payment_method_types' => ['credit_card'],
      ],
    ]);
    $onsite_gateway->save();

    $address_book_profile = $this->createEntity('profile', [
      'type' => 'customer',
      'uid' => $this->adminUser->id(),
      'address' => $this->frenchAddress,
      'is_default' => TRUE,
    ]);

    $this->drupalGet(Url::fromRoute('commerce_checkout.form', [
      'commerce_order' => $this->order->id(),
    ]));
    $this->assertSession()->pageTextContains('Shipping information');
    $this->assertRenderedAddress($this->frenchAddress);

    $this->assertSession()->pageTextContains('Payment information');
    $this->assertSession()->checkboxChecked('payment_information[billing_information][copy_fields][enable]');
    $this->assertSession()->fieldNotExists('payment_information[billing_information][address][0][address][address_line1]');
    // Confirm that the copy_fields checkbox is still checked after selecting
    // a different payment option ("Credit card", in this case).
    $this->getSession()->getPage()->selectFieldOption('payment_information[payment_method]', 'new--credit_card--onsite');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $billing_prefix = 'payment_information[add_payment_method][billing_information]';
    $this->assertSession()->checkboxChecked($billing_prefix . '[copy_fields][enable]');

    $billing_profile = $this->order->getBillingProfile();
    $this->assertEmpty($billing_profile);
    // Confirm that the shipping fields were copied on page submit.
    $this->submitForm([
      'payment_information[add_payment_method][payment_details][security_code]' => '123',
    ], 'Continue to review');
    $this->order = $this->reloadEntity($this->order);
    /** @var \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method */
    $payment_method = $this->order->get('payment_method')->entity;
    $this->assertNotEmpty($payment_method);
    $payment_method_profile = $payment_method->getBillingProfile();
    $this->assertNotEmpty($payment_method_profile);
    $billing_profile = $this->order->getBillingProfile();
    $this->assertNotEmpty($billing_profile);
    $this->assertTrue($payment_method_profile->equalToProfile($billing_profile));
    /** @var \Drupal\address\AddressInterface $address */
    $address = $billing_profile->get('address')->first();
    $this->assertEquals($this->frenchAddress, array_filter($address->toArray()));
    $this->assertNotEmpty($billing_profile->getData('copy_fields'));
    $this->assertEmpty($billing_profile->getData('copy_to_address_book'));
    $this->assertEquals($address_book_profile->id(), $billing_profile->getData('address_book_profile_id'));
  }

  /**
   * Asserts that the given address is rendered on the page.
   *
   * @param array $address
   *   The address.
   * @param string $profile_id
   *   The parent profile ID.
   */
  protected function assertRenderedAddress(array $address, $profile_id = NULL) {
    $parent_class = $profile_id ? '.profile--' . $profile_id : '.profile';
    $page = $this->getSession()->getPage();
    $address_text = $page->find('css', $parent_class . ' p.address')->getText();
    foreach ($address as $property => $value) {
      if ($property == 'country_code') {
        $value = $this->countryList[$value];
      }
      $this->assertContains($value, $address_text);
    }
  }

}
