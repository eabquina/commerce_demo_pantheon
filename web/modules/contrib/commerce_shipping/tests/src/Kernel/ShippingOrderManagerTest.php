<?php

namespace Drupal\Tests\commerce_shipping\Kernel;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\commerce_shipping\Entity\Shipment;
use Drupal\commerce_shipping\Entity\ShipmentType;
use Drupal\commerce_shipping\Entity\ShippingMethod;
use Drupal\commerce_shipping\ShipmentItem;
use Drupal\physical\Weight;
use Drupal\profile\Entity\Profile;
use Drupal\profile\Entity\ProfileType;

/**
 * Tests the shipping order manager.
 *
 * @coversDefaultClass \Drupal\commerce_shipping\ShippingOrderManager
 * @group commerce_shipping
 */
class ShippingOrderManagerTest extends ShippingKernelTestBase {

  /**
   * A non shippable order.
   *
   * @var \Drupal\commerce_order\Entity\OrderInterface
   */
  protected $nonShippableOrder;

  /**
   * A shippable order.
   *
   * @var \Drupal\commerce_order\Entity\OrderInterface
   */
  protected $shippableOrder;

  /**
   * A shipping method.
   *
   * @var \Drupal\commerce_shipping\Entity\ShippingMethodInterface
   */
  protected $shippingMethod;

  /**
   * The shipping order manager.
   *
   * @var \Drupal\commerce_shipping\ShippingOrderManagerInterface
   */
  protected $shippingOrderManager;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

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
    $this->shippingMethod = $this->reloadEntity($shipping_method);

    $order_item = OrderItem::create([
      'type' => 'test',
      'quantity' => 1,
      'unit_price' => new Price('12.00', 'USD'),
    ]);
    $order_item->save();
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = Order::create([
      'type' => 'default',
      'state' => 'draft',
      'store_id' => $this->store->id(),
      'order_items' => [$order_item],
    ]);
    $order->save();
    $this->nonShippableOrder = $this->reloadEntity($order);

    $variation = ProductVariation::create([
      'type' => 'default',
      'sku' => 'test-product-02',
      'title' => 'Mug',
      'price' => new Price('10', 'USD'),
    ]);
    $variation->save();
    $another_order_item = OrderItem::create([
      'type' => 'default',
      'quantity' => 2,
      'title' => $variation->getOrderItemTitle(),
      'purchased_entity' => $variation,
      'unit_price' => new Price('10', 'USD'),
    ]);
    $another_order_item->save();
    /** @var \Drupal\commerce_order\Entity\OrderInterface $another_order */
    $another_order = Order::create([
      'type' => 'default',
      'state' => 'draft',
      'store_id' => $this->store->id(),
      'order_items' => [$another_order_item],
    ]);
    $another_order->save();
    $this->shippableOrder = $this->reloadEntity($another_order);

