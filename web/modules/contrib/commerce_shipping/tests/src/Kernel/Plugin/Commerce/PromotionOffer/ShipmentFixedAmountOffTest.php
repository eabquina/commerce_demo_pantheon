<?php

namespace Drupal\Tests\commerce_shipping\Kernel\Plugin\Commerce\PromotionOffer;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\commerce_promotion\Entity\Promotion;
use Drupal\commerce_shipping\Entity\ShippingMethod;
use Drupal\physical\Weight;
use Drupal\profile\Entity\Profile;
use Drupal\Tests\commerce_shipping\Kernel\ShippingKernelTestBase;

/**
 * Tests the "Fixed amount off the shipment amount" offer.
 *
 * @coversDefaultClass \Drupal\commerce_shipping\Plugin\Commerce\PromotionOffer\ShipmentFixedAmountOff
 * @group commerce_shipping
 */
class ShipmentFixedAmountOffTest extends ShippingKernelTestBase {

  /**
   * The sample order.
   *
   * @var \Drupal\commerce_order\Entity\OrderInterface
   */
  protected $order;

  /**
   * The test promotion.
   *
   * @var \Drupal\commerce_promotion\Entity\PromotionInterface
   */
  protected $promotion;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'commerce_promotion',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('commerce_promotion');

    $first_variation = ProductVariation::create([
      'type' => 'default',
      'sku' => 'test-product-01',
      'title' => 'Hat',
      'price' => new Price('10.00', 'USD'),
      'weight' => new Weight('0', 'g'),
    ]);
    $first_variation->save();

    $second_variation = ProductVariation::create([
      'type' => 'default',
      'sku' => 'test-product-02',
      'title' => 'Mug',
      'price' => new Price('10.00', 'USD'),
      'weight' => new Weight('0', 'g'),
    ]);
    $second_variation->save();

    $first_order_item = OrderItem::create([
      'type' => 'default',
      'quantity' => 1,
      'title' => $first_variation->getOrderItemTitle(),
      'purchased_entity' => $first_variation,
      'unit_price' => new Price('10.00', 'USD'),
    ]);
    $first_order_item->save();

    $second_order_item = OrderItem::create([
      'type' => 'default',
      'quantity' => 1,
      'title' => $second_variation->getOrderItemTitle(),
      'purchased_entity' => $second_variation,
      'unit_price' => new Price('10.00', 'USD'),
    ]);
    $second_order_item->save();

    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = Order::create([
      'type' => 'default',
      'uid' => $this->createUser(['mail' => $this->randomString() . '@example.com']),
      'store_id' => $this->store->id(),
      'order_items' => [$first_order_item, $second_order_item],
    ]);
    $order->save();

    $first_shipping_method = ShippingMethod::create([
      'stores' => $this->store->id(),
      'name' => 'Standard shipping',
      'plugin' => [
        'target_plugin_id' => 'flat_rate',
        'target_plugin_configuration' => [
          'rate_label' => 'Standard shipping',
          'rate_amount' => [
            'number' => '5',
            'currency_code' => 'USD',
          ],
        ],
      ],
      'status' => TRUE,
    ]);
    $first_shipping_method->save();

    $second_shipping_method = ShippingMethod::create([
      'stores' => $this->store->id(),
      'name' => 'Express shipping',
      'plugin' => [
        'target_plugin_id' => 'flat_rate',
        'target_plugin_configuration' => [
          'rate_label' => 'Express shipping',
          'rate_amount' => [
            'number' => '20',
            'currency_code' => 'USD',
          ],
        ],
      ],
      'status' => TRUE,
    ]);
    $second_shipping_method->save();

    /** @var \Drupal\profile\Entity\ProfileInterface $shipping_profile */
    $shipping_profile = Profile::create([
      'type' => 'customer',
      'address' => [
        'country_code' => 'FR',
      ],
    ]);
    $shipping_profile->save();

