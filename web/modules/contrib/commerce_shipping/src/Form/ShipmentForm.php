<?php

namespace Drupal\commerce_shipping\Form;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_shipping\PackageTypeManagerInterface;
use Drupal\commerce_shipping\ShipmentItem;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\physical\Weight;
use Drupal\physical\WeightUnit;
use Drupal\profile\Entity\ProfileInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the shipment add/edit form.
 */
class ShipmentForm extends ContentEntityForm {

  /**
   * The package type manager.
   *
   * @var \Drupal\commerce_shipping\PackageTypeManagerInterface
   */
  protected $packageTypeManager;

  /**
   * Constructs a new ShipmentForm object.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time.
   * @param \Drupal\commerce_shipping\PackageTypeManagerInterface $package_type_manager
   *   The package type manager.
   */
  public function __construct(EntityRepositoryInterface $entity_repository, EntityTypeBundleInfoInterface $entity_type_bundle_info, TimeInterface $time, PackageTypeManagerInterface $package_type_manager) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);

    $this->packageTypeManager = $package_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('plugin.manager.commerce_package_type')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
    $shipment = $this->entity;
    $order_id = $shipment->get('order_id')->target_id;
    if (!$order_id) {
      $order_id = $this->getRouteMatch()->getRawParameter('commerce_order');
      $shipment->set('order_id', $order_id);
    }
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $shipment->getOrder();
    /** @var \Drupal\profile\Entity\ProfileInterface $shipping_profile */
    $shipping_profile = $shipment->getShippingProfile();
    if (!$shipping_profile) {
      /** @var \Drupal\commerce_shipping\Entity\ShipmentTypeInterface $shipment_type */
      $shipment_type = $this->entityTypeManager->getStorage('commerce_shipment_type')->load($shipment->bundle());
      /** @var \Drupal\profile\Entity\ProfileInterface $shipping_profile */
      $shipping_profile = $this->entityTypeManager->getStorage('profile')->create([
        'type' => $shipment_type->getProfileTypeId(),
        'uid' => 0,
      ]);
      $address = [
        '#type' => 'address',
        '#default_value' => [],
      ];
      $shipping_profile->set('address', $address);
      $shipment->setShippingProfile($shipping_profile);
    }
    // Store the original amount for ShipmentForm::save().
    $form_state->set('original_amount', $shipment->getAmount());

    $form = parent::form($form, $form_state);

    // The ShippingProfileWidget doesn't output a fieldset because that makes
    // sense in a checkout context, but on the admin form it is clearer for
    // profile fields to be visually grouped.
    $form['shipping_profile']['widget'][0]['#type'] = 'fieldset';

    // Fixes illegal choice has been detected message upon AJAX reload.
    if (empty($form['shipping_method']['widget'][0]['#options'])) {
      $form['shipping_method']['#access'] = FALSE;
    }

    // Prepare the form for ajax.
    // Not using Html::getUniqueId() on the wrapper ID to avoid #2675688.
    $form['#wrapper_id'] = 'shipping-information-wrapper';
    $form['#prefix'] = '<div id="' . $form['#wrapper_id'] . '">';
    $form['#suffix'] = '</div>';

    $package_types = $this->packageTypeManager->getDefinitions();
    $package_type_options = [];
    foreach ($package_types as $package_type) {
      $unit = ' ' . array_pop($package_type['dimensions']);
      $dimensions = ' (' . implode(' x ', $package_type['dimensions']) . $unit . ')';
      $package_type_options[$package_type['id']] = $package_type['label'] . $dimensions;
    }

    $package_type = $shipment->getPackageType();
    $form['package_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Package Type'),
      '#options' => $package_type_options,
      '#default_value' => $package_type ? $package_type->getId() : '',
      '#access' => count($package_types) > 1,
    ];

    $order_items = $order->getItems();
    /** @var \Drupal\commerce_shipping\ShipmentStorageInterface $shipment_storage */
    $shipment_storage = $this->entityTypeManager->getStorage('commerce_shipment');
    // Get all of the shipments for the current order.
    $order_shipments = $shipment_storage->loadMultipleByOrder($order);
    // Store order_items that are already tied to shipments on this order.
    $already_on_shipment = [];
    foreach ($order_shipments as $order_shipment) {
      if ($order_shipment->id() != $shipment->id()) {
        $shipment_items = $order_shipment->getItems();
        foreach ($shipment_items as $shipment_item) {
          $order_item_id = $shipment_item->getOrderItemId();
          $already_on_shipment[$order_item_id] = $order_item_id;
        }
      }
    }

    $shipment_item_options = [];
    // Populates the default values by looking at the items already in this
    // shipment.
    $shipment_item_defaults = [];
    $shipment_items = $shipment->getItems();
    /** @var \Drupal\commerce_shipping\ShipmentItem $shipment_item */
    foreach ($shipment_items as $shipment_item) {
      $shipment_item_id = $shipment_item->getOrderItemId();
      $shipment_item_defaults[$shipment_item_id] = $shipment_item_id;
      $shipment_item_options[$shipment_item_id] = $shipment_item->getTitle();
    }

    /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
    foreach ($order_items as $order_item) {
      // Skip shipment items that are already on this shipment.
      if (isset($shipment_item_options[$order_item->id()]) ||
        !$order_item->hasField('purchased_entity') ||
        in_array($order_item->id(), $already_on_shipment, TRUE)) {
        continue;
      }

      // Only allow items that aren't already on a shipment
      // have a purchasable entity and implement the shippable trait.
      $purchasable_entity = $order_item->getPurchasedEntity();
      if (!empty($purchasable_entity) && $purchasable_entity->hasField('weight')) {
        $shipment_item_options[$order_item->id()] = $order_item->label();
      }
    }

    $form['shipment_items'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Shipment items'),
      '#options' => $shipment_item_options,
      '#default_value' => $shipment_item_defaults,
      '#required' => TRUE,
      '#weight' => 48,
    ];
    $form['recalculate_shipping'] = [
      '#type' => 'button',
      '#value' => $this->t('Recalculate shipping'),
      '#recalculate' => TRUE,
      '#ajax' => [
        'callback' => [get_class($this), 'ajaxRefresh'],
        'wrapper' => $form['#wrapper_id'],
      ],
      // The calculation process only needs a valid shipping profile.
      '#limit_validation_errors' => [
        array_merge($form['#parents'], ['shipping_profile']),
      ],
      '#weight' => 49,
      '#after_build' => [
        [static::class, 'clearValues'],
      ],
    ];

    return $form;
  }

  /**
   * Ajax callback.
   */
  public static function ajaxRefresh(array $form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $parents = array_slice($triggering_element['#parents'], 0, -1);
    return NestedArray::getValue($form, $parents);
  }

  /**
   * Clears user input of selected shipping rates if recalculation occured.
   *
   * This is required to prevent invalid options being selected is a shipping
   * rate is no longer available.
   *
   * @param array $element
   *   The element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The element.
   */
  public static function clearValues(array $element, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    if (!$triggering_element) {
      return $element;
    }
    $triggering_element_name = end($triggering_element['#parents']);
    if ($triggering_element_name === 'recalculate_shipping') {
      $user_input = &$form_state->getUserInput();
      unset($user_input['shipping_method']);
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $triggering_element = $form_state->getTriggeringElement();
    $recalculate = !empty($triggering_element['#recalculate']);
    if ($recalculate) {
      $form_state->set('recalculate_shipping', TRUE);
      /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
      $shipment = $this->entity;
      $shipment->setTitle($form_state->getValue('title'));

      $base_form_key = ['shipping_profile', '0', 'profile'];
      $selected_profile_key = array_merge($base_form_key, ['select_address']);
      $selected_profile_id = $form_state->getValue($selected_profile_key);
      $address_key = array_merge($base_form_key, ['address', '0', 'address']);
      $address = $form_state->getValue($address_key);
      // If an address was input, use that as an address override.
      if ($address !== NULL) {
        $shipment->getShippingProfile()->get('address')->setValue($address);
      }
      // If a different profile was selected, load it and use its address.
      elseif ($selected_profile_id !== '_original') {
        $profile_storage = $this->entityTypeManager->getStorage('profile');
        $selected_profile = $profile_storage->load($selected_profile_id);
        assert($selected_profile instanceof ProfileInterface);
        $shipment->getShippingProfile()->set('address', $selected_profile->get('address'));
      }

      // Add the shipping items.
      $this->addShippingItems($form, $form_state);

      if (empty($form_state->getValue('package_type'))) {
        return;
      }
      $package_type = $this->packageTypeManager->createInstance($form_state->getValue('package_type'));
      $shipment->setPackageType($package_type);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
    $shipment = $this->getEntity();
    $this->addShippingItems($form, $form_state);
    $shipment->setData('owned_by_packer', FALSE);
    $shipment->save();

    // Make sure the shipment gets added to the order.
    $order = $shipment->getOrder();
    $order_shipments = $order->get('shipments');
    $shipment_exists = FALSE;
    $save_order = FALSE;

    // Loop over the order shipments to make sure this
    // shipment exists.
    foreach ($order_shipments->getValue() as $order_shipment) {
      if ($order_shipment['target_id'] == $shipment->id()) {
        $shipment_exists = TRUE;
      }
    }

    // Check if the shipment amount has changed, if so we need to trigger
    // an order refresh so that the shipping adjustment gets adjusted.
    if ($form_state->get('original_amount') != $shipment->getAmount()) {
      $order->setRefreshState(OrderInterface::REFRESH_ON_SAVE);
      $save_order = TRUE;
    }

    // Add the shipment to the order if it doesn't exist.
    if (!$shipment_exists) {
      $order_shipments->appendItem($shipment);
      $save_order = TRUE;
    }

    // Save the parent order if the shipment amount has changed or if the
    // shipment was appended to the order.
    if ($save_order) {
      $order->save();
    }

    $this->messenger()->addMessage($this->t('Shipment for order @order created.', ['@order' => $order->getOrderNumber()]));
    $form_state->setRedirect('entity.commerce_shipment.collection', ['commerce_order' => $order->id()]);
  }

  /**
   * Creates new shipping items from the form and adds them to the shipment.
   *
   * @param array $form
   *   A nested array of form elements comprising the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  protected function addShippingItems(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
    $shipment = $this->entity;
    // Clear the shipping items to make sure the list is fresh when we add them.
    $shipment->setItems([]);
    /** @var \Drupal\commerce_shipping\ShipmentItem $shipment_item */
    foreach ($form_state->getValue('shipment_items') as $key => $value) {
      if ($value == 0) {
        // The item was not included in the shipment.
        continue;
      }
      /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
      $order_item = $this->entityTypeManager->getStorage('commerce_order_item')->load($key);
      $quantity = $order_item->getQuantity();
      $purchased_entity = $order_item->getPurchasedEntity();

      if ($purchased_entity->get('weight')->isEmpty()) {
        $weight = new Weight(1, WeightUnit::GRAM);
      }
      else {
        /** @var \Drupal\physical\Plugin\Field\FieldType\MeasurementItem $weight_item */
        $weight_item = $purchased_entity->get('weight')->first();
        $weight = $weight_item->toMeasurement();
      }

      $shipment_item = new ShipmentItem([
        'order_item_id' => $order_item->id(),
        'title' => $purchased_entity->label(),
        'quantity' => $quantity,
        'weight' => $weight->multiply($quantity),
        'declared_value' => $order_item->getTotalPrice(),
      ]);
      $shipment->addItem($shipment_item);
    }
  }

}
