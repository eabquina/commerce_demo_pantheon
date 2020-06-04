<?php

namespace Drupal\commerce_shipping;

use Drupal\Core\Form\FormStateInterface;

/**
 * Copies shared field values from the shipping profile to the billing profile.
 */
interface ProfileFieldCopyInterface {

  /**
   * Gets whether the given inline form is supported.
   *
   * Confirms that:
   * - The inline form is used for billing information.
   * - The inline form is embedded on a shippable order's page.
   *
   * @param array $inline_form
   *   The inline form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return bool
   *   TRUE if the element can be attached, FALSE otherwise.
   */
  public function supportsForm(array &$inline_form, FormStateInterface $form_state);

  /**
   * Alters the inline form.
   *
   * Adds the field copy checkbox ("Billing same as shipping').
   * Ensures that field values are copied on submit.
   *
   * @param array $inline_form
   *   The inline form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function alterForm(array &$inline_form, FormStateInterface $form_state);

}
