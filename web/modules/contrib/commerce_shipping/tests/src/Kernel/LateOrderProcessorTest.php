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
 * Tests the late order processor.
 *
 * @coversDefaultClass \Drupal\commerce_shipping\LateOrderProcessor
 * @group commerce_shipping
 */
class LateOrderProcessorTest extends ShippingKernelTestBase {

  /**
   * The sample order.
   *
   * @var \Drupal\commerce_order\Entity\OrderInterface
   */
  protected $order;

  /**
   * The order refresh processor.
   *
   * @var \Drupal\commerce_shipping\EarlyOrderProcessor
   */
  protected $processor;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->processor = $this->container->get('commerce_shipping.late_order_processor');

    $variation = ProductVariation::create([
      'type' => 'default',
      'sku' => 'test-product-01',
      'title' => 'Hat',
      'price' => new Price('10', 'USD'),
      'weight' => new Weight('0', 'g'),
    ]);
    $variation->save();

    $first_order_item = OrderItem::create([
      'type' => 'default',
      'quantity' => 2,
      'title' => $variation->getOrderItemTitle(),
      'purchased_entity' => $variation,
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
    $order->set('shipments', $shipments);
    $order->setRefreshState(Order::REFRESH_SKIP);
    $order->save();
    $this->order = $order;
  }

  /**
   * ::covers process.
   */
  public function testProcess() {
    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface[] $shipments */
    $shipments = $this->order->get('shipments')->referencedEntities();

    $shipment = reset($shipments);
    $shipment->setAmount(new Price('3.33', 'USD'));
    $shipment->addAdjustment(new Adjustment([
      'type' => 'fee',
      'label' => 'Random fee',
      'amount' => new Price('2.00', 'USD'),
      'locked' => TRUE,
    ]));

    $this->processor->process($this->order);
    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
    $shipment = $this->reloadEntity($shipment);
    // Confirm that modified shipment was saved.
    $this->assertFalse($shipment->hasTranslationChanges());

    // Confirm that the amount and adjustments were transferred to the order.
    $adjustments = $this->order->getAdjustments();
    $this->assertCount(2, $adjustments);
    $first_adjustment = reset($adjustments);
    $this->assertEquals('shipping', $first_adjustment->getType());
    $this->assertEquals(new Price('3.33', 'USD'), $first_adjustment->getAmount());
    $second_adjustment = end($adjustments);
    $this->assertEquals('fee', $second_adjustment->getType());
    $this->assertEquals(new Price('2.00', 'USD'), $second_adjustment->getAmount());
    // Confirm that locked adjustments are transferred unlocked.
    $this->assertFalse($second_adjustment->isLocked());
  }

}
