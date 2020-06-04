<?php

namespace Drupal\Tests\commerce_shipping\Kernel\Plugin\Commerce\TaxType;

use Drupal\commerce_order\Adjustment;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\commerce_shipping\Entity\ShippingMethod;
use Drupal\commerce_tax\Entity\TaxType;
use Drupal\physical\Weight;
use Drupal\profile\Entity\Profile;
use Drupal\Tests\commerce_shipping\Kernel\ShippingKernelTestBase;

/**
 * Tests the shipping tax type.
 *
 * @coversDefaultClass \Drupal\commerce_shipping\Plugin\Commerce\TaxType\Shipping
 * @group commerce_shipping
 */
class ShippingTest extends ShippingKernelTestBase {

  /**
   * The sample order.
   *
   * @var \Drupal\commerce_order\Entity\OrderInterface
   */
  protected $order;

  /**
   * The tax type.
   *
   * @var \Drupal\commerce_tax\Entity\TaxTypeInterface
   */
  protected $taxType;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'commerce_tax',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installConfig(['commerce_tax']);

    $this->store->set('prices_include_tax', TRUE);
    $this->store->save();

    $eu_tax_type = TaxType::create([
      'id' => 'eu_vat',
      'label' => 'EU VAT',
      'plugin' => 'european_union_vat',
      'configuration' => [
        'display_inclusive' => TRUE,
      ],
      // Don't allow the tax type to apply automatically.
      'status' => FALSE,
    ]);
    $eu_tax_type->save();

    $first_variation = ProductVariation::create([
      'type' => 'default',
      'sku' => 'test-product-01',
      'title' => 'Hat',
      'price' => new Price('70.00', 'USD'),
      'weight' => new Weight('0', 'g'),
    ]);
    $first_variation->save();

    $second_variation = ProductVariation::create([
      'type' => 'default',
      'sku' => 'test-product-02',
      'title' => 'Mug',
      'price' => new Price('15.00', 'USD'),
      'weight' => new Weight('0', 'g'),
    ]);
    $second_variation->save();

    $first_order_item = OrderItem::create([
      'type' => 'default',
      'quantity' => 1,
      'title' => $first_variation->getOrderItemTitle(),
      'purchased_entity' => $first_variation,
      'unit_price' => new Price('70.00', 'USD'),
    ]);
    $first_order_item->addAdjustment(new Adjustment([
      'type' => 'tax',
      'label' => 'VAT',
      'amount' => new Price('6.36', 'USD'),
      'percentage' => '0.1',
      'source_id' => 'eu_vat|fr|intermediate',
      'included' => TRUE,
      'locked' => TRUE,
    ]));
    $first_order_item->save();

    $second_order_item = OrderItem::create([
      'type' => 'default',
      'quantity' => 1,
      'title' => $second_variation->getOrderItemTitle(),
      'purchased_entity' => $second_variation,
      'unit_price' => new Price('15.00', 'USD'),
    ]);
    $second_order_item->addAdjustment(new Adjustment([
      'type' => 'tax',
      'label' => 'VAT',
      'amount' => new Price('2.50', 'USD'),
      'percentage' => '0.2',
      'source_id' => 'eu_vat|fr|standard',
      'included' => TRUE,
      'locked' => TRUE,
    ]));
    $second_order_item->save();

    $third_order_item = OrderItem::create([
      'type' => 'default',
      'quantity' => 1,
      'title' => $second_variation->getOrderItemTitle(),
      'purchased_entity' => $second_variation,
      'unit_price' => new Price('15.00', 'USD'),
    ]);
    $third_order_item->addAdjustment(new Adjustment([
      'type' => 'tax',
      'label' => 'VAT',
      'amount' => new Price('2.50', 'USD'),
      'percentage' => '0.2',
      'source_id' => 'eu_vat|fr|standard',
      'included' => TRUE,
      'locked' => TRUE,
    ]));
    $second_order_item->save();

    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = Order::create([
      'type' => 'default',
      'uid' => $this->createUser(['mail' => $this->randomString() . '@example.com']),
      'store_id' => $this->store->id(),
      'order_items' => [$first_order_item, $second_order_item, $third_order_item],
    ]);
    $order->save();

