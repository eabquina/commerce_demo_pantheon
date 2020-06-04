<?php

namespace Drupal\commerce_shipping\Plugin\Commerce\PromotionOffer;

use Drupal\commerce\EntityUuidMapperInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_price\RounderInterface;
use Drupal\commerce_promotion\Entity\PromotionInterface;
use Drupal\commerce_promotion\Plugin\Commerce\PromotionOffer\PromotionOfferBase;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\ShippingOrderManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the base class for shipment offers.
 */
abstract class ShipmentPromotionOfferBase extends PromotionOfferBase implements ShipmentPromotionOfferInterface {

  /**
   * The entity UUID mapper.
   *
   * @var \Drupal\commerce\EntityUuidMapperInterface
   */
  protected $entityUuidMapper;

  /**
   * The shipping order manager.
   *
   * @var \Drupal\commerce_shipping\ShippingOrderManagerInterface
   */
  protected $shippingOrderManager;

  /**
   * Constructs a new ShipmentPromotionOfferBase object.
   *
   * @param array $configuration
   *   The plugin configuration, i.e. an array with configuration values keyed
   *   by configuration option name.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\commerce_price\RounderInterface $rounder
   *   The rounder.
   * @param \Drupal\commerce\EntityUuidMapperInterface $entity_uuid_mapper
   *   The entity UUID mapper.
   * @param \Drupal\commerce_shipping\ShippingOrderManagerInterface $shipping_order_manager
   *   The shipping order manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RounderInterface $rounder, EntityUuidMapperInterface $entity_uuid_mapper, ShippingOrderManagerInterface $shipping_order_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $rounder);

    $this->entityUuidMapper = $entity_uuid_mapper;
    $this->shippingOrderManager = $shipping_order_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('commerce_price.rounder'),
      $container->get('commerce.entity_uuid_mapper'),
      $container->get('commerce_shipping.order_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'display_inclusive' => FALSE,
      'filter' => 'none',
      'shipping_methods' => [],
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $uuids = $this->getShippingMethodUuids();
    $shipping_method_ids = $this->entityUuidMapper->mapToIds('commerce_shipping_method', $uuids);
    $radio_parents = array_merge($form['#parents'], ['filter']);
    $radio_path = array_shift($radio_parents);
    $radio_path .= '[' . implode('][', $radio_parents) . ']';

    $form['display_inclusive'] = [
      '#type' => 'radios',
      '#title' => $this->t('Discount display'),
      '#title_display' => 'invisible',
      '#options' => [
        TRUE => $this->t('Include the discount in the displayed amount'),
        FALSE => $this->t('Only show the discount on the order total summary'),
      ],
      '#default_value' => (int) $this->configuration['display_inclusive'],
    ];
    $form['filter'] = [
      '#type' => 'radios',
      '#title' => $this->t('Applies to'),
      '#default_value' => $this->configuration['filter'],
      '#options' => [
        'none' => $this->t('All shipping methods'),
        'include' => $this->t('Only the selected shipping methods'),
        'exclude' => $this->t('All except the selected shipping methods'),
      ],
    ];
    $form['container'] = [
      '#type' => 'container',
      '#states' => [
        'invisible' => [
          ':input[name="' . $radio_path . '"]' => ['value' => 'none'],
        ],
      ],
    ];
    $form['container']['shipping_methods'] = [
      '#parents' => array_merge($form['#parents'], ['shipping_methods']),
      '#type' => 'commerce_entity_select',
      '#title' => $this->t('Shipping methods'),
      '#default_value' => $shipping_method_ids,
      '#target_type' => 'commerce_shipping_method',
      '#hide_single_entity' => FALSE,
      '#autocomplete_threshold' => 10,
      '#multiple' => TRUE,
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
      $this->configuration['display_inclusive'] = $values['display_inclusive'];
      $this->configuration['filter'] = $values['filter'];
      $this->configuration['shipping_methods'] = [];
      if ($values['filter'] != 'none') {
        // Convert selected shipping method IDs into UUIDs, and store them.
        $uuids = $this->entityUuidMapper->mapFromIds('commerce_shipping_method', $values['shipping_methods']);
        foreach ($uuids as $uuid) {
          $this->configuration['shipping_methods'][] = [
            'shipping_method' => $uuid,
          ];
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function isDisplayInclusive() {
    return $this->configuration['display_inclusive'];
  }

  /**
   * Gets the configured shipping method UUIDs.
   *
   * @return array
   *   The shipping method UUIDs.
   */
  protected function getShippingMethodUuids() {
    return array_column($this->configuration['shipping_methods'], 'shipping_method');
  }

  /**
   * {@inheritdoc}
   */
  public function apply(EntityInterface $order, PromotionInterface $promotion) {
    assert($order instanceof OrderInterface);
    if (!$order->hasField('shipments') || $order->get('shipments')->isEmpty()) {
      return;
    }

    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface[] $shipments */
    $shipments = $order->get('shipments')->referencedEntities();
    foreach ($shipments as $shipment) {
      if ($this->appliesToShipment($shipment)) {
        $this->applyToShipment($shipment, $promotion);
      }
    }
  }

  /**
   * Checks whether the promotion can be applied to the given shipment.
   *
   * @param \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment
   *   The shipment.
   *
   * @return bool
   *   TRUE if promotion can be applied, FALSE otherwise.
   */
  protected function appliesToShipment(ShipmentInterface $shipment) {
    if (!$shipment->getShippingMethodId() || !$shipment->getAmount()) {
      // The shipment is still incomplete, skip it.
      return FALSE;
    }
    if ($this->configuration['filter'] == 'none') {
      // No filtering required.
      return TRUE;
    }
    $shipping_method = $shipment->getShippingMethod();
    if (!$shipping_method) {
      // The referenced shipping method has been deleted.
      return FALSE;
    }
    $match = in_array($shipping_method->uuid(), $this->getShippingMethodUuids());

    return ($this->configuration['filter'] == 'include') ? $match : !$match;
  }

  /**
   * Applies the offer to the given shipment.
   *
   * @param \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment
   *   The shipment.
   * @param \Drupal\commerce_promotion\Entity\PromotionInterface $promotion
   *   The parent promotion.
   */
  abstract protected function applyToShipment(ShipmentInterface $shipment, PromotionInterface $promotion);

}
