<?php

namespace Drupal\commerce_shipping\Form;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Form\OrderForm;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\inline_entity_form\Form\EntityInlineForm;

/**
 * Defines the inline form for shipments.
 */
class ShipmentInlineForm extends EntityInlineForm {

  /**
   * {@inheritdoc}
   */
  public function getEntityTypeLabels() {
    return [
      'singular' => new TranslatableMarkup('shipment'),
      'plural' => new TranslatableMarkup('shipments'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function entityForm(array $entity_form, FormStateInterface $form_state) {
    $shipment = $entity_form['#entity'];
    assert($shipment instanceof ShipmentInterface);
    if ($shipment->isNew()) {
      $form_object = $form_state->getFormObject();
      assert($form_object instanceof OrderForm);
      $order = $form_object->getEntity();
      assert($order instanceof OrderInterface);
      $shipment->set('order_id', $order);
    }
    $entity_form = parent::entityForm($entity_form, $form_state);

    // IEF + InlineForms (CustomerProfile) are not compatible when the IEF form
    // is saved/closed. So we disable the "Update" option.
    // @todo remove when IEF + InlineForms are compatible.
    if (!$shipment->isNew()) {
      $entity_form['#after_build'][] = [static::class, 'disableSaveButton'];
    }
    return $entity_form;
  }

  /**
   * Disables the save button for the IEF element.
   *
   * @param array $element
   *   The element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The element.
   */
  public static function disableSaveButton(array $element, FormStateInterface $form_state) {
    $action_button_key = 'ief_' . $element['#op'] . '_save';
    if (isset($element['actions'][$action_button_key])) {
      $element['actions'][$action_button_key]['#access'] = FALSE;
    }
    return $element;
  }

}
