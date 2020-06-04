<?php

namespace Drupal\commerce_shipping\Plugin\Field\FieldWidget;

use CommerceGuys\Intl\Formatter\CurrencyFormatterInterface;
use Drupal\commerce_shipping\ShipmentManagerInterface;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Validation\Plugin\Validation\Constraint\NotNullConstraint;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Plugin implementation of 'commerce_shipping_rate'.
 *
 * @FieldWidget(
 *   id = "commerce_shipping_rate",
 *   label = @Translation("Shipping rate"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class ShippingRateWidget extends WidgetBase implements ContainerFactoryPluginInterface {

  /**
   * The currency formatter.
   *
   * @var \CommerceGuys\Intl\Formatter\CurrencyFormatterInterface
   */
  protected $currencyFormatter;

  /**
   * The shipment manager.
   *
   * @var \Drupal\commerce_shipping\ShipmentManagerInterface
   */
  protected $shipmentManager;

  /**
   * Constructs a new ShippingRateWidget object.
   *
   * @param string $plugin_id
   *   The plugin_id for the widget.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the widget is associated.
   * @param array $settings
   *   The widget settings.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \CommerceGuys\Intl\Formatter\CurrencyFormatterInterface $currency_formatter
   *   The currency formatter.
   * @param \Drupal\commerce_shipping\ShipmentManagerInterface $shipment_manager
   *   The shipment manager.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, CurrencyFormatterInterface $currency_formatter, ShipmentManagerInterface $shipment_manager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);

    $this->currencyFormatter = $currency_formatter;
    $this->shipmentManager = $shipment_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('commerce_price.currency_formatter'),
      $container->get('commerce_shipping.shipment_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
    $shipment = $items[$delta]->getEntity();
    $rates = $this->shipmentManager->calculateRates($shipment);
    if (!$rates) {
      $element = [
        '#markup' => $this->t('There are no shipping rates available for this address.'),
      ];
      return $element;
    }

    $default_rate = $this->shipmentManager->selectDefaultRate($shipment, $rates);
    $element['#type'] = 'radios';
    $element['#default_value'] = $default_rate->getId();
    $element['#options'] = [];
    foreach ($rates as $rate_id => $rate) {
      $original_amount = $rate->getOriginalAmount();
      $amount = $rate->getAmount();
      if ($original_amount->greaterThan($amount)) {
        $rate_label = new FormattableMarkup('@service: <s>@original_amount</s> @amount', [
          '@service' => $rate->getService()->getLabel(),
          '@original_amount' => $this->currencyFormatter->format($original_amount->getNumber(), $original_amount->getCurrencyCode()),
          '@amount' => $this->currencyFormatter->format($amount->getNumber(), $amount->getCurrencyCode()),
        ]);
      }
      else {
        $rate_label = new FormattableMarkup('@service: @amount', [
          '@service' => $rate->getService()->getLabel(),
          '@amount' => $this->currencyFormatter->format($amount->getNumber(), $amount->getCurrencyCode()),
        ]);
      }
      $element['#options'][$rate_id] = $rate_label;
      $element[$rate_id]['#description'] = $rate->getDescription();
      // Tell Bootstrap not to turn the description into a tooltip.
      $element[$rate_id]['#smart_description'] = FALSE;
      // Store the rate object for use in extractFormValues().
      $element[$rate_id]['#rate'] = $rate;
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function extractFormValues(FieldItemListInterface $items, array $form, FormStateInterface $form_state) {
    $field_name = $this->fieldDefinition->getName();
    $parents = array_merge($form['#parents'], [$field_name, 0]);
    $element = NestedArray::getValue($form, [$field_name, 'widget', 0]);
    $selected_value = NestedArray::getValue($form_state->getValues(), $parents, $key_exists);
    if ($selected_value) {
      /** @var \Drupal\commerce_shipping\ShippingRate $rate */
      $rate = $element[$selected_value]['#rate'];
      /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
      $shipment = $items[0]->getEntity();
      $this->shipmentManager->applyRate($shipment, $rate);
      // Put delta mapping in $form_state, so that flagErrors() can use it.
      $field_state = static::getWidgetState($form['#parents'], $field_name, $form_state);
      foreach ($items as $delta => $item) {
        $field_state['original_deltas'][$delta] = $delta;
      }
      static::setWidgetState($form['#parents'], $field_name, $form_state, $field_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    $entity_type = $field_definition->getTargetEntityTypeId();
    $field_name = $field_definition->getName();
    return $entity_type == 'commerce_shipment' && $field_name == 'shipping_method';
  }

  /**
   * {@inheritdoc}
   */
  public function flagErrors(FieldItemListInterface $items, ConstraintViolationListInterface $violations, array $form, FormStateInterface $form_state) {
    foreach ($violations as $offset => $violation) {
      /** @var \Symfony\Component\Validator\ConstraintViolationInterface $violation */
      if ($violation->getCode() == NotNullConstraint::IS_NULL_ERROR) {
        // There are no setters on ConstraintValidation.
        $new = new ConstraintViolation(
          $this->t('A valid shipping method must be selected in order to check out.'),
          $violation->getMessageTemplate(),
          $violation->getParameters(),
          $violation->getRoot(),
          $violation->getPropertyPath(),
          $violation->getInvalidValue(),
          $violation->getPlural(),
          $violation->getCode(),
          new NotNullConstraint()
        );
        $violations->remove($offset);
        $violations->add($new);
      }
    }
    return parent::flagErrors($items, $violations, $form, $form_state);
  }

}
