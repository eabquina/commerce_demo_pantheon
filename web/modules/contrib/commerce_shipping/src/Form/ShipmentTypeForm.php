<?php

namespace Drupal\commerce_shipping\Form;

use Drupal\commerce\EntityHelper;
use Drupal\commerce\EntityTraitManagerInterface;
use Drupal\commerce\Form\CommerceBundleEntityFormBase;
use Drupal\commerce_order\AddressBookInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ShipmentTypeForm extends CommerceBundleEntityFormBase {

  /**
   * The address book.
   *
   * @var \Drupal\commerce_order\AddressBookInterface
   */
  protected $addressBook;

  /**
   * Constructs a new ShipmentTypeForm object.
   *
   * @param \Drupal\commerce\EntityTraitManagerInterface $trait_manager
   *   The entity trait manager.
   * @param \Drupal\commerce_order\AddressBookInterface $address_book
   *   The address book.
   */
  public function __construct(EntityTraitManagerInterface $trait_manager, AddressBookInterface $address_book) {
    parent::__construct($trait_manager);

    $this->addressBook = $address_book;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.commerce_entity_trait'),
      $container->get('commerce_order.address_book')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    /** @var \Drupal\commerce_shipping\Entity\ShipmentTypeInterface $shipment_type */
    $shipment_type = $this->entity;
    $profile_types = $this->addressBook->loadTypes();
    $shipments_exist = FALSE;
    if (!$this->entity->isNew()) {
      $shipment_storage = $this->entityTypeManager->getStorage('commerce_shipment');
      $shipments_exist = (bool) $shipment_storage->getQuery()
        ->condition('type', $shipment_type->id())
        ->execute();
    }

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $shipment_type->label(),
      '#required' => TRUE,
    ];
    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $shipment_type->id(),
      '#machine_name' => [
        'exists' => '\Drupal\commerce_shipping\Entity\ShipmentType::load',
      ],
      '#maxlength' => EntityTypeInterface::BUNDLE_MAX_LENGTH,
    ];
    $form['profileType'] = [
      '#type' => 'select',
      '#title' => $this->t('Profile type'),
      '#default_value' => $shipment_type->getProfileTypeId(),
      '#options' => EntityHelper::extractLabels($profile_types),
      '#required' => TRUE,
      '#disabled' => $shipments_exist,
    ];
    $form = $this->buildTraitForm($form, $form_state);

    return $this->protectBundleIdElement($form);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $this->validateTraitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $this->entity->save();
    $this->submitTraitForm($form, $form_state);

    $this->messenger()->addStatus($this->t('Saved the %label shipment type.', [
      '%label' => $this->entity->label(),
    ]));
    $form_state->setRedirect('entity.commerce_shipment_type.collection');
  }

}
