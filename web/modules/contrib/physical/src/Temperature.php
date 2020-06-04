<?php

namespace Drupal\physical;

/**
 * Provides a value object for temperature amounts.
 *
 * Usage example:
 * @code
 *   $temperature = new Temperature('98.6', TemperatureUnit::FAHRENHEIT);
 * @endcode
 */
final class Temperature extends Measurement {

  /**
   * The measurement type.
   *
   * @var string
   */
  protected $type = MeasurementType::TEMPERATURE;

  /**
   * Converts the current temperature measurement to a new unit.
   *
   * @param string $new_unit
   *   The new unit.
   *
   * @return static
   *   The resulting temperature.
   */
  public function convert($new_unit) {
    TemperatureUnit::assertExists($new_unit);
    // The base factors are not used because the formulas change depending
    // on the units being converted.
    $new_number = '0';
    switch ($this->unit) {
      case TemperatureUnit::KELVIN:
        if ($new_unit == TemperatureUnit::CELSIUS) {
          // http://www.rapidtables.com/convert/temperature/kelvin-to-celsius.htm
          $new_number = Calculator::subtract($this->number, '273.15');
        }
        else {
          // @see http://www.rapidtables.com/convert/temperature/kelvin-to-fahrenheit.htm
          $new_number = Calculator::subtract(Calculator::multiply($this->number, '1.8'), '459.67');
        }
        break;

      case TemperatureUnit::CELSIUS:
        if ($new_unit == TemperatureUnit::FAHRENHEIT) {
          // http://www.rapidtables.com/convert/temperature/how-celsius-to-fahrenheit.htm
          $new_number = Calculator::add(Calculator::multiply($this->number, '1.8'), '32');
        }
        else {
          // http://www.rapidtables.com/convert/temperature/celsius-to-kelvin.htm
          $new_number = Calculator::add($this->number, '273.15');
        }
        break;

      case TemperatureUnit::FAHRENHEIT:
        if ($new_unit == TemperatureUnit::CELSIUS) {
          // @see http://www.rapidtables.com/convert/temperature/fahrenheit-to-celsius.htm
          $new_number = Calculator::divide(Calculator::subtract($this->number, '32'), '1.8');
        }
        else {
          // http://www.rapidtables.com/convert/temperature/fahrenheit-to-kelvin.htm
          $new_number = Calculator::divide(Calculator::add($this->number, '459.67'), '1.8');
        }
        break;
    }

    return new static($new_number, $new_unit);
  }

}
