<?php

namespace Drupal\commerce_shipping\Plugin\Commerce\CheckoutPane;

use Drupal\commerce\AjaxFormTrait;
use Drupal\commerce\InlineFormManager;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface;
use Drupal\commerce_shipping\OrderShipmentSummaryInterface;
use Drupal\commerce_shipping\PackerManagerInterface;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Element;
use Drupal\profile\Entity\ProfileInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the shipping information pane.
 *
 * Collects the shipping profile, then the information for each shipment.
 * Assumes that all shipments share the same shipping profile.
 *
 * @CommerceCheckoutPane(
 *   id = "shipping_information",
 *   label = @Translation("Shipping information"),
 *   wrapper_element = "fieldset",
 * )
 */
class ShippingInformation extends CheckoutPaneBase implements ContainerFactoryPluginInterface {

  use AjaxFormTrait;

  /**
   * The entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfo
   */
  protected $entityTypeBundleInfo;

  /**
   * The inline form manager.
   *
   * @var \Drupal\commerce\InlineFormManager
   */
  protected $inlineFormManager;

  /**
   * The packer manager.
   *
   * @var \Drupal\commerce_shipping\PackerManagerInterface
   */
  protected $packerManager;

  /**
   * The order shipment summary.
   *
   * @var \Drupal\commerce_shipping\OrderShipmentSummaryInterface
   */
  protected $orderShipmentSummary;

  /**
   * Constructs a new ShippingInformation object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface $checkout_flow
   *   The parent checkout flow.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfo $entity_type_bundle_info
   *   The entity type bundle info.
   * @param \Drupal\commerce\InlineFormManager $inline_form_manager
   *   The inline form manager.
   * @param \Drupal\commerce_shipping\PackerManagerInterface $packer_manager
   *   The packer manager.
   * @param \Drupal\commerce_shipping\OrderShipmentSummaryInterface $order_shipment_summary
   *   The order shipment summary.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, CheckoutFlowInterface $checkout_flow, EntityTypeManagerInterface $entity_type_manager, EntityTypeBundleInfo $entity_type_bundle_info, InlineFormManager $inline_form_manager, PackerManagerInterface $packer_manager, OrderShipmentSummaryInterface $order_shipment_summary) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $checkout_flow, $entity_type_manager);

    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->inlineFormManager = $inline_form_manager;
    $this->packerManager = $packer_manager;
    $this->orderShipmentSummary = $order_shipment_summary;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, CheckoutFlowInterface $checkout_flow = NULL) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $checkout_flow,
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('plugin.manager.commerce_inline_form'),
      $container->get('commerce_shipping.packer_manager'),
      $container->get('commerce_shipping.order_shipment_summary')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'require_shipping_profile' => TRUE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationSummary() {
    if (!empty($this->configuration['require_shipping_profile'])) {
      $summary = $this->t('Hide shipping costs until an address is entered: Yes');
    }
    else {
      $summary = $this->t('Hide shipping costs until an address is entered: No');
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['require_shipping_profile'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide shipping costs until an address is entered'),
      '#default_value' => $this->configuration['require_shipping_profile'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['require_shipping_profile'] = !empty($values['require_shipping_profile']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function isVisible() {
    if (!$this->order->hasField('shipments')) {
      return FALSE;
    }

    // The order must contain at least one shippable purchasable entity.
    foreach ($this->order->getItems() as $order_item) {
      $purchased_entity = $order_item->getPurchasedEntity();
      if ($purchased_entity && $purchased_entity->hasField('weight')) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneSummary() {
    $summary = [];
    if ($this->isVisible()) {
      $summary = $this->orderShipmentSummary->build($this->order, 'checkout');
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    $store = $this->order->getStore();
    $available_countries = [];
    foreach ($store->get('shipping_countries') as $country_item) {
      $available_countries[] = $country_item->value;
    }
    /** @var \Drupal\commerce\Plugin\Commerce\InlineForm\EntityInlineFormInterface $inline_form */
    $inline_form = $this->inlineFormManager->createInstance('customer_profile', [
      'profile_scope' => 'shipping',
      'available_countries' => $available_countries,
      'address_book_uid' => $this->order->getCustomerId(),
      // Don't copy the profile to address book until the order is placed.
      'copy_on_save' => FALSE,
    ], $this->getShippingProfile());