    $this->shippingOrderManager = $this->container->get('commerce_shipping.order_manager');
  }

  /**
   * @covers ::createProfile
   */
  public function testCreateProfile() {
    $profile = $this->shippingOrderManager->createProfile($this->shippableOrder);
    $this->assertEquals('customer', $profile->bundle());
    $profile_type = ProfileType::create([
      'id' => 'customer_shipping',
      'label' => $this->randomString(),
    ]);
    $profile_type->setThirdPartySetting('commerce_order', 'customer_profile_type', TRUE);
    $profile_type->save();
    $shipment_type = ShipmentType::load('default');
    $shipment_type->setProfileTypeId('customer_shipping');
    $shipment_type->save();

    $profile = $this->shippingOrderManager->createProfile($this->shippableOrder);
    $this->assertEquals('customer_shipping', $profile->bundle());
  }

  /**
   * @covers ::getProfile
   */
  public function testGetProfile() {
    $shipping_profile = Profile::create([
      'type' => 'customer',
      'address' => [
        'country_code' => 'FR',
      ],
    ]);
    $shipping_profile->save();
    $shipping_profile = $this->reloadEntity($shipping_profile);

    $shipment = Shipment::create([
      'type' => 'default',
      'order_id' => $this->shippableOrder->id(),
      'title' => 'Shipment',
      'shipping_method' => $this->shippingMethod,
      'shipping_profile' => $shipping_profile,
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

    $profile = $this->shippingOrderManager->getProfile($this->nonShippableOrder);
    $this->assertNull($profile);

    $this->shippableOrder->set('shipments', [$shipment]);
    $this->shippableOrder->save();
    $profile = $this->shippingOrderManager->getProfile($this->shippableOrder);
    $this->assertEquals($shipping_profile->id(), $profile->id());
  }

  /**
   * @covers ::hasShipments
   */
  public function testHasShipments() {
    $this->assertFalse($this->shippingOrderManager->hasShipments($this->nonShippableOrder));
    $this->assertFalse($this->shippingOrderManager->hasShipments($this->shippableOrder));
    $shipping_profile = Profile::create([
      'type' => 'customer',
      'address' => [
        'country_code' => 'FR',
      ],
    ]);
    $shipping_profile->save();
    $shipments = $this->shippingOrderManager->pack($this->shippableOrder, $shipping_profile);
    $this->shippableOrder->set('shipments', $shipments);
    $this->assertTrue($this->shippingOrderManager->hasShipments($this->shippableOrder));
    $this->shippableOrder->set('shipments', []);
    $this->assertFalse($this->shippingOrderManager->hasShipments($this->shippableOrder));
  }

  /**
   * @covers ::isShippable
   */
  public function testIsShippable() {
    $this->assertFalse($this->shippingOrderManager->isShippable($this->nonShippableOrder));
    $this->assertTrue($this->shippingOrderManager->isShippable($this->shippableOrder));
  }

  /**
   * @covers ::pack
   */
  public function testPack() {
    $shipping_profile = Profile::create([
      'type' => 'customer',
      'address' => [
        'country_code' => 'FR',
      ],
    ]);
    $shipping_profile->save();
    $shipping_profile = $this->reloadEntity($shipping_profile);

    $shipments = $this->shippingOrderManager->pack($this->shippableOrder, $shipping_profile);
    $this->assertCount(1, $shipments);
    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
    $shipment = $shipments[0];
    $this->assertEquals('Mug', $shipment->getItems()[0]->getTitle());
    $this->assertTrue($shipment->getData('owned_by_packer'));
    $this->assertEquals($shipping_profile, $shipment->getShippingProfile());
    $this->shippableOrder->set('shipments', $shipments);

    $variation = ProductVariation::create([
      'type' => 'default',
      'sku' => 'test-product-01',
      'title' => 'Hat',
      'price' => new Price('10', 'USD'),
    ]);
    $variation->save();
    $order_item = OrderItem::create([
      'type' => 'default',
      'quantity' => 2,
      'title' => $variation->getOrderItemTitle(),
      'purchased_entity' => $variation,
      'unit_price' => new Price('10', 'USD'),
    ]);
    $order_item->save();
    $this->shippableOrder->addItem($order_item);

    // The first shipment should be reused, and a second one created.
    $shipment_id = $shipment->id();
    $shipments = $this->shippingOrderManager->pack($this->shippableOrder, $shipping_profile);
    $this->assertCount(2, $shipments);
    $first_shipment = $shipments[0];
    $this->assertEquals($shipment_id, $first_shipment->id());
    $this->assertEquals('Mug', $first_shipment->getItems()[0]->getTitle());
    $this->assertTrue($first_shipment->getData('owned_by_packer'));
    $second_shipment = $shipments[1];
    $this->assertEquals('Hat', $second_shipment->getItems()[0]->getTitle());
    $this->assertTrue($second_shipment->getData('owned_by_packer'));

    $shipments = $this->shippingOrderManager->pack($this->nonShippableOrder);
    $this->assertEmpty($shipments);
  }

}
