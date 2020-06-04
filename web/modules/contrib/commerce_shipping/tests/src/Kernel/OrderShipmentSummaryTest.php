<?php

namespace Drupal\Tests\commerce_shipping\Kernel;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\profile\Entity\Profile;
use Drupal\commerce_shipping\Entity\Shipment;
use Drupal\commerce_shipping\Entity\ShippingMethod;
use Drupal\commerce_shipping\ShipmentItem;
use Drupal\physical\Weight;
use Drupal\Core\Entity\Entity\EntityViewDisplay;

/**
 * Tests the order shipment summary.
 *
 * @group commerce_shipping
 */
class OrderShipmentSummaryTest extends ShippingKernelTestBase {

  /**
   * A sample order.
   *
   * @var \Drupal\commerce_order\Entity\OrderInterface
   */
  protected $order;

  /**
   * A sample shipment.
   *
   * @var \Drupal\commerce_shipping\Entity\ShipmentInterface
   */
  protected $shipment;

  /**
   * Order shipment summary.
   *
   * @var \Drupal\commerce_shipping\OrderShipmentSummaryInterface
   */
  protected $orderShipmentSummary;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->orderShipmentSummary = $this->container->get('commerce_shipping.order_shipment_summary');

    $user = $this->createUser(['mail' => $this->randomString() . '@example.com']);

    $product = Product::create([
      'type' => 'default',
      'title' => 'Default testing product',
    ]);
    $product->save();

    $variation1 = ProductVariation::create([
      'type' => 'default',
      'sku' => 'TEST_' . strtolower($this->randomMachineName()),
      'title' => $this->randomString(),
      'status' => 1,
      'price' => new Price('12.00', 'USD'),
    ]);
    $variation1->save();
    $product->addVariation($variation1)->save();

    $profile = Profile::create([
      'type' => 'customer',
      'uid' => $user->id(),
      'address' => [
        'country_code' => 'US',
        'administrative_area' => 'CA',
        'locality' => 'Mountain View',
        'postal_code' => '94043',
        'address_line1' => '1098 Alta Ave',
        'organization' => 'Google Inc.',
        'given_name' => 'John',
        'family_name' => 'Smith',
      ],
    ]);
    $profile->save();
    $profile = $this->reloadEntity($profile);

    $order_item = OrderItem::create([
      'type' => 'test',
      'quantity' => 1,
      'unit_price' => new Price('12.00', 'USD'),
    ]);
    $order_item->save();

    /** @var \Drupal\commerce_order\Entity\Order $order */
    $order = Order::create([
      'type' => 'default',
      'mail' => $user->getEmail(),
      'uid' => $user->id(),
      'ip_address' => '127.0.0.1',
      'order_number' => '6',
      'billing_profile' => $profile,
      'store_id' => $this->store->id(),
      'state' => 'completed',
      'order_items' => [$order_item],
    ]);

    $order->save();
    $this->order = $this->reloadEntity($order);

    $shipping_method = ShippingMethod::create([
      'stores' => $this->store->id(),
      'name' => 'Example',
      'plugin' => [
        'target_plugin_id' => 'flat_rate',
        'target_plugin_configuration' => [
          'rate_label' => 'Flat rate',
          'rate_amount' => new Price('1', 'USD'),
        ],
      ],
      'status' => TRUE,
      'weight' => 1,
    ]);
    $shipping_method->save();

    $shipment = Shipment::create([
      'type' => 'default',
      'order_id' => $this->order->id(),
      'title' => 'Shipment',
      'shipping_method' => $shipping_method,
      'shipping_profile' => $profile,
      'tracking_code' => 'ABC123',
      'items' => [
        new ShipmentItem([
          'order_item_id' => 1,
          'title' => 'T-shirt (red, large)',
          'quantity' => 2,
          'weight' => new Weight('40', 'kg'),
          'declared_value' => new Price('30', 'USD'),
        ]),
      ],
      'amount' => new Price('5', 'USD'),
      'state' => 'draft',
    ]);
    $shipment->save();
    $this->shipment = $this->reloadEntity($shipment);

    $this->order->set('shipments', [$shipment]);
    $this->order->save();
  }

  /**
   * Tests the order shipment summary build method.
   */
  public function testBuild() {
    // User display rendered by default.
    $build = $this->orderShipmentSummary->build($this->order);
    $this->render($build);
    $this->assertUniqueText('John');

    // Default view mode includes shipping profile and state (as transition form).
    $default_view_display = EntityViewDisplay::load('commerce_shipment.default.default');
    $this->assertNotEmpty($default_view_display);
    // Change transition form to list_default formatter.
    $default_view_display->setComponent('state', [
      'label' => 'hidden',
      'type' => 'list_default',
      'weight' => 7,
      'region' => 'content',
      'settings' => [],
      'third_party_settings' => [],
    ]);
    $default_view_display->save();
    $this->assertNotNull($default_view_display->getComponent('state'));
    $build = $this->orderShipmentSummary->build($this->order, 'default');

    $this->render($build);
    $this->assertUniqueText('John');
    $this->assertText('ABC123');
    $this->assertText('Draft');
  }

}
