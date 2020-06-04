<?php

namespace Drupal\physical\Comparator;

use Drupal\physical\Measurement;
use SebastianBergmann\Comparator\Comparator;
use SebastianBergmann\Comparator\ComparisonFailure;

/**
 * Provides a PHPUnit comparator for measurements.
 */
class MeasurementComparator extends Comparator {

  /**
   * {@inheritdoc}
   */
  public function accepts($expected, $actual) {
    return $expected instanceof Measurement && $actual instanceof Measurement;
  }

  /**
   * {@inheritdoc}
   */
  public function assertEquals($expected, $actual, $delta = 0.0, $canonicalize = FALSE, $ignoreCase = FALSE) {
    assert($expected instanceof Measurement);
    assert($actual instanceof Measurement);
    if (!$actual->equals($expected)) {
      throw new ComparisonFailure(
        $expected,
        $actual,
        (string) $expected,
        (string) $actual,
        FALSE,
        sprintf('Failed asserting that Measurement %s matches expected %s.', $actual, $expected)
      );
    }
  }

}
