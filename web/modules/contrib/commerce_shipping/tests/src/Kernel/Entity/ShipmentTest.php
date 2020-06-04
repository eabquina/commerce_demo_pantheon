<?php

namespace Drupal\Tests\commerce_shipping\Kernel\Entity;

use Drupal\commerce_order\Adjustment;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_price\Price;
use Drupal\commerce_shipping\Entity\Shipment;
use Drupal\commerce_shipping\Entity\ShipmentType;
use Drupal\commerce_shipping\Entity\ShippingMethod;
use Drupal\commerce_shipping\ProposedShipment;
use Drupal\commerce_shipping\ShipmentItem;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\physical\Weight;
use Drupal\profile\Entity\Profile;
use Drupal\profile\Entity\ProfileType;
use Drupal\Tests\commerce_shipping\Kernel\ShippingKernelTestBase;

/**
 * Tests the Shipment entity.
 *
 * @coversDefaultClass \Drupal\commerce_shipping\Entity\Shipment
 *
 * @group commerce_shipping
 */
class ShipmentTest extends ShippingKernelTestBase {

  /**
   * @covers ::getOrder
   * @covers ::getOrderId
   * @covers ::getPackageType
   * @covers ::setPackageType
   * @covers ::getShippingMethod
   * @covers ::getShippingMethodId
   * @covers ::setShippingMethod
   * @covers ::getShippingService
   * @covers ::setShippingService
   * @covers ::getShippingProfile
   * @covers ::setShippingProfile
   * @covers ::getTitle
   * @covers ::setTitle
   * @covers ::getItems
   * @covers ::setItems
   * @covers ::hasItems
   * @covers ::addItem
   * @covers ::removeItem
   * @covers ::getTotalQuantity
   * @covers ::getTotalDeclaredValue
   * @covers ::getWeight
   * @covers ::setWeight
   * @covers ::getOriginalAmount
   * @covers ::setOriginalAmount
   * @covers ::getAmount
   * @covers ::setAmount
   * @covers ::getAdjustments
   * @covers ::setAdjustments
   * @covers ::addAdjustment
   * @covers ::removeAdjustment
   * @covers ::getAdjustedAmount
   * @covers ::getTrackingCode
   * @covers ::setTrackingCode
   * @covers ::getState
   * @covers ::getData
   * @covers ::setData
   * @covers ::getCreatedTime
   * @covers ::setCreatedTime
   * @covers ::getShippedTime
   * @covers ::setShippedTime
   * @covers ::recalculateWeight
   * @covers ::postDelete
   */
  public function testShipment() {
    $user = $this->createUser(['mail' => $this->randomString() . '@example.com']);
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = Order::create([
      'type' => 'default',
      'state' => 'draft',
      'mail' => $user->getEmail(),
      'uid' => $user->id(),
      'store_id' => $this->store->id(),
    ]);
    $order->setRefreshState(Order::REFRESH_SKIP);
    $order->save();
    $order = $this->reloadEntity($order);

    /** @var \Drupal\commerce_shipping\Entity\ShippingMethodInterface $shipping_method */
    $shipping_method = ShippingMethod::create([
      'name' => $this->randomString(),
      'status' => 1,
    ]);
    $shipping_method->save();
    $shipping_method = $this->reloadEntity($shipping_method);

    /** @var \Drupal\profile\Entity\ProfileInterface $profile */
    $profile = Profile::create([
      'type' => 'customer',
    ]);
    $profile->save();
    $profile = $this->reloadEntity($profile);

    $shipment = Shipment::create([
      'type' => 'default',
      'state' => 'ready',
      'order_id' => $order->id(),
      'title' => 'Shipment',
      'amount' => new Price('12.00', 'USD'),
    ]);

    $this->assertEquals($order, $shipment->getOrder());
    $this->assertEquals($order->id(), $shipment->getOrderId());

    $package_type_manager = $this->container->get('plugin.manager.commerce_package_type');
    $package_type = $package_type_manager->createInstance('custom_box');
    $shipment->setPackageType($package_type);
    $this->assertEquals($package_type, $shipment->getPackageType());

    $shipment->setShippingMethod($shipping_method);
    $this->assertEquals($shipping_method, $shipment->getShippingMethod());
    $this->assertEquals($shipping_method->id(), $shipment->getShippingMethodId());

    $shipping_service = $this->randomString();
    $shipment->setShippingService($shipping_service);
    $this->assertEquals($shipping_service, $shipment->getShippingService());

    $shipment->setShippingProfile($profile);
    $this->assertEquals($profile, $shipment->getShippingProfile());

    $shipment->setTitle('Shipment #1');
    $this->assertEquals('Shipment #1', $shipment->getTitle());

    $items = [];
    $items[] = new ShipmentItem([
      'order_item_id' => 10,
      'title' => 'T-shirt (red, large)',
      'quantity' => 2,
      'weight' => new Weight('40', 'kg'),
      'declared_value' => new Price('30', 'USD'),
    ]);
    $items[] = new ShipmentItem([
      'order_item_id' => 10,
      'title' => 'T-shirt (blue, large)',
      'quantity' => 2,
      'weight' => new Weight('30', 'kg'),
      'declared_value' => new Price('30', 'USD'),
    ]);
    $shipment->addItem($items[0]);
    $shipment->addItem($items[1]);
    $this->assertTrue($shipment->hasItems());
    $this->assertEquals($items, $shipment->getItems());
    $shipment->removeItem($items[0]);
    $this->assertEquals([$items[1]], $shipment->getItems());
    $shipment->setItems($items);
    $this->assertEquals($items, $shipment->getItems());

    $this->assertEquals('4.00', $shipment->getTotalQuantity());
    $this->assertEquals(new Price('60', 'USD'), $shipment->getTotalDeclaredValue());

    $calculated_weight = new Weight('70', 'kg');
    $this->assertEquals($calculated_weight, $shipment->getWeight()->convert('kg'));
    $new_weight = new Weight('4', 'kg');
    $shipment->setWeight($new_weight);
    $this->assertEquals($new_weight, $shipment->getWeight());

    $original_amount = new Price('15.00', 'USD');
    $shipment->setOriginalAmount($original_amount);
    $this->assertEquals($original_amount, $shipment->getOriginalAmount());

    $amount = new Price('10.00', 'USD');
    $shipment->setAmount($amount);
    $this->assertEquals($amount, $shipment->getAmount());

    $adjustments = [];
    $adjustments[] = new Adjustment([
      'type' => 'custom',
      'label' => '10% off',
      'amount' => new Price('-1.00', 'USD'),
    ]);
    $adjustments[] = new Adjustment([
      'type' => 'fee',
      'label' => 'Random fee',
      'amount' => new Price('2.00', 'USD'),
    ]);
    $shipment->addAdjustment($adjustments[0]);
    $shipment->addAdjustment($adjustments[1]);
    $this->assertEquals($adjustments, $shipment->getAdjustments());
    $shipment->removeAdjustment($adjustments[0]);
    $this->assertEquals([$adjustments[1]], $shipment->getAdjustments());
    $shipment->setAdjustments($adjustments);
    $this->assertEquals($adjustments, $shipment->getAdjustments());
    $this->assertEquals(new Price('11.00', 'USD'), $shipment->getAdjustedAmount());
    $this->assertEquals(new Price('9.00', 'USD'), $shipment->getAdjustedAmount(['custom']));
    $this->assertEquals(new Price('12.00', 'USD'), $shipment->getAdjustedAmount(['fee']));

    $tracking_code = $this->randomString();
    $shipment->setTrackingCode($tracking_code);
    $this->assertEquals($tracking_code, $shipment->getTrackingCode());

    $this->assertEquals('ready', $shipment->getState()->value);

    $this->assertEquals('default', $shipment->getData('test', 'default'));
    $shipment->setData('test', 'value');
    $this->assertEquals('value', $shipment->getData('test', 'default'));

    $shipment->setCreatedTime(635879700);
    $this->assertEquals(635879700, $shipment->getCreatedTime());

    $shipment->setShippedTime(635879800);
    $this->assertEquals(635879800, $shipment->getShippedTime());

    $shipment->save();
    $order->set('shipments', [$shipment]);
    $order->addAdjustment(new Adjustment([
      'type' => 'shipping',
      'label' => t('Shipping'),
      'amount' => $shipment->getAmount(),
      'source_id' => $shipment->id(),
    ]));
    $order->setRefreshState(Order::REFRESH_SKIP);
    $order->save();
    $order = $this->reloadEntity($order);
    $this->assertCount(1, $order->getAdjustments());
    $this->assertCount(1, $order->get('shipments')->referencedEntities());
    $shipment->delete();
    $order = $this->reloadEntity($order);
    $this->assertCount(0, $order->getAdjustments());
    $this->assertCount(0, $order->get('shipments')->referencedEntities());
  }

