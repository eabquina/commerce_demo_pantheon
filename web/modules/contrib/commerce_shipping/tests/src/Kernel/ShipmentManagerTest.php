<?php

namespace Drupal\Tests\commerce_shipping\Kernel;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_price\Price;
use Drupal\commerce_promotion\Entity\Coupon;
use Drupal\commerce_promotion\Entity\Promotion;
use Drupal\commerce_shipping\Entity\Shipment;
use Drupal\commerce_shipping\Entity\ShippingMethod;
use Drupal\commerce_shipping\ShipmentItem;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\physical\Weight;

/**
 * Tests the shipment manager.
 *
 * @coversDefaultClass \Drupal\commerce_shipping\ShipmentManager
 * @group commerce_shipping
 */
class ShipmentManagerTest extends ShippingKernelTestBase {

  /**
   * The shipment manager.
   *
   * @var \Drupal\commerce_shipping\ShipmentManagerInterface
   */
  protected $shipmentManager;

  /**
   * The sample order.
   *
   * @var \Drupal\commerce_order\Entity\OrderInterface
   */
  protected $order;

  /**
   * The sample shipment.
   *
   * @var \Drupal\commerce_shipping\Entity\ShipmentInterface
   */
  protected $shipment;

  /**
   * The sample shipping methods.
   *
   * @var \Drupal\commerce_shipping\Entity\ShippingMethodInterface[]
   */
  protected $shippingMethods = [];

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'commerce_promotion',
    'language',
    'content_translation',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('commerce_promotion');
    $this->installEntitySchema('commerce_promotion_coupon');

    ConfigurableLanguage::createFromLangcode('fr')->save();
    $this->container->get('content_translation.manager')->setEnabled('commerce_shipping_method', 'commerce_shipping_method', TRUE);

    $this->shipmentManager = $this->container->get('commerce_shipping.shipment_manager');
    $order_item = OrderItem::create([
      'type' => 'test',
      'quantity' => 1,
      'unit_price' => new Price('12.00', 'USD'),
    ]);
    $order_item->save();
    /** @var \Drupal\commerce_order\Entity\Order $order */
    $order = Order::create([
      'type' => 'default',
      'order_number' => '6',
      'store_id' => $this->store->id(),
      'state' => 'completed',
      'order_items' => [$order_item],
    ]);
    $order->save();
    $this->order = $order;

    $shipping_method = ShippingMethod::create([
      'stores' => $this->store->id(),
      'name' => 'Example',
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
      'weight' => 1,
    ]);
    $shipping_method->save();
    $this->shippingMethods[] = $shipping_method;

    $another_shipping_method = ShippingMethod::create([
      'stores' => $this->store->id(),
      'name' => 'Another shipping method',
      'plugin' => [
        'target_plugin_id' => 'flat_rate',
        'target_plugin_configuration' => [
          'rate_label' => 'Overnight shipping',
          'rate_amount' => [
            'number' => '20',
            'currency_code' => 'USD',
          ],
        ],
      ],
      'status' => TRUE,
      'weight' => 0,
    ]);
    $another_shipping_method->addTranslation('fr', [
      'name' => 'Another shipping method (FR)',
      'plugin' => [
        'target_plugin_id' => 'flat_rate',
        'target_plugin_configuration' => [
          'rate_label' => 'Le overnight shipping',
          'rate_amount' => [
            'number' => '22',
            'currency_code' => 'USD',
          ],
        ],
      ],
    ]);
    $another_shipping_method->save();
    $this->shippingMethods[] = $another_shipping_method;

    $bad_shipping_method = ShippingMethod::create([
      'stores' => $this->store->id(),
      'name' => $this->randomString(),
      'status' => TRUE,
      'plugin' => [
        'target_plugin_id' => 'exception_thrower',
        'target_plugin_configuration' => [],
      ],
    ]);
    $bad_shipping_method->save();

