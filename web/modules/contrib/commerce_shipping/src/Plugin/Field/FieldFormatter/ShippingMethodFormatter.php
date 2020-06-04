<?php

namespace Drupal\commerce_shipping\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'commerce_shipping_method' formatter.
 *
 * Represents the shipping method using the label of the selected service.
 *
 * @FieldFormatter(
 *   id = "commerce_shipping_method",
 *   label = @Translation("Shipping method"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class ShippingMethodFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * Constructs a ShippingMethodFormatter instance.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, EntityRepositoryInterface $entity_repository) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);

    $this->entityRepository = $entity_repository;
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
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('entity.repository')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
    $shipment = $items->getEntity();
    $shipping_service_id = $shipment->getShippingService();

    $elements = [];
    foreach ($items as $delta => $item) {
      /** @var \Drupal\commerce_shipping\Entity\ShippingMethodInterface $shipping_method */
      $shipping_method = $item->entity;
      if (!$shipping_method) {
        // The shipping method could not be loaded, it was probably deleted.
        continue;
      }
      $shipping_method = $this->entityRepository->getTranslationFromContext($shipping_method, $langcode);
      $shipping_services = $shipping_method->getPlugin()->getServices();
      if (isset($shipping_services[$shipping_service_id])) {
        $elements[$delta] = [
          '#markup' => $shipping_services[$shipping_service_id]->getLabel(),
        ];
      }
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    $entity_type = $field_definition->getTargetEntityTypeId();
    $field_name = $field_definition->getName();
    return $entity_type == 'commerce_shipment' && $field_name == 'shipping_method';
  }

}
