<?php

namespace Drupal\commerce_demo;

use Drupal\commerce_product\Entity\ProductAttributeValueInterface;
use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\commerce_promotion\Entity\PromotionInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Defines the content exporter.
 *
 * @internal
 *   For internal usage by the Commerce Demo module.
 */
class ContentExporter {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new ContentExporter object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Exports all entities of the given type, restricted by bundle.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $bundle
   *   The bundle.
   *
   * @return array
   *   The exported entities, keyed by UUID.
   */
  public function exportAll($entity_type_id, $bundle = '') {
    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
    if (!$entity_type->entityClassImplements(ContentEntityInterface::class)) {
      throw new \InvalidArgumentException(sprintf('The %s entity type is not a content entity type.', $entity_type_id));
    }

    $storage = $this->entityTypeManager->getStorage($entity_type_id);
    $query = $storage->getQuery();
    if ($bundle_key = $entity_type->getKey('bundle')) {
      $query->condition($bundle_key, $bundle);
    }
    // Root terms need to be imported first.
    if ($entity_type_id == 'taxonomy_term') {
      $query->sort('depth_level', 'ASC');
      $query->sort('name', 'ASC');
    }

    $ids = $query->execute();
    if (!$ids) {
      return [];
    }

    $export = [];
    $entities = $storage->loadMultiple($ids);
    foreach ($entities as $entity) {
      $export[$entity->uuid()] = $this->export($entity);
      // The array is keyed by UUID, no need to have it in the export too.
      unset($export[$entity->uuid()]['uuid']);
    }

    return $export;
  }

  /**
   * Exports the given entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   *
   * @return array
   *   The export array.
   */
  public function export(ContentEntityInterface $entity) {
    $id_key = $entity->getEntityType()->getKey('id');
    $skip_fields = [
      $id_key, 'langcode', 'default_langcode',
      'uid', 'created', 'changed',
    ];

    $export = [];
    foreach ($entity->getFields() as $field_name => $items) {
      if (in_array($field_name, $skip_fields)) {
        continue;
      }
      $items->filterEmptyItems();
      if ($items->isEmpty()) {
        continue;
      }

      $storage_definition = $items->getFieldDefinition()->getFieldStorageDefinition();;
      $list = $items->getValue();
      foreach ($list as $delta => $item) {
        if ($storage_definition->getType() == 'entity_reference') {
          $target_entity_type_id = $storage_definition->getSetting('target_type');
          $target_entity_type = $this->entityTypeManager->getDefinition($target_entity_type_id);
          if ($target_entity_type->entityClassImplements(ContentEntityInterface::class)) {
            // Map entity_reference IDs to UUIDs.
            $item['target_id'] = $this->mapToUuid($target_entity_type_id, $item['target_id']);
          }
        }
        elseif ($storage_definition->getType() == 'image') {
          // Replace the 'target_id' with the filename.
          /** @var \Drupal\file\FileInterface $file */
          $file = $this->entityTypeManager->getStorage('file')->load($item['target_id']);
          $item['filename'] = $file->getFilename();
          unset($item['target_id']);
          // Remove calculated values.
          unset($item['height']);
          unset($item['width']);
          // Remove empty keys.
          $item = array_filter($item);
        }
        elseif ($storage_definition->getType() == 'path') {
          // Remove calculated values.
          $item = array_intersect_key($item, ['alias' => 'alias']);
        }
        // Simplify items with a single key (such as "value").
        $main_property_name = $storage_definition->getMainPropertyName();
        if ($main_property_name && isset($item[$main_property_name]) && count($item) === 1) {
          $item = $item[$main_property_name];
        }

        $list[$delta] = $item;
      }
      // Remove the wrapping array if the field is single-valued.
      if ($storage_definition->getCardinality() === 1) {
        $list = reset($list);
      }

      if (!empty($list)) {
        $export[$field_name] = $list;
      }
    }

    $entity_type_id = $entity->getEntityTypeId();
    // Perform generic processing.
    if (substr($entity_type_id, 0, 9) == 'commerce_') {
      $export = $this->processCommerce($export, $entity);
    }
    // Process by entity type ID.
    if ($entity_type_id == 'commerce_product') {
      $export = $this->processProduct($export, $entity);
    }
    elseif ($entity_type_id == 'commerce_product_variation') {
      $export = $this->processVariation($export, $entity);
    }
    elseif ($entity_type_id == 'commerce_product_attribute_value') {
      $export = $this->processAttributeValue($export, $entity);
    }
    elseif ($entity_type_id == 'commerce_promotion') {
      $export = $this->processPromotion($export, $entity);
    }
    elseif ($entity_type_id == 'taxonomy_term') {
      $export = $this->processTerm($export, $entity);
    }

    return $export;
  }

