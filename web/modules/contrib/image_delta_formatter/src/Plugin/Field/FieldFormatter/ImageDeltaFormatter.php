<?php

namespace Drupal\image_delta_formatter\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\image\Plugin\Field\FieldFormatter\ImageFormatter;

/**
 * Plugin implementation of the 'image_delta_formatter' formatter.
 *
 * @FieldFormatter(
 *   id = "image_delta_formatter",
 *   label = @Translation("Image delta"),
 *   description = @Translation("Display specific deltas of an image field."),
 *   field_types = {
 *     "image"
 *   }
 * )
 */
class ImageDeltaFormatter extends ImageFormatter {
  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'deltas' => 0,
      'deltas_reversed' => FALSE,
      ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);

    $element['deltas'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Delta'),
      '#description' => $this->t('Enter a delta, or a comma-separated list of deltas that should be shown. For example: 0, 1, 4.'),
      '#size' => 10,
      '#default_value' => $this->getSetting('deltas'),
      '#required' => TRUE,
      '#weight' => -20,
    ];
    $element['deltas_reversed'] = [
      '#title' => $this->t('Reversed'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('deltas_reversed'),
      '#description' => $this->t('Start from the last values.'),
      '#weight' => -10,
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $settings = $this->getSettings();
    $summary = parent::settingsSummary();

    $count = count(explode(',', $settings['deltas']));
    $args = [
      '@deltas' => trim($settings['deltas']),
    ];
    $delta_summary = empty($settings['deltas_reversed']) ? $this->formatPlural($count, 'Delta: @deltas', 'Deltas: @deltas', $args) : $this->formatPlural($count, 'Delta: @deltas (reversed, no effect).', 'Deltas: @deltas (reversed).', $args);
    $summary[] = $delta_summary;

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntitiesToView(EntityReferenceFieldItemListInterface $items, $langcode) {
    $files = parent::getEntitiesToView($items, $langcode);
    // Prepare an array of selected deltas from the entered string.
    if (Unicode::strpos($this->getSetting('deltas'), ',')) {
      $deltas = explode(',', $this->getSetting('deltas'));
      $deltas = array_map('trim', $deltas);
    }
    else {
      $delta = trim($this->getSetting('deltas'));
      $deltas = [$delta];
    }

    foreach (array_keys($files) as $delta) {
      if (!in_array($delta, $deltas)) {
        unset($files[$delta]);
      }
    }

    // Reverse the items if needed.
    if ($this->getSetting('deltas_reversed')) {
      $files = array_reverse($files);
    }

    return $files;

  }


}
