<?php

namespace Drupal\physical\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\physical\MeasurementType;

/**
 * Plugin implementation of the 'physical_measurement_default' formatter.
 *
 * @FieldFormatter(
 *   id = "physical_measurement_default",
 *   label = @Translation("Default"),
 *   field_types = {
 *     "physical_measurement"
 *   }
 * )
 */
class MeasurementDefaultFormatter extends PhysicalFormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $measurement_type = $this->fieldDefinition->getSetting('measurement_type');
    /** @var \Drupal\physical\UnitInterface $unit_class */
    $unit_class = MeasurementType::getUnitClass($measurement_type);
    $unit_labels = $unit_class::getLabels();

    $element = [];
    /** @var \Drupal\physical\Plugin\Field\FieldType\MeasurementItem $item */
    foreach ($items as $delta => $item) {
      $number = $this->numberFormatter->format($item->number);
      $unit = isset($unit_labels[$item->unit]) ? $unit_labels[$item->unit] : $item->unit;

      $element[$delta] = [
        '#markup' => $number . ' ' . $unit,
      ];
    }

    return $element;
  }

}