    $pane_form['shipping_profile'] = [
      '#parents' => array_merge($pane_form['#parents'], ['shipping_profile']),
      '#inline_form' => $inline_form,
    ];
    $pane_form['shipping_profile'] = $inline_form->buildInlineForm($pane_form['shipping_profile'], $form_state);
    // The shipping_profile should always exist in form state (and not just
    // after "Recalculate shipping" is clicked).
    if (!$form_state->has('shipping_profile')) {
      $form_state->set('shipping_profile', $inline_form->getEntity());
    }

    $pane_form['recalculate_shipping'] = [
      '#type' => 'button',
      '#value' => $this->t('Recalculate shipping'),
      '#recalculate' => TRUE,
      '#ajax' => [
        'callback' => [get_class($this), 'ajaxRefreshForm'],
        'element' => $pane_form['#parents'],
      ],
      // The calculation process only needs a valid shipping profile.
      '#limit_validation_errors' => [
        array_merge($pane_form['#parents'], ['shipping_profile']),
      ],
    ];
    $pane_form['removed_shipments'] = [
      '#type' => 'value',
      '#value' => [],
    ];
    $pane_form['shipments'] = [
      '#type' => 'container',
    ];

    $shipping_profile = $form_state->get('shipping_profile');
    $shipments = $this->order->get('shipments')->referencedEntities();
    $recalculate_shipping = $form_state->get('recalculate_shipping');
    $force_packing = empty($shipments) && $this->canCalculateRates($shipping_profile);
    if ($recalculate_shipping || $force_packing) {
      // We're still relying on the packer manager for packing the order since
      // we don't want the shipments to be saved for performance reasons.
      // The shipments are saved on pane submission.
      list($shipments, $removed_shipments) = $this->packerManager->packToShipments($this->order, $shipping_profile, $shipments);

      // Store the IDs of removed shipments for submitPaneForm().
      $pane_form['removed_shipments']['#value'] = array_map(function ($shipment) {
        /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
        return $shipment->id();
      }, $removed_shipments);
    }

    $single_shipment = count($shipments) === 1;
    foreach ($shipments as $index => $shipment) {
      /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
      $pane_form['shipments'][$index] = [
        '#parents' => array_merge($pane_form['#parents'], ['shipments', $index]),
        '#array_parents' => array_merge($pane_form['#parents'], ['shipments', $index]),
        '#type' => $single_shipment ? 'container' : 'fieldset',
        '#title' => $shipment->getTitle(),
      ];
      $form_display = EntityFormDisplay::collectRenderDisplay($shipment, 'checkout');
      $form_display->removeComponent('shipping_profile');
      $form_display->buildForm($shipment, $pane_form['shipments'][$index], $form_state);
      $pane_form['shipments'][$index]['#shipment'] = $shipment;
    }