  /**
   * Processes the exported Commerce entity.
   *
   * @param array $export
   *   The export array.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The Commerce entity.
   *
   * @return array
   *   The processed export array.
   */
  protected function processCommerce(array $export, ContentEntityInterface $entity) {
    // Imported entities are always assigned to the default store.
    unset($export['stores']);
    return $export;
  }

  /**
   * Processes the exported product.
   *
   * @param array $export
   *   The export array.
   * @param \Drupal\commerce_product\Entity\ProductInterface $product
   *   The product.
   *
   * @return array
   *   The processed export array.
   */
  protected function processProduct(array $export, ProductInterface $product) {
    // Export the variations as well.
    $variations = [];
    foreach ($product->getVariations() as $variation) {
      $variations[$variation->uuid()] = $this->export($variation);
      // The array is keyed by UUID, no need to have it in the export too.
      unset($variations[$variation->uuid()]['uuid']);
    }
    $export['variations'] = $variations;

    return $export;
  }

  /**
   * Processes the exported product variation.
   *
   * @param array $export
   *   The export array.
   * @param \Drupal\commerce_product\Entity\ProductVariationInterface $variation
   *   The product variation.
   *
   * @return array
   *   The processed export array.
   */
  protected function processVariation(array $export, ProductVariationInterface $variation) {
    // Don't export the product_id backreference, it's automatically populated.
    unset($export['product_id']);
    return $export;
  }

  /**
   * Processes the exported attribute value.
   *
   * @param array $export
   *   The export array.
   * @param \Drupal\commerce_product\Entity\ProductAttributeValueInterface $attribute_value
   *   The attribute value.
   *
   * @return array
   *   The processed export array.
   */
  protected function processAttributeValue(array $export, ProductAttributeValueInterface $attribute_value) {
    // Don't export the weight for now.
    unset($export['weight']);
    return $export;
  }

  /**
   * Processes the exported promotion.
   *
   * @param array $export
   *   The export array.
   * @param \Drupal\commerce_promotion\Entity\PromotionInterface $promotion
   *   The promotion.
   *
   * @return array
   *   The processed export array.
   */
  protected function processPromotion(array $export, PromotionInterface $promotion) {
    // Export the coupons as well.
    $coupons = [];
    foreach ($promotion->getCoupons() as $coupon) {
      $coupons[$coupon->uuid()] = $this->export($coupon);
      // The array is keyed by UUID, no need to have it in the export too.
      unset($coupons[$coupon->uuid()]['uuid']);
    }
    $export['coupons'] = $coupons;

    return $export;
  }

  /**
   * Processes the exported taxonomy term.
   *
   * @param array $export
   *   The export array.
   * @param \Drupal\taxonomy\TermInterface $term
   *   The taxonomy term.
   *
   * @return array
   *   The processed export array.
   */
  protected function processTerm(array $export, TermInterface $term) {
    /** @var \Drupal\taxonomy\TermStorageInterface $term_storage */
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    if ($parents = $term_storage->loadParents($term->id())) {
      // The 'parent' doesn't export properly before Drupal 8.6.0. See #2543726.
      $parent_ids = array_keys($parents);
      $parent_ids = array_map(function ($parent_id) {
        return $this->mapToUuid('taxonomy_term', $parent_id);
      }, $parent_ids);
      $export = [
        'parent' => $parent_ids,
      ] + $export;
    }

    return $export;
  }

  /**
   * Maps an entity ID to a UUID.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param int $entity_id
   *   The entity ID.
   *
   * @return string|null
   *   The entity UUID, or NULL if the entity no longer exists.
   */
  protected function mapToUuid($entity_type_id, $entity_id) {
    $storage = $this->entityTypeManager->getStorage($entity_type_id);
    $entity = $storage->load($entity_id);

    return $entity ? $entity->uuid() : NULL;
  }

}
