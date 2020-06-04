<?php

namespace Drupal\commerce_shipping\Plugin\Commerce\TaxType;

use Drupal\commerce\EntityUuidMapperInterface;
use Drupal\commerce_order\Adjustment;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_price\Calculator;
use Drupal\commerce_price\RounderInterface;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\ShippingOrderManagerInterface;
use Drupal\commerce_tax\Plugin\Commerce\TaxType\LocalTaxTypeInterface;
use Drupal\commerce_tax\Plugin\Commerce\TaxType\TaxTypeBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Provides the Shipping tax type.
 *
 * @CommerceTaxType(
 *   id = "shipping",
 *   label = "Shipping",
 *   weight = 10,
 * )
 */
class Shipping extends TaxTypeBase {

  /**
   * The entity UUID mapper.
   *
   * @var \Drupal\commerce\EntityUuidMapperInterface
   */
  protected $entityUuidMapper;

  /**
   * The rounder.
   *
   * @var \Drupal\commerce_price\RounderInterface
   */
  protected $rounder;

  /**
   * The shipping order manager.
   *
   * @var \Drupal\commerce_shipping\ShippingOrderManagerInterface
   */
  protected $shippingOrderManager;

  /**
   * Constructs a new Shipping object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\commerce\EntityUuidMapperInterface $entity_uuid_mapper
   *   The entity UUID mapper.
   * @param \Drupal\commerce_price\RounderInterface $rounder
   *   The rounder.
   * @param \Drupal\commerce_shipping\ShippingOrderManagerInterface $shipping_order_manager
   *   The shipping order manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EventDispatcherInterface $event_dispatcher, EntityUuidMapperInterface $entity_uuid_mapper, RounderInterface $rounder, ShippingOrderManagerInterface $shipping_order_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $event_dispatcher);

    $this->entityUuidMapper = $entity_uuid_mapper;
    $this->rounder = $rounder;
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
      $container->get('entity_type.manager'),
      $container->get('event_dispatcher'),
      $container->get('commerce.entity_uuid_mapper'),
      $container->get('commerce_price.rounder'),
      $container->get('commerce_shipping.order_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'strategy' => 'default',
      // The store UUIDs.
      'store_filter' => 'none',
      'stores' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['strategy'] = [
      '#type' => 'radios',
      '#title' => t('Strategy'),
      '#options' => [
        'default' => $this->t("Apply the default (standard) rate of the order's tax type"),
        'highest' => $this->t('Apply the highest rate found on the order'),
        'proportional' => $this->t("Apply each order item's rate proportionally"),
      ],
      '#default_value' => $this->configuration['strategy'],
    ];

    $store_ids = NULL;
    if ($this->configuration['stores']) {
      $store_ids = $this->entityUuidMapper->mapToIds('commerce_store', $this->configuration['stores']);
    }
    $radio_parents = array_merge($form['#parents'], ['store_filter']);
    $radio_path = array_shift($radio_parents);
    $radio_path .= '[' . implode('][', $radio_parents) . ']';

    $form['store_filter'] = [
      '#type' => 'radios',
      '#title' => $this->t('Applies to'),
      '#default_value' => $this->configuration['store_filter'],
      '#options' => [
        'none' => $this->t('All stores'),
        'include' => $this->t('Only the selected stores'),
        'exclude' => $this->t('All except the selected stores'),
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
    $form['container']['stores'] = [
      '#parents' => array_merge($form['#parents'], ['stores']),
      '#type' => 'commerce_entity_select',
      '#title' => $this->t('Stores'),
      '#default_value' => $store_ids,
      '#target_type' => 'commerce_store',
      '#hide_single_entity' => FALSE,
      '#multiple' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration = [];
      $this->configuration['strategy'] = $values['strategy'];
      $this->configuration['store_filter'] = $values['store_filter'];
      $this->configuration['stores'] = [];
      if ($values['store_filter'] != 'none') {
        $this->configuration['stores'] = $this->entityUuidMapper->mapFromIds('commerce_store', $values['stores']);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function applies(OrderInterface $order) {
    if (!$this->shippingOrderManager->isShippable($order)) {
      return FALSE;
    }
    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface[] $shipments */
    $shipments = $order->get('shipments')->referencedEntities();
    if (empty($shipments)) {
      return FALSE;
    }
    $store_filter = $this->configuration['store_filter'];
    if ($store_filter != 'none') {
      $match = in_array($order->getStore()->uuid(), $this->configuration['stores']);
      $match = ($store_filter == 'include') ? $match : !$match;
      if (!$match) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function apply(OrderInterface $order) {
    $tax_adjustments = $order->collectAdjustments(['tax']);
    // Filter-out adjustments with an unknown percentage or source ID,
    // usually indicative of a remote tax type.
    $tax_adjustments = array_filter($tax_adjustments, function (Adjustment $adjustment) {
      $percentage = $adjustment->getPercentage();
      $source_id = $adjustment->getSourceId();

      return isset($percentage) && substr_count($source_id, '|') === 2;
    });
    if (empty($tax_adjustments)) {
      return;
    }

    if ($this->configuration['strategy'] == 'default') {
      $this->applyDefault($order, $tax_adjustments);
    }
    elseif ($this->configuration['strategy'] == 'highest') {
      $this->applyHighest($order, $tax_adjustments);
    }
    elseif ($this->configuration['strategy'] == 'proportional') {
      $this->applyProportional($order, $tax_adjustments);
    }
  }

  /**
   * Applies the default tax rate of the order's tax type.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   * @param \Drupal\commerce_order\Adjustment[] $tax_adjustments
   *   The tax adjustments.
   */
  protected function applyDefault(OrderInterface $order, array $tax_adjustments) {
    // Assume that all tax adjustments have the same tax type and zone ID.
    $tax_adjustment = reset($tax_adjustments);
    list($tax_type_id, $zone_id, $rate_id) = explode('|', $tax_adjustment->getSourceId());
    $tax_type_storage = $this->entityTypeManager->getStorage('commerce_tax_type');
    /** @var \Drupal\commerce_tax\Entity\TaxTypeInterface $tax_type */
    $tax_type = $tax_type_storage->load($tax_type_id);
    if (!$tax_type) {
      return;
    }
    /** @var \Drupal\commerce_tax\Plugin\Commerce\TaxType\LocalTaxTypeInterface $tax_type_plugin */
    $tax_type_plugin = $tax_type->getPlugin();
    if (!($tax_type_plugin instanceof LocalTaxTypeInterface)) {
      return;
    }
    $zones = $tax_type_plugin->getZones();
    $zone = $zones[$zone_id];
    $default_rate = $zone->getDefaultRate();
    $percentage = $default_rate->getPercentage($order->getCalculationDate());

    foreach ($this->getShipments($order) as $shipment) {
      $display_inclusive = $tax_type_plugin->isDisplayInclusive();
      $tax_amount = $this->calculateTaxAmount($shipment, $percentage->getNumber(), $display_inclusive);
      $tax_amount = $this->rounder->round($tax_amount);

      $shipment->addAdjustment(new Adjustment([
        'type' => 'tax',
        'label' => $zone->getDisplayLabel(),
        'amount' => $tax_amount,
        'percentage' => $percentage->getNumber(),
        'source_id' => $tax_type->id() . '|' . $zone->getId() . '|' . $default_rate->getId(),
        'included' => $display_inclusive,
      ]));
    }
  }

  /**
   * Applies the highest tax rate found on the order.
   *
   * If an order has one order item taxed using the standard rate (e.g. 20%)
   * and one taxed using the intermediate rate (e.g. 15%), then the standard
   * rate will be applied, just like with applyDefault().
   *
   * However, if the order only has an order item taxed using the intermediate
   * rate, then the intermediate rate will be applied.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   * @param \Drupal\commerce_order\Adjustment[] $tax_adjustments
   *   The tax adjustments.
   */
  protected function applyHighest(OrderInterface $order, array $tax_adjustments) {
    /** @var \Drupal\commerce_order\Adjustment[] $tax_adjustments_by_source */
    $tax_adjustments_by_source = [];
    foreach ($tax_adjustments as $adjustment) {
      $tax_adjustments_by_source[$adjustment->getSourceId()] = $adjustment;
    }
    // Sort by percentage descending.
    uasort($tax_adjustments_by_source, function (Adjustment $a, Adjustment $b) {
      return $b->getPercentage() <=> $a->getPercentage();
    });
    $highest_adjustment = reset($tax_adjustments_by_source);

    foreach ($this->getShipments($order) as $shipment) {
      $display_inclusive = $highest_adjustment->isIncluded();
      $percentage = $highest_adjustment->getPercentage();
      $tax_amount = $this->calculateTaxAmount($shipment, $percentage, $display_inclusive);
      $tax_amount = $this->rounder->round($tax_amount);

      $definition = ['amount' => $tax_amount] + $highest_adjustment->toArray();
      $shipment->addAdjustment(new Adjustment($definition));
    }
  }

  /**
   * Applies each order item's tax rate proportionally.
   *
   * Logic:
   * 1. Order items are grouped by their tax rates and then summed up.
   * 2. Each group's ratio of the subtotal is calculated.
   * 3. Each group's tax rate is applied to the shipments, multiplied by
   *    the ratio and then rounded.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   * @param \Drupal\commerce_order\Adjustment[] $tax_adjustments
   *   The tax adjustments.
   */
  protected function applyProportional(OrderInterface $order, array $tax_adjustments) {
    if (count($tax_adjustments) === 1) {
      $this->applyHighest($order, $tax_adjustments);
      return;
    }

    // Group order items by tax percentage.
    $groups = [];
    foreach ($order->getItems() as $order_item) {
      $order_item_total = $order_item->getTotalPrice();
      $order_item_tax_adjustments = $order_item->getAdjustments(['tax']);
      $order_item_tax_adjustment = reset($order_item_tax_adjustments);
      $percentage = $order_item_tax_adjustment->getPercentage();
      if (!isset($groups[$percentage])) {
        $groups[$percentage] = [
          'order_item_total' => $order_item_total,
          'tax_adjustment' => $order_item_tax_adjustment,
        ];
      }
      else {
        $previous_total = $groups[$percentage]['order_item_total'];
        $previous_adjustment = $groups[$percentage]['tax_adjustment'];
        $groups[$percentage]['order_item_total'] = $previous_total->add($order_item_total);
        $groups[$percentage]['tax_adjustment'] = $previous_adjustment->add($order_item_tax_adjustment);
      }
    }
    // Sort by percentage descending.
    krsort($groups, SORT_NUMERIC);
    // Calculate the ratio of each group.
    $subtotal = $order->getSubtotalPrice()->getNumber();
    foreach ($groups as $percentage => $group) {
      $order_item_total = $group['order_item_total'];
      $groups[$percentage]['ratio'] = $order_item_total->divide($subtotal)->getNumber();
    }

    foreach ($this->getShipments($order) as $shipment) {
      foreach ($groups as $percentage => $group) {
        $existing_adjustment = $group['tax_adjustment'];
        $display_inclusive = $existing_adjustment->isIncluded();
        $tax_amount = $this->calculateTaxAmount($shipment, $percentage, $display_inclusive);
        $tax_amount = $tax_amount->multiply($group['ratio']);
        $tax_amount = $this->rounder->round($tax_amount);

        $definition = ['amount' => $tax_amount] + $existing_adjustment->toArray();
        $shipment->addAdjustment(new Adjustment($definition));
      }
    }
  }

  /**
   * Gets the order's shipments.
   *
   * Filters out shipments which are still incomplete (no rate selected).
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   *
   * @return \Drupal\commerce_shipping\Entity\ShipmentInterface[]
   *   The shipments.
   */
  protected function getShipments(OrderInterface $order) {
    /** @var \Drupal\commerce_shipping\Entity\Shipment[] $shipments */
    $shipments = $order->get('shipments')->referencedEntities();
    $shipments = array_filter($shipments, function (ShipmentInterface $shipment) {
      return $shipment->getShippingMethodId() && $shipment->getAmount();
    });

    return $shipments;
  }

  /**
   * Calculates the tax amount for the given shipment.
   *
   * @param \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment
   *   The shipment.
   * @param string $percentage
   *   The tax rate percentage.
   * @param bool $included
   *   Whether tax is already included in the price.
   *
   * @return \Drupal\commerce_price\Price
   *   The unrounded tax amount.
   */
  protected function calculateTaxAmount(ShipmentInterface $shipment, $percentage, $included = FALSE) {
    $shipment_amount = $shipment->getAdjustedAmount(['shipping_promotion']);
    $tax_amount = $shipment_amount->multiply($percentage);
    if ($included) {
      $divisor = Calculator::add('1', $percentage);
      $tax_amount = $tax_amount->divide($divisor);
    }

    return $tax_amount;
  }

}
