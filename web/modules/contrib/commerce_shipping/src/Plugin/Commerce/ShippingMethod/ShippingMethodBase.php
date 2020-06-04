<?php

namespace Drupal\commerce_shipping\Plugin\Commerce\ShippingMethod;

use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\PackageTypeManagerInterface;
use Drupal\commerce_shipping\ShippingRate;
use Drupal\commerce_shipping\ShippingService;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\state_machine\WorkflowManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the base class for shipping methods.
 */
abstract class ShippingMethodBase extends PluginBase implements ContainerFactoryPluginInterface, ShippingMethodInterface {

  /**
   * The package type manager.
   *
   * @var \Drupal\commerce_shipping\PackageTypeManagerInterface
   */
  protected $packageTypeManager;

  /**
   * The workflow manager.
   *
   * @var \Drupal\state_machine\WorkflowManagerInterface
   */
  protected $workflowManager;

  /**
   * The shipping services.
   *
   * @var \Drupal\commerce_shipping\ShippingService[]
   */
  protected $services = [];

  /**
   * The parent config entity.
   *
   * Not available while the plugin is being configured.
   *
   * @var \Drupal\commerce_shipping\Entity\ShippingMethodInterface
   */
  protected $parentEntity;

  /**
   * Constructs a new ShippingMethodBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\commerce_shipping\PackageTypeManagerInterface $package_type_manager
   *   The package type manager.
   * @param \Drupal\state_machine\WorkflowManagerInterface $workflow_manager
   *   The workflow manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, PackageTypeManagerInterface $package_type_manager, WorkflowManagerInterface $workflow_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->packageTypeManager = $package_type_manager;
    $this->workflowManager = $workflow_manager;
    foreach ($this->pluginDefinition['services'] as $id => $label) {
      $this->services[$id] = new ShippingService($id, (string) $label);
    }
    $this->setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.commerce_package_type'),
      $container->get('plugin.manager.workflow')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function setParentEntity(EntityInterface $parent_entity) {
    $this->parentEntity = $parent_entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return (string) $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultPackageType() {
    $package_type_id = $this->configuration['default_package_type'];
    return $this->packageTypeManager->createInstance($package_type_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getServices() {
    // Filter out shipping services disabled by the merchant.
    return array_intersect_key($this->services, array_flip($this->configuration['services']));
  }

  /**
   * {@inheritdoc}
   */
  public function getWorkflowId() {
    return $this->configuration['workflow'];
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'default_package_type' => 'custom_box',
      'services' => [],
      'workflow' => $this->pluginDefinition['workflow'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = NestedArray::mergeDeep($this->defaultConfiguration(), $configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $package_types = $this->packageTypeManager->getDefinitionsByShippingMethod($this->pluginId);
    $package_types = array_map(function ($package_type) {
      return $package_type['label'];
    }, $package_types);
    $services = array_map(function ($service) {
      return $service->getLabel();
    }, $this->services);
    // Select all services by default.
    if (empty($this->configuration['services'])) {
      $service_ids = array_keys($services);
      $this->configuration['services'] = array_combine($service_ids, $service_ids);
    }
    $workflows = $this->workflowManager->getGroupedLabels('commerce_shipment');
    $workflows = reset($workflows);

    $form['default_package_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Default package type'),
      '#options' => $package_types,
      '#default_value' => $this->configuration['default_package_type'],
      '#required' => TRUE,
      '#access' => count($package_types) > 1,
    ];
    $form['services'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Shipping services'),
      '#options' => $services,
      '#default_value' => $this->configuration['services'],
      '#required' => TRUE,
      '#access' => count($services) > 1,
    ];
    $form['workflow'] = [
      '#type' => 'select',
      '#title' => $this->t('Shipment workflow'),
      '#options' => $workflows,
      '#default_value' => $this->configuration['workflow'],
      '#required' => TRUE,
      '#access' => count($workflows) > 1,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValue($form['#parents']);
    /** @var \Drupal\state_machine\Plugin\Workflow\WorkflowInterface $workflow */
    $workflow = $this->workflowManager->createInstance($values['workflow']);

    // Verify "Finalize" transition.
    if (!$workflow->getTransition('finalize')) {
      $form_state->setError($form['workflow'], $this->t('The @workflow workflow does not have a "Finalize" transition.', [
        '@workflow' => $workflow->getLabel(),
      ]));
    }
    // Verify "Cancel" transition.
    if (!$workflow->getTransition('cancel')) {
      $form_state->setError($form['workflow'], $this->t('The @workflow workflow does not have a "Cancel" transition.', [
        '@workflow' => $workflow->getLabel(),
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      if (!empty($values['services'])) {
        $values['services'] = array_filter($values['services']);

        $this->configuration['default_package_type'] = $values['default_package_type'];
        $this->configuration['services'] = array_keys($values['services']);
      }
      $this->configuration['workflow'] = $values['workflow'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function selectRate(ShipmentInterface $shipment, ShippingRate $rate) {
    // Plugins can override this method to store additional information
    // on the shipment when the rate is selected (for example, the rate ID).
    $shipment->setShippingMethodId($rate->getShippingMethodId());
    $shipment->setShippingService($rate->getService()->getId());
    $shipment->setOriginalAmount($rate->getOriginalAmount());
    $shipment->setAmount($rate->getAmount());
  }

}