    $shipment = Shipment::create([
      'type' => 'default',
      'order_id' => $order->id(),
      'title' => 'Shipment',
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
  }

  /**
   * Tests applying rates.
   *
   * @covers ::applyRate
   */
  public function testApplyRate() {
    $rates = $this->shipmentManager->calculateRates($this->shipment);
    $this->assertCount(2, $rates);
    /** @var \Drupal\commerce_shipping\ShippingRate $second_rate */
    $second_rate = end($rates);
    $this->shipmentManager->applyRate($this->shipment, $second_rate);

    $this->assertEquals($second_rate->getShippingMethodId(), $this->shipment->getShippingMethodId());
    $this->assertEquals($second_rate->getService()->getId(), $this->shipment->getShippingService());
    $this->assertEquals($second_rate->getOriginalAmount(), $this->shipment->getOriginalAmount());
    $this->assertEquals($second_rate->getAmount(), $this->shipment->getAmount());
  }

  /**
   * Tests calculating rates.
   *
   * @covers ::calculateRates
   */
  public function testCalculateRates() {
    // Use the FR translation where available (e.g. $another_shipping_method).
    $this->container->get('language.default')->set(ConfigurableLanguage::createFromLangcode('fr'));

    $rates = $this->shipmentManager->calculateRates($this->shipment);
    $this->assertCount(2, $rates);
    $first_rate = reset($rates);
    $second_rate = end($rates);

    $this->assertArrayHasKey($first_rate->getId(), $rates);
    $this->assertEquals('2', $first_rate->getShippingMethodId());
    $this->assertEquals('default', $first_rate->getService()->getId());
    $this->assertEquals('Le overnight shipping', $first_rate->getService()->getLabel());
    $this->assertEquals(new Price('22.00', 'USD'), $first_rate->getOriginalAmount());
    $this->assertEquals(new Price('22.00', 'USD'), $first_rate->getAmount());

    $this->assertArrayHasKey($second_rate->getId(), $rates);
    $this->assertEquals('1', $second_rate->getShippingMethodId());
    $this->assertEquals('default', $second_rate->getService()->getId());
    $this->assertEquals('Standard shipping', $second_rate->getService()->getLabel());
    $this->assertEquals(new Price('5.00', 'USD'), $second_rate->getOriginalAmount());
    $this->assertEquals(new Price('5.00', 'USD'), $second_rate->getAmount());

    // Test rate altering.
    $this->shipment->setData('alter_rate', TRUE);
    $rates = $this->shipmentManager->calculateRates($this->shipment);
    $this->assertCount(2, $rates);
    $first_rate = reset($rates);
    $second_rate = end($rates);

    $this->assertArrayHasKey($first_rate->getId(), $rates);
    $this->assertEquals('2', $first_rate->getShippingMethodId());
    $this->assertEquals('default', $first_rate->getService()->getId());
    $this->assertEquals('Le overnight shipping', $first_rate->getService()->getLabel());
    $this->assertEquals(new Price('22.00', 'USD'), $first_rate->getOriginalAmount());
    $this->assertEquals(new Price('44.00', 'USD'), $first_rate->getAmount());

    $this->assertArrayHasKey($second_rate->getId(), $rates);
    $this->assertEquals('1', $second_rate->getShippingMethodId());
    $this->assertEquals('default', $second_rate->getService()->getId());
    $this->assertEquals('Standard shipping', $second_rate->getService()->getLabel());
    $this->assertEquals(new Price('5.00', 'USD'), $second_rate->getOriginalAmount());
    $this->assertEquals(new Price('10.00', 'USD'), $second_rate->getAmount());
  }

  /**
   * Tests the applying of display-inclusive promotions.
   *
   * @covers ::calculateRates
   */
  public function testPromotions() {
    $first_promotion = Promotion::create([
      'name' => 'Promotion 1',
      'order_types' => [$this->order->bundle()],
      'stores' => [$this->store->id()],
      'offer' => [
        'target_plugin_id' => 'shipment_fixed_amount_off',
        'target_plugin_configuration' => [
          'display_inclusive' => TRUE,
          'filter' => 'include',
          'shipping_methods' => [
            ['shipping_method' => $this->shippingMethods[0]->uuid()],
          ],
          'amount' => [
            'number' => '1.00',
            'currency_code' => 'USD',
          ],
        ],
      ],
      'status' => TRUE,
    ]);
    $first_promotion->save();

    $second_promotion = Promotion::create([
      'name' => 'Promotion 1',
      'order_types' => [$this->order->bundle()],
      'stores' => [$this->store->id()],
      'offer' => [
        'target_plugin_id' => 'shipment_percentage_off',
        'target_plugin_configuration' => [
          'display_inclusive' => TRUE,
          'filter' => 'include',
          'shipping_methods' => [
            ['shipping_method' => $this->shippingMethods[1]->uuid()],
          ],
          'percentage' => '0.5',
        ],
      ],
      'status' => TRUE,
    ]);
    $second_promotion->save();

    $coupon = Coupon::create([
      'promotion_id' => $second_promotion->id(),
      'code' => '50% off shipping',
      'status' => TRUE,
    ]);
    $coupon->save();

    $this->order->set('coupons', [$coupon]);
    $this->order->setRefreshState(Order::REFRESH_SKIP);
    $this->order->save();

    $rates = $this->shipmentManager->calculateRates($this->shipment);
    $this->assertCount(2, $rates);
    $first_rate = reset($rates);
    $second_rate = end($rates);

    // The first rate should be reduced by the 50% off coupon.
    $this->assertArrayHasKey($first_rate->getId(), $rates);
    $this->assertEquals('2', $first_rate->getShippingMethodId());
    $this->assertEquals('default', $first_rate->getService()->getId());
    $this->assertEquals('Overnight shipping', $first_rate->getService()->getLabel());
    $this->assertEquals(new Price('20.00', 'USD'), $first_rate->getOriginalAmount());
    $this->assertEquals(new Price('10.00', 'USD'), $first_rate->getAmount());

    // The second rate should be reduced by the $1 off promotion.
    $this->assertArrayHasKey($second_rate->getId(), $rates);
    $this->assertEquals('1', $second_rate->getShippingMethodId());
    $this->assertEquals('default', $second_rate->getService()->getId());
    $this->assertEquals('Standard shipping', $second_rate->getService()->getLabel());
    $this->assertEquals(new Price('5.00', 'USD'), $second_rate->getOriginalAmount());
    $this->assertEquals(new Price('4.00', 'USD'), $second_rate->getAmount());
  }

  /**
   * Tests selecting the default rate.
   *
   * @covers ::selectDefaultRate
   */
  public function testSelectDefaultRate() {
    $rates = $this->shipmentManager->calculateRates($this->shipment);
    // The selected rate should be the first one (as a fallback).
    $default_rate = $this->shipmentManager->selectDefaultRate($this->shipment, $rates);
    $this->assertEquals('2--default', $default_rate->getId());

    // The selected rate should match the specified shipping method/service.
    $this->shipment->setShippingMethodId('1');
    $this->shipment->setShippingService('default');
    $default_rate = $this->shipmentManager->selectDefaultRate($this->shipment, $rates);
    $this->assertEquals('1--default', $default_rate->getId());
  }

}
