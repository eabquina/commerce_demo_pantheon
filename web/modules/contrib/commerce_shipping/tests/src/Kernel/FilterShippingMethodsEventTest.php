<?php

namespace Drupal\Tests\commerce_shipping\Kernel;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_price\Price;
use Drupal\commerce_shipping\Entity\Shipment;
use Drupal\commerce_shipping\Entity\ShippingMethod;
use Drupal\commerce_shipping\ShipmentItem;
use Drupal\physical\Weight;

/**
 * Tests the FilterShippingMethodsEvent.
 *
 * @coversDefaultClass \Drupal\commerce_shipping\Event\FilterShippingMethodsEvent
 * @group commerce_shipping
 */
class FilterShippingMethodsEventTest extends ShippingKernelTestBase {

  /**
   * The shipping method storage.
   *
   * @var \Drupal\commerce_shipping\ShippingMethodStorageInterface
   */
  protected $storage;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->storage = $this->container->get('entity_type.manager')->getStorage('commerce_shipping_method');
  }

  /**
   * Tests that the shipping method is removed.
   */
  public function testEvent() {
    $shipping_method_example = ShippingMethod::create([
      'name' => 'Example',
      'plugin' => [
        'target_plugin_id' => 'flat_rate',
        'target_plugin_configuration' => [],
      ],
      'status' => 1,
      'stores' => $this->store->id(),
    ]);
    $shipping_method_example->save();
    $shipping_method_filtered = ShippingMethod::create([
      'name' => 'Example (Filtered)',
      'plugin' => [
        'target_plugin_id' => 'flat_rate',
        'target_plugin_configuration' => [],
      ],
      'status' => 1,
      'stores' => $this->store->id(),
    ]);
    $shipping_method_filtered->save();

    $user = $this->createUser(['mail' => $this->randomString() . '@example.com']);
    $order = Order::create([
      'type' => 'default',
      'state' => 'draft',
      'mail' => $user->getEmail(),
      'uid' => $user->id(),
      'store_id' => $this->store->id(),
    ]);
    $order->save();

    $shipment = Shipment::create([
      'type' => 'default',
      'order_id' => $order->id(),
      'shipping_method' => $shipping_method_filtered,
      'title' => 'Shipment',
      'amount' => new Price('10.00', 'USD'),
      'items' => [
        new ShipmentItem([
          'order_item_id' => 10,
          'title' => 'T-shirt (red, large)',
          'quantity' => 1,
          'weight' => new Weight('10', 'kg'),
          'declared_value' => new Price('15', 'USD'),
        ]),
      ],
    ]);
    $shipment->save();

    $available_methods = $this->storage->loadMultipleForShipment($shipment);
    $this->assertEquals(2, count($available_methods));
    $method = array_shift($available_methods);
    $this->assertEquals($shipping_method_example->label(), $method->label());
    $method = array_shift($available_methods);
    $this->assertEquals($shipping_method_filtered->label(), $method->label());

    $shipment->setData('excluded_methods', [$shipping_method_filtered->id()]);

    $available_methods = $this->storage->loadMultipleForShipment($shipment);
    $this->assertEquals(1, count($available_methods));
    $method = array_shift($available_methods);
    $this->assertEquals($shipping_method_example->label(), $method->label());
  }

}
