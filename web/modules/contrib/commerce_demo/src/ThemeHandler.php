<?php

namespace Drupal\commerce_demo;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\Container;

class ThemeHandler {

  /**
   * The block storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $blockStorage;

  /**
   * Constructs a new ThemeHandler object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->blockStorage = $entity_type_manager->getStorage('block');
  }

  /**
   * {@inheritdoc}
   */
  public function placeBlocks($theme) {
    $method = 'dataFor' . Container::camelize($theme) . 'Blocks';
    if (!method_exists($this, $method)) {
      return;
    }

    $blocks = $this->blockDefinitions();
    $this->{$method}($blocks);

    foreach ($blocks as $block_id => $block_data) {
      /** @var \Drupal\block\Entity\Block $existing_block */
      $block_id = "{$theme}_{$block_id}";
      $existing_block = $this->blockStorage->load($block_id);
      if (!$existing_block) {
        $block_data['id'] = $block_id;
        $block_data['theme'] = $theme;
        /** @var \Drupal\block\Entity\Block $block */
        $block = $this->blockStorage->create($block_data);
        $block->setStatus(TRUE);
        $block->save();
      }
      // When changing themes, the `block_themes_installed` hook runs first,
      // and invokes block_theme_initialize. This caused blocks from the
      // previous theme to carry over. We ensure, here, their placement in the
      // event the block was moved to the "default" region.
      else {
        $existing_block->setRegion($block_data['region']);
        $existing_block->setWeight($block_data['weight']);
        foreach ($block_data['visibility'] as $instance_id => $instance_config) {
          $existing_block->setVisibilityConfig($instance_id, $instance_config);
        }
        $existing_block->setStatus(TRUE);
        $existing_block->save();
      }
    }

  }

  /**
   * Provides block definitions for the demo.
   *
   * @return array
   *   The block definitions.
   */
  protected function blockDefinitions() {
    $catalog_visibility = [
      'request_path' => [
        'id' => 'request_path',
        'pages' => "/products\r\n/products/*",
        'negate' => FALSE,
        'context_mapping' => [],
      ],
    ];
    return [
      'facets_brand' => [
        'plugin' => 'facet_block:brand',
        'settings' => [
          'id' => 'facet_block:brand',
          'label' => 'Brand',
          'provider' => 'facets',
          'label_display' => 'visible',
        ],
        'visibility' => $catalog_visibility,
      ],
      'facets_categories' => [
        'plugin' => 'facet_block:product_categories',
        'settings' => [
          'id' => 'facet_block:product_categories',
          'label' => 'Categories',
          'provider' => 'facets',
          'label_display' => 'visible',
        ],
        'visibility' => $catalog_visibility,
      ],
      'facets_specialcategories' => [
        'plugin' => 'facet_block:special_categories',
        'settings' => [
          'id' => 'facet_block:special_categories',
          'label' => 'Special categories',
          'provider' => 'facets',
          'label_display' => 'visible',
        ],
        'visibility' => $catalog_visibility,
      ],
      'shopping_cart' => [
        'plugin' => 'commerce_cart',
        'settings' => [
          'id' => 'commerce_cart',
          'label' => 'Shopping Cart',
          'provider' => 'commerce_cart',
          'label_display' => '0',
          'dropdown' => 'true',
          'item_text' => 'items',
        ],
        'visibility' => [],
      ],
      'catalog' => [
        'plugin' => 'system_menu_block:catalog',
        'settings' => [
          'id' => 'catalog_footer',
          'label' => 'Catalog',
          'provider' => 'system',
          'label_display' => '0',
          'level' => 1,
          'depth' => 0,
        ],
        'visibility' => [],
      ],
      'catalog_menu' => [
        'plugin' => 'system_menu_block:catalog',
        'settings' => [
          'id' => 'catalog_menu',
          'label' => 'Catalog menu',
          'provider' => 'system',
          'label_display' => '0',
          'level' => 1,
          'depth' => 0,
        ],
        'visibility' => [],
      ],
      'catalog_menu_navigation' => [
        'plugin' => 'system_menu_block:catalog',
        'settings' => [
          'id' => 'catalog_menu_navigation',
          'label' => 'Catalog menu navigation',
          'provider' => 'system',
          'label_display' => '0',
          'level' => 1,
          'depth' => 0,
        ],
        'visibility' => [],
      ],
      'breadcrumbs' => [
        'plugin' => 'system_breadcrumb_block',
        'settings' => [
          'id' => 'system_breadcrumb_block',
          'label' => 'Breadcrumbs',
          'provider' => 'system',
          'label_display' => '0',
        ],
        'visibility' => [
          'request_path' => [
            'id' => 'request_path',
            'pages' => "/checkout\r\n/checkout/*",
            'negate' => TRUE,
            'context_mapping' => [],
          ],
        ],
      ],
    ];
  }

  /**
   * Data for the Bartik theme's blocks.
   *
   * @param array $data
   *   The block definitions to alter.
   */
  protected function dataForBartikBlocks(array &$data) {
    $data['facets_brand']['region'] = 'sidebar_first';
    $data['facets_brand']['weight'] = 0;
    $data['facets_categories']['region'] = 'sidebar_first';
    $data['facets_categories']['weight'] = 1;
    $data['facets_specialcategories']['region'] = 'sidebar_first';
    $data['facets_specialcategories']['weight'] = 2;

    $data['shopping_cart']['region'] = 'header';
    $data['shopping_cart']['weight'] = NULL;

    $data['catalog']['region'] = 'footer_first';
    $data['catalog']['weight'] = -6;
    $data['catalog_menu']['region'] = 'sidebar_first';
    $data['catalog_menu']['weight'] = -8;
    $data['catalog_menu']['settings']['label_display'] = 1;
    $data['catalog_menu']['settings']['label'] = t('Catalog');
    unset($data['catalog_menu_navigation']);

    $data['breadcrumbs']['region'] = 'breadcrumb';
    $data['breadcrumbs']['weight'] = 0;
  }

  /**
   * Data for the Belgrade theme's blocks.
   *
   * @param array $data
   *   The block definitions to alter.
   */
  protected function dataForBelgradeBlocks(array &$data) {
    $data['facets_brand']['region'] = 'sidebar_first';
    $data['facets_brand']['weight'] = 0;
    $data['facets_categories']['region'] = 'sidebar_first';
    $data['facets_categories']['weight'] = 1;
    $data['facets_specialcategories']['region'] = 'sidebar_first';
    $data['facets_specialcategories']['weight'] = 2;

    $data['shopping_cart']['region'] = 'top_navigation';
    $data['shopping_cart']['weight'] = -5;

    $data['catalog']['region'] = 'footer';
    $data['catalog']['weight'] = -6;
    $data['catalog_menu']['region'] = 'header';
    $data['catalog_menu']['weight'] = -8;
    $data['catalog_menu_navigation']['region'] = 'navigation';
    $data['catalog_menu_navigation']['weight'] = 0;

    $data['breadcrumbs']['region'] = 'highlighted';
    $data['breadcrumbs']['weight'] = -7;
  }

}