  /**
   * @covers ::bundleFieldDefinitions
   */
  public function testCustomProfileType() {
    $profile_type = ProfileType::create([
      'id' => 'customer_shipping',
    ]);
    $profile_type->setThirdPartySetting('commerce_order', 'customer_profile_type', TRUE);
    $profile_type->save();

    $shipment_type = ShipmentType::load('default');
    $shipment_type->setProfileTypeId('customer_shipping');
    $shipment_type->save();

    $profile = Shipment::create(['type' => 'default']);
    /** @var \Drupal\Core\Field\FieldItemListInterface $shipping_profile_field */
    $shipping_profile_field = $profile->get('shipping_profile');
    $handler_settings = $shipping_profile_field->getFieldDefinition()->getSetting('handler_settings');
    $this->assertEquals('customer_shipping', reset($handler_settings['target_bundles']));
  }

  /**
   * @covers ::populateFromProposedShipment
   */
  public function testPopulatingFromProposedShipment() {
    /** @var \Drupal\profile\Entity\ProfileInterface $profile */
    $profile = Profile::create([
      'type' => 'customer',
    ]);
    $profile->save();
    $profile = $this->reloadEntity($profile);

    $proposed_shipment = new ProposedShipment([
      'type' => 'default',
      'order_id' => 10,
      'title' => 'Test title',
      'items' => [
        new ShipmentItem([
          'order_item_id' => 10,
          'title' => 'T-shirt (red, large)',
          'quantity' => 1,
          'weight' => new Weight('10', 'kg'),
          'declared_value' => new Price('15', 'USD'),
        ]),
      ],
      'shipping_profile' => $profile,
      'package_type_id' => 'custom_box',
      // State is not a custom field, but it simplifies this test.
      'custom_fields' => [
        'state' => 'ready',
        'no_field' => 'custom_value',
      ],
    ]);
    $shipment = Shipment::create([
      'type' => 'default',
      'title' => 'Shipment',
    ]);
    $shipment->populateFromProposedShipment($proposed_shipment);

    $this->assertEquals($proposed_shipment->getOrderId(), $shipment->getOrderId());
    $this->assertEquals($proposed_shipment->getPackageTypeId(), $shipment->getPackageType()->getId());
    $this->assertEquals($profile, $shipment->getShippingProfile());
    $this->assertEquals($proposed_shipment->getTitle(), $shipment->getTitle());
    $this->assertEquals($proposed_shipment->getItems(), $shipment->getItems());
    $this->assertEquals(new Weight('10', 'kg'), $shipment->getWeight());
    $this->assertEquals('ready', $shipment->getState()->value);
    $this->assertEquals('custom_value', $shipment->getData('no_field'));
  }

