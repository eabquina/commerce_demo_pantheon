<?php

namespace Drupal\Tests\physical\Kernel;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\physical\Comparator\MeasurementComparator;
use Drupal\physical\Length;
use SebastianBergmann\Comparator\Factory as PhpUnitComparatorFactory;

/**
 * Tests the 'physical_dimensions' field type.
 *
 * @group physical
 */
class DimensionsItemTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'physical',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $factory = PhpUnitComparatorFactory::getInstance();
    $factory->register(new MeasurementComparator());

    $field_storage = FieldStorageConfig::create([
      'field_name' => 'test_dimensions',
      'entity_type' => 'entity_test',
      'type' => 'physical_dimensions',
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_name' => 'test_dimensions',
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
    ]);
    $field->save();
  }

  /**
   * Tests the field.
   */
  public function testField() {
    /** @var \Drupal\entity_test\Entity\EntityTest $entity */
    $entity = EntityTest::create([
      'test_dimensions' => [
        'length' => '5',
        'width' => '7',
        'height' => '2',
        'unit' => 'in',
      ],
    ]);
    $entity->save();
    $entity = $this->reloadEntity($entity);

    /** @var \Drupal\physical\Plugin\Field\FieldType\DimensionsItem $item */
    $item = $entity->get('test_dimensions')->first();

    $length = $item->getLength();
    $this->assertInstanceOf(Length::class, $length);
    $this->assertEquals(new Length('5', 'in'), $length);

    $width = $item->getWidth();
    $this->assertInstanceOf(Length::class, $width);
    $this->assertEquals(new Length('7', 'in'), $width);

    $height = $item->getHeight();
    $this->assertInstanceOf(Length::class, $height);
    $this->assertEquals(new Length('2', 'in'), $height);
  }

}