    $shipping_method = ShippingMethod::create([
      'stores' => $this->store->id(),
      'name' => 'Standard shipping',
      'plugin' => [
        'target_plugin_id' => 'flat_rate',
        'target_plugin_configuration' => [
          'rate_label' => 'Standard shipping',
          'rate_amount' => [
            'number' => '10.00',
            'currency_code' => 'USD',
          ],
        ],
      ],
      'status' => TRUE,
    ]);
    $shipping_method->save();

    /** @var \Drupal\profile\Entity\ProfileInterface $shipping_profile */
    $shipping_profile = Profile::create([
      'type' => 'customer',
      'address' => [
        'country_code' => 'DE',
      ],
    ]);
    $shipping_profile->save();

    $shipping_order_manager = $this->container->get('commerce_shipping.order_manager');
    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface[] $shipments */
    $shipments = $shipping_order_manager->pack($order, $shipping_profile);
    $shipment = reset($shipments);
    $shipment->setShippingMethodId($shipping_method->id());
    $shipment->setShippingService('default');
    $shipment->setAmount(new Price('10.00', 'USD'));
    $order->set('shipments', [$shipment]);
    $order->setRefreshState(Order::REFRESH_SKIP);
    $order->save();
    $this->order = $order;

    $this->taxType = TaxType::create([
      'id' => 'shipping',
      'label' => 'Shipping',
      'plugin' => 'shipping',
      'configuration' => [
        'strategy' => 'default',
      ],
      // Don't allow the tax type to apply automatically.
      'status' => FALSE,
    ]);
    $this->taxType->save();
  }

  /**
   * @covers ::applies
   */
  public function testApplies() {
    $plugin = $this->taxType->getPlugin();
    $this->assertTrue($plugin->applies($this->order));

    // Confirm that non-shippable orders are ignored.
    $order = clone $this->order;
    $order->setItems([]);
    $this->assertFalse($plugin->applies($order));

    // Confirm that orders without shipments are ignored.
    $order = clone $this->order;
    $order->set('shipments', []);
    $this->assertFalse($plugin->applies($order));

    // Confirm that the tax type can be limited by store.
    $order = clone $this->order;
    $order->setStore($this->createStore());
    $plugin->setConfiguration([
      'store_filter' => 'include',
      'stores' => [$this->store->uuid()],
    ]);
    $this->assertFalse($plugin->applies($order));
    $this->assertTrue($plugin->applies($this->order));
    $plugin->setConfiguration([
      'store_filter' => 'exclude',
      'stores' => [$this->store->uuid()],
    ]);
    $this->assertTrue($plugin->applies($order));
    $this->assertFalse($plugin->applies($this->order));
  }

  /**
   * @covers ::applyDefault
   */
  public function testApplyDefault() {
    $plugin = $this->taxType->getPlugin();
    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface[] $shipments */
    $shipments = $this->order->get('shipments')->referencedEntities();
    $shipment = reset($shipments);

    $plugin->apply($this->order);
    $tax_adjustments = $shipment->getAdjustments(['tax']);
    $this->assertCount(1, $tax_adjustments);

    $tax_adjustment = reset($tax_adjustments);
    $this->assertEquals('eu_vat|fr|standard', $tax_adjustment->getSourceId());
    $this->assertEquals('VAT', $tax_adjustment->getLabel());
    $this->assertEquals(new Price('1.67', 'USD'), $tax_adjustment->getAmount());
    $this->assertEquals('0.2', $tax_adjustment->getPercentage());
    $this->assertTrue($tax_adjustment->isIncluded());

    // Leave only the order item which uses the "intermediate" rate.
    $order_items = $this->order->getItems();
    $second_order_item = reset($order_items);
    $this->order->setItems([$second_order_item]);

    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
    $shipment = $this->reloadEntity($shipment);
    $plugin->apply($this->order);
    $tax_adjustments = $shipment->getAdjustments(['tax']);
    $this->assertCount(1, $tax_adjustments);

    $tax_adjustment = reset($tax_adjustments);
    // Confirm that the default rate is still used.
    $this->assertEquals('eu_vat|fr|standard', $tax_adjustment->getSourceId());
  }

  /**
   * @covers ::applyHighest
   */
  public function testApplyHighest() {
    $plugin = $this->taxType->getPlugin();
    $plugin->setConfiguration([
      'strategy' => 'highest',
    ]);
    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface[] $shipments */
    $shipments = $this->order->get('shipments')->referencedEntities();
    $shipment = reset($shipments);

    $plugin->apply($this->order);
    $tax_adjustments = $shipment->getAdjustments(['tax']);
    $this->assertCount(1, $tax_adjustments);

    $tax_adjustment = reset($tax_adjustments);
    $this->assertEquals('eu_vat|fr|standard', $tax_adjustment->getSourceId());
    $this->assertEquals('VAT', $tax_adjustment->getLabel());
    $this->assertEquals(new Price('1.67', 'USD'), $tax_adjustment->getAmount());
    $this->assertEquals('0.2', $tax_adjustment->getPercentage());
    $this->assertTrue($tax_adjustment->isIncluded());

    // Leave only the order item which uses the "intermediate" rate.
    $order_items = $this->order->getItems();
    $second_order_item = reset($order_items);
    $this->order->setItems([$second_order_item]);

    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
    $shipment = $this->reloadEntity($shipment);
    $plugin->apply($this->order);
    $tax_adjustments = $shipment->getAdjustments(['tax']);
    $this->assertCount(1, $tax_adjustments);

    $tax_adjustment = reset($tax_adjustments);
    // Confirm that the intermediate rate is now used.
    $this->assertEquals('eu_vat|fr|intermediate', $tax_adjustment->getSourceId());
    $this->assertEquals('VAT', $tax_adjustment->getLabel());
    $this->assertEquals(new Price('0.91', 'USD'), $tax_adjustment->getAmount());
    $this->assertEquals('0.1', $tax_adjustment->getPercentage());
    $this->assertTrue($tax_adjustment->isIncluded());
  }

  /**
   * @covers ::applyProportional
   */
  public function testApplyProportional() {
    $plugin = $this->taxType->getPlugin();
    $plugin->setConfiguration([
      'strategy' => 'proportional',
    ]);
    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface[] $shipments */
    $shipments = $this->order->get('shipments')->referencedEntities();
    $shipment = reset($shipments);

    $plugin->apply($this->order);
    $tax_adjustments = $shipment->getAdjustments(['tax']);
    $this->assertCount(2, $tax_adjustments);

    // The second and third order item represent 30% of the subtotal.
    // That's (10 * 0.2 / 1.2) * 0.3 = 1.67.
    $first_tax_adjustment = reset($tax_adjustments);
    $this->assertEquals('eu_vat|fr|standard', $first_tax_adjustment->getSourceId());
    $this->assertEquals('VAT', $first_tax_adjustment->getLabel());
    $this->assertEquals(new Price('0.5', 'USD'), $first_tax_adjustment->getAmount());
    $this->assertEquals('0.2', $first_tax_adjustment->getPercentage());
    $this->assertTrue($first_tax_adjustment->isIncluded());

    // The first order item represents 70% of the subtotal.
    // That's (10 * 0.1 / 1.1) * 0.7 = 0.64.
    $second_tax_adjustment = end($tax_adjustments);
    $this->assertEquals('eu_vat|fr|intermediate', $second_tax_adjustment->getSourceId());
    $this->assertEquals('VAT', $second_tax_adjustment->getLabel());
    $this->assertEquals(new Price('0.64', 'USD'), $second_tax_adjustment->getAmount());
    $this->assertEquals('0.1', $second_tax_adjustment->getPercentage());
    $this->assertTrue($second_tax_adjustment->isIncluded());
  }

}
