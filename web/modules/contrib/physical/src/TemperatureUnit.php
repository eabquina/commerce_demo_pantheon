<?php

namespace Drupal\physical;

/**
 * Provides common temperature units.
 */
final class TemperatureUnit implements UnitInterface {

  const KELVIN = 'K';
  const CELSIUS = 'C';
  const FAHRENHEIT = 'F';

  /**
   * {@inheritdoc}
   */
  public static function getLabels() {
    return [
      self::KELVIN => t('K'),
      self::CELSIUS => t('&deg;C'),
      self::FAHRENHEIT => t('&deg;F'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function getBaseUnit() {
    return self::CELSIUS;
  }

  /**
   * {@inheritdoc}
   */
  public static function getBaseFactor($unit) {
    self::assertExists($unit);
    // Not used, see Temperature::convert().
    $factors = [
      self::KELVIN => '274.15',
      self::CELSIUS => '1',
      self::FAHRENHEIT => '33.8',
    ];

    return $factors[$unit];
  }

  /**
   * {@inheritdoc}
   */
  public static function assertExists($unit) {
    $allowed_units = [self::KELVIN, self::CELSIUS, self::FAHRENHEIT];
    if (!in_array($unit, $allowed_units)) {
      throw new \InvalidArgumentException(sprintf('Invalid temperature unit "%s" provided.', $unit));
    }
  }

}
