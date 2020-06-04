<?php

namespace Drupal\Tests\physical\Unit;

use Drupal\physical\Temperature;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the temperature class.
 *
 * @coversDefaultClass \Drupal\physical\Temperature
 * @group physical
 */
class TemperatureTest extends UnitTestCase {

  /**
   * The Kelvin temperature.
   *
   * @var \Drupal\physical\Temperature
   */
  protected $temperatureKelvin;

  /**
   * The Celsius temperature.
   *
   * @var \Drupal\physical\Temperature
   */
  protected $temperatureCelsius;

  /**
   * The Fahrenheit temperature.
   *
   * @var \Drupal\physical\Temperature
   */
  protected $temperatureFahrenheit;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->temperatureKelvin = new Temperature('504', 'K');
    $this->temperatureCelsius = new Temperature('0', 'C');
    $this->temperatureFahrenheit = new Temperature('65', 'F');
  }

  /**
   * ::covers __construct.
   */
  public function testInvalidUnit() {
    $this->expectException(\InvalidArgumentException::class);
    $temperature = new Temperature('10', 'mm');
  }

  /**
   * Tests unit conversion.
   *
   * ::covers convert.
   */
  public function testConvert() {
    $this->assertEquals(new Temperature('230.85', 'C'), $this->temperatureKelvin->convert('C')->round(2));
    $this->assertEquals(new Temperature('447.53', 'F'), $this->temperatureKelvin->convert('F')->round(2));

    $this->assertEquals(new Temperature('32', 'F'), $this->temperatureCelsius->convert('F')->round());
    $this->assertEquals(new Temperature('273.15', 'K'), $this->temperatureCelsius->convert('K')->round(2));

    $this->assertEquals(new Temperature('18', 'C'), $this->temperatureFahrenheit->convert('C')->round());
    $this->assertEquals(new Temperature('291.48', 'K'), $this->temperatureFahrenheit->convert('K')->round(2));
  }

}