    $shipping_order_manager = $this->container->get('commerce_shipping.order_manager');
    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface[] $shipments */
    $shipments = $shipping_order_manager->pack($order, $shipping_profile);
    $first_shipment = reset($shipments);
    $first_shipment->setShippingMethodId($first_shipping_method->id());
    $first_shipment->setShippingService('default');
    $first_shipment->setOriginalAmount(new Price('5.00', 'USD'));
    $first_shipment->setAmount(new Price('5.00', 'USD'));
    $second_shipment = end($shipments);
    $second_shipment->setShippingMethodId($second_shipping_method->id());
    $second_shipment->setShippingService('default');
    $second_shipment->setOriginalAmount(new Price('20.00', 'USD'));
    $second_shipment->setAmount(new Price('20.00', 'USD'));
    $order->set('shipments', [$first_shipment, $second_shipment]);
    $order->setRefreshState(Order::REFRESH_SKIP);
    $order->save();
    $this->order = $order;

    $this->promotion = Promotion::create([
      'name' => 'Promotion 1',
      'order_types' => [$this->order->bundle()],
      'stores' => [$this->store->id()],
      'offer' => [
        'target_plugin_id' => 'shipment_fixed_amount_off',
        'target_plugin_configuration' => [
          'display_inclusive' => TRUE,
          'amount' => [
            'number' => '11.00',
            'currency_code' => 'USD',
          ],
        ],
      ],
      'status' => TRUE,
    ]);
    $this->promotion->save();
  }

  /**
   * Tests the display-inclusive offer.
   *
   * @covers ::applyToShipment
   */
  public function testDisplayInclusive() {
    $this->assertCount(0, $this->order->collectAdjustments());
    $this->assertEquals(new Price('20.00', 'USD'), $this->order->getTotalPrice());
    $this->order->setRefreshState(Order::REFRESH_ON_SAVE);
    $this->order->save();

    // Confirm that both shipments were discounted.
    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface[] $shipments */
    $shipments = $this->order->get('shipments')->referencedEntities();
    $first_shipment = reset($shipments);
    $this->assertEquals(new Price('5.00', 'USD'), $first_shipment->getOriginalAmount());
    $this->assertEquals(new Price('0.00', 'USD'), $first_shipment->getAmount());
    $this->assertEquals(new Price('0.00', 'USD'), $first_shipment->getAdjustedAmount());
    $adjustments = $first_shipment->getAdjustments();
    $this->assertCount(1, $adjustments);
    $adjustment = reset($adjustments);
    $this->assertEquals('shipping_promotion', $adjustment->getType());
    $this->assertEquals('Discount', $adjustment->getLabel());
    // Confirm that the adjustment amount is equal to the remaining shipment
    // amount at the time of application.
    $this->assertEquals(new Price('-5.00', 'USD'), $adjustment->getAmount());
    $this->assertEquals($this->promotion->id(), $adjustment->getSourceId());
    $this->assertTrue($adjustment->isIncluded());

    $second_shipment = end($shipments);
    $this->assertEquals(new Price('20.00', 'USD'), $second_shipment->getOriginalAmount());
    $this->assertEquals(new Price('9.00', 'USD'), $second_shipment->getAmount());
    $this->assertEquals(new Price('9.00', 'USD'), $second_shipment->getAdjustedAmount());
    $adjustments = $second_shipment->getAdjustments();
    $this->assertCount(1, $adjustments);
    $adjustment = reset($adjustments);
    $this->assertEquals('shipping_promotion', $adjustment->getType());
    $this->assertEquals('Discount', $adjustment->getLabel());
    // Confirm that the adjustment amount matches the offer amount.
    $this->assertEquals(new Price('-11.00', 'USD'), $adjustment->getAmount());
    $this->assertEquals($this->promotion->id(), $adjustment->getSourceId());
    $this->assertTrue($adjustment->isIncluded());

    // Confirm that the adjustments were transferred to the order.
    $this->assertCount(4, $this->order->collectAdjustments());
    $this->assertCount(2, $this->order->collectAdjustments(['shipping']));
    $this->assertCount(2, $this->order->collectAdjustments(['shipping_promotion']));
    $this->assertEquals(new Price('29.00', 'USD'), $this->order->getTotalPrice());

    // Confirm that it is possible to discount only a single shipment.
    $offer = $this->promotion->getOffer();
    $offer_configuration = $offer->getConfiguration();
    $offer_configuration['filter'] = 'exclude';
    $offer_configuration['shipping_methods'] = [
      ['shipping_method' => $first_shipment->getShippingMethod()->uuid()],
    ];
    $offer->setConfiguration($offer_configuration);
    $this->promotion->setOffer($offer);
    $this->promotion->save();
    $this->order->setRefreshState(Order::REFRESH_ON_SAVE);
    $this->order->save();

    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $first_shipment */
    $first_shipment = $this->reloadEntity($first_shipment);
    $this->assertEquals(new Price('5.00', 'USD'), $first_shipment->getOriginalAmount());
    $this->assertEquals(new Price('5.00', 'USD'), $first_shipment->getAmount());
    $this->assertEquals(new Price('5.00', 'USD'), $first_shipment->getAdjustedAmount());
    $this->assertCount(0, $first_shipment->getAdjustments());
    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $second_shipment */
    $second_shipment = $this->reloadEntity($second_shipment);
    $this->assertEquals(new Price('20.00', 'USD'), $second_shipment->getOriginalAmount());
    $this->assertEquals(new Price('9.00', 'USD'), $second_shipment->getAmount());
    $this->assertEquals(new Price('9.00', 'USD'), $second_shipment->getAdjustedAmount());
    $this->assertCount(1, $second_shipment->getAdjustments());

    // Confirm that the adjustments were transferred to the order.
    $this->assertCount(3, $this->order->collectAdjustments());
    $this->assertCount(2, $this->order->collectAdjustments(['shipping']));
    $this->assertCount(1, $this->order->collectAdjustments(['shipping_promotion']));
    $this->assertEquals(new Price('34.00', 'USD'), $this->order->getTotalPrice());
  }

  /**
   * Tests the non-display-inclusive offer.
   *
   * @covers ::applyToShipment
   */
  public function testNonDisplayInclusive() {
    $offer = $this->promotion->getOffer();
    $offer_configuration = $offer->getConfiguration();
    $offer_configuration['display_inclusive'] = FALSE;
    $offer->setConfiguration($offer_configuration);
    $this->promotion->setDisplayName('$11 off');
    $this->promotion->setOffer($offer);
    $this->promotion->save();

    $this->assertCount(0, $this->order->collectAdjustments());
    $this->assertEquals(new Price('20.00', 'USD'), $this->order->getTotalPrice());
    $this->order->setRefreshState(Order::REFRESH_ON_SAVE);
    $this->order->save();

    // Confirm that both shipments were discounted.
    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface[] $shipments */
    $shipments = $this->order->get('shipments')->referencedEntities();
    $first_shipment = reset($shipments);
    $this->assertEquals(new Price('5.00', 'USD'), $first_shipment->getOriginalAmount());
    $this->assertEquals(new Price('5.00', 'USD'), $first_shipment->getAmount());
    $this->assertEquals(new Price('0.00', 'USD'), $first_shipment->getAdjustedAmount());
    $adjustments = $first_shipment->getAdjustments();
    $this->assertCount(1, $adjustments);
    $adjustment = reset($adjustments);
    $this->assertEquals('shipping_promotion', $adjustment->getType());
    $this->assertEquals('$11 off', $adjustment->getLabel());
    // Confirm that the adjustment amount is equal to the remaining shipment
    // amount at the time of application.
    $this->assertEquals(new Price('-5.00', 'USD'), $adjustment->getAmount());
    $this->assertEquals($this->promotion->id(), $adjustment->getSourceId());
    $this->assertFalse($adjustment->isIncluded());

    $second_shipment = end($shipments);
    $this->assertEquals(new Price('20.00', 'USD'), $second_shipment->getOriginalAmount());
    $this->assertEquals(new Price('20.00', 'USD'), $second_shipment->getAmount());
    $this->assertEquals(new Price('9.00', 'USD'), $second_shipment->getAdjustedAmount());
    $adjustments = $second_shipment->getAdjustments();
    $this->assertCount(1, $adjustments);
    $adjustment = reset($adjustments);
    $this->assertEquals('shipping_promotion', $adjustment->getType());
    $this->assertEquals('$11 off', $adjustment->getLabel());
    // Confirm that the adjustment amount matches the offer amount.
    $this->assertEquals(new Price('-11.00', 'USD'), $adjustment->getAmount());
    $this->assertEquals($this->promotion->id(), $adjustment->getSourceId());
    $this->assertFalse($adjustment->isIncluded());
  }

}