  /**
   * @covers ::preSave
   */
  public function testDefaults() {
    /** @var \Drupal\commerce_shipping\Entity\ShippingMethodInterface $shipping_method */
    $shipping_method = ShippingMethod::create([
      'name' => $this->randomString(),
      'plugin' => [
        'target_plugin_id' => 'flat_rate',
        'target_plugin_configuration' => [],
      ],
      'status' => 1,
    ]);
    $shipping_method->save();

    // Saving a shipment with a shipping method but no package type should
    // populate the package type.
    $shipment = Shipment::create([
      'type' => 'default',
      'order_id' => 10,
      'shipping_method' => $shipping_method,
      'title' => 'Shipment',
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
    $this->assertEquals('custom_box', $shipment->getPackageType()->getId());
  }

  /**
   * @covers ::preSave
   */
  public function testEmptyValidation() {
    $shipment = Shipment::create([
      'type' => 'default',
      'title' => 'Shipment',
    ]);
    $this->expectException(EntityStorageException::class);
    $this->expectExceptionMessage('Required shipment field "order_id" is empty.');
    $shipment->save();
  }

  /**
   * @covers ::clearRate
   */
  public function testClearRate() {
    $fields = ['amount', 'original_amount', 'shipping_method', 'shipping_service'];
    $user = $this->createUser(['mail' => $this->randomString() . '@example.com']);
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = Order::create([
      'type' => 'default',
      'state' => 'draft',
      'mail' => $user->getEmail(),
      'uid' => $user->id(),
      'store_id' => $this->store->id(),
    ]);
    $order->setRefreshState(Order::REFRESH_SKIP);
    $order->save();
    $order = $this->reloadEntity($order);
    /** @var \Drupal\commerce_shipping\Entity\ShippingMethodInterface $shipping_method */
    $shipping_method = ShippingMethod::create([
      'name' => $this->randomString(),
      'status' => 1,
    ]);
    $shipping_method->save();
    $shipping_method = $this->reloadEntity($shipping_method);
    $shipment = Shipment::create([
      'amount' => new Price('0', 'USD'),
      'original_amount' => new Price('0', 'USD'),
      'shipping_service' => $this->randomString(),
      'order_id' => $order->id(),
      'type' => 'default',
    ]);
    $shipment->setShippingMethod($shipping_method);
    foreach ($fields as $field) {
      $this->assertFalse($shipment->get($field)->isEmpty());
    }
    $shipment->clearRate();
    foreach ($fields as $field) {
      $this->assertTrue($shipment->get($field)->isEmpty());
    }
  }

}
