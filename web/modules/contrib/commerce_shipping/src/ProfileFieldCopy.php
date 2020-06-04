<?php

namespace Drupal\commerce_shipping;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Form\BaseFormIdInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\profile\Entity\ProfileInterface;

/**
 * Default implementation of profile field copying ("Billing same as shipping").
 *
 * Supports copying at checkout and on the admin order edit page.
 * At checkout the shipping and billing panes can be on the same step, or
 * on separate steps, assuming that the shipping pane comes first.
 *
 * Note that a billing profile can either be populated from the shipping
 * profile or from the address book, never both at the same time.
 * When profile field copying is enabled, the address book elements are hidden
 * and the address book is not populated with the billing information.
 */
class ProfileFieldCopy implements ProfileFieldCopyInterface {

  use StringTranslationTrait;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Constructs a new ProfileFieldCopy object.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   */
  public function __construct(AccountInterface $current_user) {
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsForm(array &$inline_form, FormStateInterface $form_state) {
    if (!isset($inline_form['#profile_scope']) || $inline_form['#profile_scope'] != 'billing') {
      return FALSE;
    }
    $order = self::getOrder($form_state);
    if (!$order) {
      // The inline form is being used outside of an order context
      // (e.g. the payment method add/edit screen).
      return FALSE;
    }
    if (!$order->hasField('shipments')) {
      // The order is not shippable.
      return FALSE;
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function alterForm(array &$inline_form, FormStateInterface $form_state) {
    $shipping_profile = self::getShippingProfile($form_state);
    if (!$shipping_profile) {
      // No source information is available.
      return;
    }
    $billing_profile = self::getBillingProfile($inline_form);
    $shipping_form_display = self::getFormDisplay($shipping_profile, 'shipping');
    $shipping_fields = array_keys($shipping_form_display->getComponents());
    $user_input = (array) NestedArray::getValue($form_state->getUserInput(), $inline_form['#parents']);
    // Copying is enabled by default for new billing profiles.
    $enabled = $billing_profile->getData('copy_fields', $billing_profile->isNew());
    if ($user_input && isset($user_input['copy_fields'])) {
      $enabled = $user_input['copy_fields']['enable'];
    }

    $inline_form['copy_fields'] = [
      '#parents' => array_merge($inline_form['#parents'], ['copy_fields']),
      '#type' => 'container',
      '#weight' => -1000,
      '#shipping_fields' => $shipping_fields,
      '#has_form' => FALSE,
    ];
    $inline_form['copy_fields']['enable'] = [
      '#type' => 'checkbox',
      '#title' => $this->getCopyLabel($inline_form),
      '#default_value' => $enabled,
      '#ajax' => [
        'callback' => [get_class($this), 'ajaxRefresh'],
        'wrapper' => $inline_form['#id'],
      ],
    ];

    if ($enabled) {
      // Copy over the current shipping field values, allowing widgets such as
      // TaxNumberDefaultWidget to rely on them. These values might change
      // during submit, so the profile is populated again in submitForm().
      $billing_profile->populateFromProfile($shipping_profile, $shipping_fields);
      // Disable address book copying and remove all existing fields.
      $inline_form['copy_to_address_book'] = [
        '#type' => 'value',
        '#value' => FALSE,
      ];
      foreach (Element::getVisibleChildren($inline_form) as $key) {
        if (!in_array($key, ['copy_fields', 'copy_to_address_book'])) {
          $inline_form[$key]['#access'] = FALSE;
        }
      }
      // Add field widgets for any non-copied billing fields.
      $form_display = self::getFormDisplay($billing_profile, 'billing', $shipping_fields);
      $billing_fields = array_keys($form_display->getComponents());
      if ($billing_fields) {
        $form_display->buildForm($billing_profile, $inline_form['copy_fields'], $form_state);
        $inline_form['copy_fields']['#has_form'] = TRUE;
      }
      // Replace the existing validate/submit handlers with custom ones.
      foreach ($inline_form['#element_validate'] as &$validate_handler) {
        if ($validate_handler[1] == 'runValidate') {
          $validate_handler = [get_class($this), 'validateForm'];
          break;
        }
      }
      foreach ($inline_form['#commerce_element_submit'] as &$submit_handler) {
        if ($submit_handler[1] == 'runSubmit') {
          $submit_handler = [get_class($this), 'submitForm'];
          break;
        }
      }
    }
    else {
      $billing_profile->unsetData('copy_fields');
    }
  }

  /**
   * Gets the copy label for the given inline form.
   *
   * @param array $inline_form
   *   The inline form.
   *
   * @return string
   *   The copy label.
   */
  protected function getCopyLabel(array $inline_form) {
    /** @var \Drupal\commerce\Plugin\Commerce\InlineForm\EntityInlineFormInterface $plugin */
    $plugin = $inline_form['#inline_form'];
    $configuration = $plugin->getConfiguration();
    $is_owner = FALSE;
    if (empty($configuration['admin'])) {
      $is_owner = $this->currentUser->id() == $configuration['address_book_uid'];
    }

    if ($is_owner) {
      $copy_label = $this->t('My billing information is the same as my shipping information.');
    }
    else {
      $copy_label = $this->t('Billing information is the same as the shipping information.');
    }

    return $copy_label;
  }

  /**
   * Ajax callback.
   */
  public static function ajaxRefresh(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $inline_form = NestedArray::getValue($form, array_slice($triggering_element['#array_parents'], 0, -2));
    return $inline_form;
  }

  /**
   * Validates the inline form.
   *
   * @param array $inline_form
   *   The inline form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public static function validateForm(array &$inline_form, FormStateInterface $form_state) {
    $shipping_fields = $inline_form['copy_fields']['#shipping_fields'];
    if ($inline_form['copy_fields']['#has_form']) {
      $billing_profile = self::getBillingProfile($inline_form);
      $form_display = self::getFormDisplay($billing_profile, 'billing', $shipping_fields);
      $form_display->extractFormValues($billing_profile, $inline_form['copy_fields'], $form_state);
      $form_display->validateFormValues($billing_profile, $inline_form['copy_fields'], $form_state);
    }
  }

  /**
   * Submits the inline form.
   *
   * @param array $inline_form
   *   The inline form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public static function submitForm(array &$inline_form, FormStateInterface $form_state) {
    $shipping_fields = $inline_form['copy_fields']['#shipping_fields'];
    $shipping_profile = self::getShippingProfile($form_state);
    $billing_profile = self::getBillingProfile($inline_form);

    $billing_profile->populateFromProfile($shipping_profile, $shipping_fields);
    if ($inline_form['copy_fields']['#has_form']) {
      $form_display = self::getFormDisplay($billing_profile, 'billing', $shipping_fields);
      $form_display->extractFormValues($billing_profile, $inline_form['copy_fields'], $form_state);
    }
    $billing_profile->setData('copy_fields', TRUE);
    $billing_profile->unsetData('copy_to_address_book');
    // Transfer the source address book ID to ensure that the right option
    // is preselected when the copy_fields checkbox is unchecked.
    $address_book_profile_id = $shipping_profile->getData('address_book_profile_id');
    if ($address_book_profile_id && $shipping_profile->bundle() == $billing_profile->bundle()) {
      $billing_profile->setData('address_book_profile_id', $address_book_profile_id);
    }
    $billing_profile->save();
  }

  /**
   * Gets the billing profile from the inline form.
   *
   * @param array $inline_form
   *   The inline form.
   *
   * @return \Drupal\profile\Entity\ProfileInterface
   *   The profile.
   */
  protected static function getBillingProfile(array &$inline_form) {
    /** @var \Drupal\commerce\Plugin\Commerce\InlineForm\EntityInlineFormInterface $plugin */
    $plugin = $inline_form['#inline_form'];
    $profile = $plugin->getEntity();
    assert($profile instanceof ProfileInterface);

    return $profile;
  }

  /**
   * Gets the shipping profile from the parent form.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return \Drupal\profile\Entity\ProfileInterface|null
   *   The shipping profile, or NULL if not found.
   */
  protected static function getShippingProfile(FormStateInterface $form_state) {
    if ($form_state->has('shipping_profile')) {
      // Shipping information on the same step as the billing information.
      $shipping_profile = $form_state->get('shipping_profile');
    }
    else {
      $order = self::getOrder($form_state);
      $profiles = $order->collectProfiles();
      $shipping_profile = $profiles['shipping'] ?? NULL;
    }

    return $shipping_profile;
  }

  /**
   * Gets the order from the parent form.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return \Drupal\commerce_order\Entity\OrderInterface|null
   *   The order, or NULL if not found (unrecognized form).
   */
  protected static function getOrder(FormStateInterface $form_state) {
    $form_object = $form_state->getFormObject();
    if (!($form_object instanceof BaseFormIdInterface)) {
      return NULL;
    }

    $order = NULL;
    if ($form_object instanceof EntityFormInterface) {
      $entity = $form_object->getEntity();
      if ($entity->getEntityTypeId() == 'commerce_order') {
        $order = $entity;
      }
    }
    elseif ($form_object->getBaseFormId() == 'commerce_checkout_flow') {
      /** @var \Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface $form_object */
      $order = $form_object->getOrder();
    }

    return $order;
  }

  /**
   * Gets the form display for the given profile and form mode.
   *
   * @param \Drupal\profile\Entity\ProfileInterface $profile
   *   The profile.
   * @param string $form_mode
   *   The form mode.
   * @param string[] $remove_fields
   *   The fields to remove.
   *
   * @return \Drupal\Core\Entity\Display\EntityFormDisplayInterface
   *   The form display.
   */
  protected static function getFormDisplay(ProfileInterface $profile, $form_mode, array $remove_fields = []) {
    // @todo Investigate a static cache for form displays, since we load the
    //   billing/shipping ones twice (once in CustomerProfile, once here).
    $form_display = EntityFormDisplay::collectRenderDisplay($profile, $form_mode);
    $form_display->removeComponent('revision_log_message');
    foreach ($form_display->getComponents() as $name => $component) {
      if (in_array($name, $remove_fields)) {
        $form_display->removeComponent($name);
      }
    }

    return $form_display;
  }

}
