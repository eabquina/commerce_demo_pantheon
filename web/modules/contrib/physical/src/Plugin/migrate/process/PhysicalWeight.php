<?php

namespace Drupal\physical\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Maps D7 weight values to D8 values.
 *
 * @MigrateProcessPlugin(
 *   id = "physical_weight"
 * )
 */
class PhysicalWeight extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    // The Drupal 8 numeric value was changed to 'number'.
    return [
      'number' => $value['weight'],
      'unit' => $value['unit'],
    ];
  }

}
