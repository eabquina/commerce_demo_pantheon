<?php

namespace Drupal\physical\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\physical\LengthUnit;

/**
 * Plugin implementation of the 'physical_dimensions_default' formatter.
 *
 * @FieldFormatter(
 *   id = "physical_dimensions_default",
 *   label = @Translation("Default"),
 *   field_types = {
 *     "physical_dimensions"
 *   }
 * )
 */
class DimensionsDefaultFormatter extends PhysicalFormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $unit_labels = LengthUnit::getLabels();
    $element = [];
    /** @var \Drupal\physical\Plugin\Field\FieldType\DimensionsItem $item */
    foreach ($items as $delta => $item) {
      $dimensions = [
        $this->numberFormatter->format($item->length),
        $this->numberFormatter->format($item->width),
        $this->numberFormatter->format($item->height),
      ];
      $unit = isset($unit_labels[$item->unit]) ? $unit_labels[$item->unit] : $item->unit;

      $element[$delta] = [
        '#markup' => implode(' &times; ', $dimensions) . ' ' . $unit,
      ];
    }

    return $element;
  }

}
