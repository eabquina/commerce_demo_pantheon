<?php

namespace Drupal\Tests\commerce_shipping\Unit;

use Drupal\commerce_price\Price;
use Drupal\commerce_shipping\ShippingRate;
use Drupal\commerce_shipping\ShippingService;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\commerce_shipping\ShippingRate
 * @group commerce_shipping
 */
class ShippingRateTest extends UnitTestCase {

  /**
   * The shipping rate.
   *
   * @var \Drupal\commerce_shipping\ShippingRate
   */
  protected $rate;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
  }

  /**
   * Tests the constructor and definition checks.
   *
   * @covers ::__construct
   *
   * @dataProvider invalidDefinitionProvider
   */
  public function testInvalidDefinition($definition, $message) {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage($message);
    new ShippingRate($definition);
  }

  /**
   * Invalid constructor definitions.
   *
   * @return array
   *   The definitions.
   */
  public function invalidDefinitionProvider() {
    return [
      [[], 'Missing required property shipping_method_id'],
      [['shipping_method_id' => 'standard'], 'Missing required property service'],
      [
        [
          'shipping_method_id' => 'standard',
          'service' => new ShippingService('test', 'Test'),
        ],
        'Missing required property amount',
      ],
      [
        [
          'shipping_method_id' => 'standard',
          'service' => 'Test',
          'amount' => '10 USD',
        ],
        sprintf('Property "service" should be an instance of %s.', ShippingService::class),
      ],
      [
        [
          'shipping_method_id' => 'standard',
          'service' => new ShippingService('test', 'Test'),
          'amount' => '10 USD',
        ],
        sprintf('Property "amount" should be an instance of %s.', Price::class),
      ],
    ];
  }

  /**
   * @covers ::getId
   * @covers ::getShippingMethodId
   * @covers ::getService
   * @covers ::getOriginalAmount
   * @covers ::setOriginalAmount
   * @covers ::getAmount
   * @covers ::setAmount
   * @covers ::getDescription
   * @covers ::setDescription
   * @covers ::getDeliveryDate
   * @covers ::setDeliveryDate
   * @covers ::toArray
   */
  public function testMethods() {
    $first_date = new DrupalDateTime('2016-11-24', 'UTC', ['langcode' => 'en']);
    $second_date = new DrupalDateTime('2016-12-01', 'UTC', ['langcode' => 'en']);

    $definition = [
      'id' => '717c2f9',
      'shipping_method_id' => 'standard',
      'service' => new ShippingService('test', 'Test'),
      'original_amount' => new Price('15.00', 'USD'),
      'amount' => new Price('10.00', 'USD'),
      'description' => 'Delivery in 3-5 business days.',
      'delivery_date' => $first_date,
    ];

    $shipping_rate = new ShippingRate($definition);
    $this->assertEquals($definition['id'], $shipping_rate->getId());
    $this->assertEquals($definition['shipping_method_id'], $shipping_rate->getShippingMethodId());
    $this->assertEquals($definition['service'], $shipping_rate->getService());
    $this->assertEquals($definition['original_amount'], $shipping_rate->getOriginalAmount());
    $this->assertEquals($definition['amount'], $shipping_rate->getAmount());
    $this->assertEquals($definition['description'], $shipping_rate->getDescription());
    $this->assertEquals($definition['delivery_date'], $shipping_rate->getDeliveryDate());
    $this->assertEquals($definition, $shipping_rate->toArray());

    $shipping_rate->setOriginalAmount(new Price('14.00', 'USD'));
    $this->assertEquals(new Price('14.00', 'USD'), $shipping_rate->getOriginalAmount());
    $shipping_rate->setAmount(new Price('11.00', 'USD'));
    $this->assertEquals(new Price('11.00', 'USD'), $shipping_rate->getAmount());
    $shipping_rate->setDescription('Arrives yesterday.');
    $this->assertEquals('Arrives yesterday.', $shipping_rate->getDescription());
    $shipping_rate->setDeliveryDate($second_date);
    $this->assertEquals($second_date, $shipping_rate->getDeliveryDate());
  }

  /**
   * @covers ::getId
   * @covers ::getOriginalAmount
   */
  public function testDefaults() {
    $definition = [
      'shipping_method_id' => 'standard',
      'service' => new ShippingService('test', 'Test'),
      'amount' => new Price('10.00', 'USD'),
    ];

    $shipping_rate = new ShippingRate($definition);
    $this->assertEquals('standard--test', $shipping_rate->getId());
    $this->assertEquals($definition['amount'], $shipping_rate->getOriginalAmount());
  }

}
