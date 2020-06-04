<?php

namespace Drupal\Tests\commerce_shipping\Kernel;

use Drupal\commerce_order\Adjustment;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_price\Price;
use Drupal\physical\Weight;
use Drupal\profile\Entity\Profile;

/**
 * Tests the early order processor.
 *
 * @coversDefaultClass \Drupal\commerce_shipping\EarlyOrderProcessor
 * @group commerce_shipping
 */
class EarlyOrderProcessorTest extends ShippingKernelTestBase {

  /**
   * The sample product variations.
   *
   * @var \Drupal\commerce_product\Entity\ProductVariationInterface[]
   */
  protected $variations = [];

  /**
   * The sample order.
   *
   * @var \Drupal\commerce_order\Entity\OrderInterface
   */
  protected $order;

  /**
   * A sample shipping profile.
   *
   * @var \Drupal\profile\Entity\ProfileInterface
   */
  protected $shippingProfile;

  /**
   * The order refresh processor.
   *
   * @var \Drupal\commerce_shipping\LateOrderProcessor
   */
  protected $processor;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'commerce_checkout',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->processor = $this->container->get('commerce_shipping.early_order_processor');

    $this->variations[] = ProductVariation::create([
      'type' => 'default',
      'sku' => 'test-product-01',
      'title' => 'Hat',
      'price' => new Price('10', 'USD'),
      'weight' => new Weight('0', 'g'),
    ]);
    $this->variations[] = ProductVariation::create([
      'type' => 'default',
      'sku' => 'test-product-02',
      'title' => 'Mug',
      'price' => new Price('10', 'USD'),
      'weight' => new Weight('0', 'g'),
    ]);
    $this->variations[0]->save();
    $this->variations[1]->save();

    $first_order_item = OrderItem::create([
      'type' => 'default',
      'quantity' => 2,
      'title' => $this->variations[0]->getOrderItemTitle(),
      'purchased_entity' => $this->variations[0],
      'unit_price' => new Price('10', 'USD'),
    ]);
    $first_order_item->save();
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = Order::create([
      'type' => 'default',
      'uid' => $this->createUser(['mail' => $this->randomString() . '@example.com']),
      'store_id' => $this->store->id(),
      'order_items' => [$first_order_item],
    ]);
    $order->save();
    /** @var \Drupal\profile\Entity\ProfileInterface $shipping_profile */
    $shipping_profile = Profile::create([
      'type' => 'customer',
      'address' => [
        'country_code' => 'FR',
      ],
    ]);
    $shipping_profile->save();

    // Create the first shipment.
    $shipping_order_manager = $this->container->get('commerce_shipping.order_manager');
    $shipments = $shipping_order_manager->pack($order, $shipping_profile);
    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
    $shipment = reset($shipments);
    $shipment->setOriginalAmount(new Price('4', 'USD'));
    $shipment->setAmount(new Price('6', 'USD'));
    $shipment->addAdjustment(new Adjustment([
      'type' => 'fee',
      'label' => 'Handling fee',
      'amount' => new Price('2.00', 'USD'),
      'included' => TRUE,
    ]));
    $shipment->save();

    $order->set('shipments', [$shipment]);
    $order->setRefreshState(Order::REFRESH_SKIP);
    $order->save();
    $this->order = $order;
    $this->shippingProfile = $shipping_profile;
  }

  /**
   * @covers ::process
   * @covers ::shouldRepack
   */
  public function testProcess() {
    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface[] $shipments */
    $shipments = $this->order->get('shipments')->referencedEntities();
    $this->assertCount(1, $shipments);

    // Repack on adding an order item.
    $second_order_item = OrderItem::create([
      'type' => 'default',
      'quantity' => 2,
      'title' => $this->variations[1]->getOrderItemTitle(),
      'purchased_entity' => $this->variations[1],
      'unit_price' => new Price('10', 'USD'),
    ]);
    $second_order_item->save();
    $this->order->addItem($second_order_item);
    $this->processor->process($this->order);
    $shipments = $this->order->get('shipments')->referencedEntities();

    // Confirm that the first shipment's amount and adjustments were reset.
    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $first_shipment */
    $first_shipment = reset($shipments);
    $this->assertEquals(new Price('4', 'USD'), $first_shipment->getOriginalAmount());
    $this->assertEquals(new Price('4', 'USD'), $first_shipment->getAmount());
    $this->assertEmpty($first_shipment->getAdjustments());

    // Confirm that an additional shipment was created.
    $this->assertCount(2, $shipments);
    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $second_shipment */
    $second_shipment = end($shipments);
    $this->assertNull($second_shipment->getOriginalAmount());
    $this->assertNull($second_shipment->getAmount());
    $this->assertEmpty($second_shipment->getAdjustments());

    // No repack when the checkout page changed but the order items didn't.
    // The country change makes the DefaultPacker take over from TestPacker,
    // resulting in a single shipment.
    $this->shippingProfile->get('address')->country_code = 'RS';
    $this->shippingProfile->save();
    $this->order->original = clone $this->order;
    $this->order->set('checkout_step', 'review');
    $this->processor->process($this->order);
    $this->assertCount(2, $this->order->get('shipments')->referencedEntities());

    // Repack when the checkout page changed but so did the order items.
    $this->order->original = clone $this->order;
    $this->order->original->set('checkout_step', 'order_information');
    $this->order->removeItem($second_order_item);
    $this->order->set('checkout_step', 'review');
    $this->processor->process($this->order);
    $this->assertCount(1, $this->order->get('shipments')->referencedEntities());
  }

  /**
   * Test the edge case when the shipping profile has been deleted.
   *
   * @covers ::process
   */
  public function testProcessWithoutProfile() {
    $this->assertCount(1, $this->order->get('shipments')->referencedEntities());

    // Add an item to trigger shouldRepack.
    $order_item = OrderItem::create([
      'type' => 'default',
      'quantity' => 2,
      'title' => $this->variations[1]->getOrderItemTitle(),
      'purchased_entity' => $this->variations[1],
      'unit_price' => new Price('10', 'USD'),
    ]);
    $order_item->save();
    $this->order->addItem($order_item);

    $this->shippingProfile->delete();
    $this->processor->process($this->order);
    // Confirm that the shipment has been deleted.
    $this->assertEmpty($this->order->get('shipments')->referencedEntities());
  }

}