    return $pane_form;
  }

  /**
   * {@inheritdoc}
   */
  public function validatePaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    $shipment_indexes = Element::children($pane_form['shipments']);
    $triggering_element = $form_state->getTriggeringElement();
    $recalculate = !empty($triggering_element['#recalculate']);
    $button_type = isset($triggering_element['#button_type']) ? $triggering_element['#button_type'] : '';
    if (!$recalculate && $button_type == 'primary' && empty($shipment_indexes)) {
      // The checkout step was submitted without shipping being calculated.
      // Force the recalculation now and reload the page.
      $recalculate = TRUE;
      $this->messenger()->addError($this->t('Please select a shipping method.'));
      $form_state->setRebuild(TRUE);
    }

    if ($recalculate) {
      $form_state->set('recalculate_shipping', TRUE);
    }
    /** @var \Drupal\commerce\Plugin\Commerce\InlineForm\EntityInlineFormInterface $inline_form */
    $inline_form = $pane_form['shipping_profile']['#inline_form'];
    // The profile in form state needs to reflect the submitted values,
    // for packers invoked on form rebuild, or "Billing same as shipping".
    $form_state->set('shipping_profile', $inline_form->getEntity());

    foreach ($shipment_indexes as $index) {
      $shipment = clone $pane_form['shipments'][$index]['#shipment'];
      $form_display = EntityFormDisplay::collectRenderDisplay($shipment, 'checkout');
      $form_display->removeComponent('shipping_profile');
      $form_display->extractFormValues($shipment, $pane_form['shipments'][$index], $form_state);
      $form_display->validateFormValues($shipment, $pane_form['shipments'][$index], $form_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    /** @var \Drupal\commerce\Plugin\Commerce\InlineForm\EntityInlineFormInterface $inline_form */
    $inline_form = $pane_form['shipping_profile']['#inline_form'];
    /** @var \Drupal\profile\Entity\ProfileInterface $profile */
    $profile = $inline_form->getEntity();

    // Save the modified shipments.
    $shipments = [];
    foreach (Element::children($pane_form['shipments']) as $index) {
      /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
      $shipment = clone $pane_form['shipments'][$index]['#shipment'];
      $form_display = EntityFormDisplay::collectRenderDisplay($shipment, 'checkout');
      $form_display->removeComponent('shipping_profile');
      $form_display->extractFormValues($shipment, $pane_form['shipments'][$index], $form_state);
      $shipment->setShippingProfile($profile);
      $shipment->save();
      $shipments[] = $shipment;
    }
    $this->order->shipments = $shipments;

    // Delete shipments that are no longer in use.
    $removed_shipment_ids = $pane_form['removed_shipments']['#value'];
    if (!empty($removed_shipment_ids)) {
      $shipment_storage = $this->entityTypeManager->getStorage('commerce_shipment');
      $removed_shipments = $shipment_storage->loadMultiple($removed_shipment_ids);
      $shipment_storage->delete($removed_shipments);
    }
  }

  /**
   * Gets the shipping profile.
   *
   * The shipping profile is assumed to be the same for all shipments.
   * Therefore, it is taken from the first found shipment, or created from
   * scratch if no shipments were found.
   *
   * @return \Drupal\profile\Entity\ProfileInterface
   *   The shipping profile.
   */
  protected function getShippingProfile() {
    $shipping_profile = NULL;
    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
    foreach ($this->order->get('shipments')->referencedEntities() as $shipment) {
      $shipping_profile = $shipment->getShippingProfile();
      break;
    }
    if (!$shipping_profile) {
      $profile_type_id = 'customer';
      // Check whether the order type has another profile type ID specified.
      $order_type_id = $this->order->bundle();
      $order_bundle_info = $this->entityTypeBundleInfo->getBundleInfo('commerce_order');
      if (!empty($order_bundle_info[$order_type_id]['shipping_profile_type'])) {
        $profile_type_id = $order_bundle_info[$order_type_id]['shipping_profile_type'];
      }

      $shipping_profile = $this->entityTypeManager->getStorage('profile')->create([
        'type' => $profile_type_id,
        'uid' => 0,
      ]);
    }

    return $shipping_profile;
  }

  /**
   * Gets whether shipping rates can be calculated for the given profile.
   *
   * Ensures that a required shipping address is present and valid.
   *
   * @param \Drupal\profile\Entity\ProfileInterface $profile
   *   The profile.
   *
   * @return bool
   *   TRUE if shipping rates can be calculated, FALSE otherwise.
   */
  protected function canCalculateRates(ProfileInterface $profile) {
    if (!empty($this->configuration['require_shipping_profile'])) {
      $violations = $profile->get('address')->validate();
      return count($violations) === 0;
    }

    return TRUE;
  }

}
