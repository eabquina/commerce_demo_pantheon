<?php

namespace Drupal\physical;

/**
 * Provides length units.
 */
final class LengthUnit implements UnitInterface {

  const MILLIMETER = 'mm';
  const CENTIMETER = 'cm';
  const METER = 'm';
  const KILOMETER = 'km';
  const INCH = 'in';
  const FOOT = 'ft';
  const NAUTICAL_MILE = 'M';

  /**
   * {@inheritdoc}
   */
  public static function getLabels() {
    return [
      self::MILLIMETER => t('mm'),
      self::CENTIMETER => t('cm'),
      self::METER => t('m'),
      self::KILOMETER => t('km'),
      self::INCH => t('in'),
      self::FOOT => t('ft'),
      self::NAUTICAL_MILE => t('M'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function getBaseUnit() {
    return self::METER;
  }

  /**
   * {@inheritdoc}
   */
  public static function getBaseFactor($unit) {
    self::assertExists($unit);
    $factors = [
      self::MILLIMETER => '0.001',
      self::CENTIMETER => '0.01',
      self::METER => '1',
      self::KILOMETER => '1000',
      self::INCH => '0.0254',
      self::FOOT => '0.3048',
      self::NAUTICAL_MILE => '1852',
    ];

    return $factors[$unit];
  }

  /**
   * {@inheritdoc}
   */
  public static function assertExists($unit) {
    $allowed_units = [
      self::MILLIMETER, self::CENTIMETER, self::METER, self::KILOMETER,
      self::INCH, self::FOOT, self::NAUTICAL_MILE,
    ];
    if (!in_array($unit, $allowed_units)) {
      throw new \InvalidArgumentException(sprintf('Invalid length unit "%s" provided.', $unit));
    }
  }

}
