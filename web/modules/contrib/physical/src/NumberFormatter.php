<?php

namespace Drupal\physical;

use Drupal\Core\Language\LanguageManagerInterface;

/**
 * Default number formatter.
 *
 * Uses the intl NumberFormatter class, if the intl PHP extension is enabled.
 *
 * Commerce swaps out this class in order to use its own NumberFormatter which
 * does not depend on the intl extension.
 */
class NumberFormatter implements NumberFormatterInterface {

  /**
   * The intl number formatter.
   *
   * @var \NumberFormatter
   */
  protected $numberFormatter;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Constructs a new NumberFormatter object.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(LanguageManagerInterface $language_manager) {
    if (extension_loaded('intl')) {
      $language = $language_manager->getConfigOverrideLanguage() ?: $language_manager->getCurrentLanguage();
      $this->numberFormatter = new \NumberFormatter($language->getId(), \NumberFormatter::DECIMAL);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function format($number, array $options = []) {
    $default_options = [
      'use_grouping' => TRUE,
      'minimum_fraction_digits' => 0,
      'maximum_fraction_digits' => 6,
    ];
    $options = array_replace($default_options, $options);
    if ($this->numberFormatter) {
      $this->numberFormatter->setAttribute(\NumberFormatter::GROUPING_USED, $options['use_grouping']);
      $this->numberFormatter->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, $options['minimum_fraction_digits']);
      $this->numberFormatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, $options['maximum_fraction_digits']);
      $number = $this->numberFormatter->format($number);
    }
    else {
      if ($options['minimum_fraction_digits'] == 0) {
        $number = Calculator::trim($number);
      }
    }

    return $number;
  }

  /**
   * {@inheritdoc}
   */
  public function parse($number) {
    if ($this->numberFormatter) {
      $number = $this->numberFormatter->parse($number);
      // The returned number should be a string.
      if (is_numeric($number)) {
        $number = (string) $number;
      }
    }
    elseif (!is_numeric($number)) {
      // The intl extension is missing, validate the number at least.
      $number = FALSE;
    }
    return $number;
  }

}
