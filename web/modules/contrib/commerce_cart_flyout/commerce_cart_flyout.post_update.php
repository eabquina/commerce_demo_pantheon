<?php

/**
 * @file
 * Post update functions for Commerce Cart Flyout.
 */

/**
 * Update the add to cart formatter ID for existing product types.
 */
function commerce_cart_flyout_post_update_update_add_to_cart_formatter() {
  $storage = \Drupal::entityTypeManager()->getStorage('entity_view_display');
  /** @var \Drupal\Core\Entity\Display\EntityViewDisplayInterface[] $displays */
  $displays = $storage->loadByProperties(['targetEntityType' => 'commerce_product']);
  foreach ($displays as $display) {
    $variations_component = $display->getComponent('variations');
    if ($variations_component !== NULL && $variations_component['type'] === 'commerce_add_to_cart') {
      $variations_component['type'] = 'commerce_cart_flyout_add_to_cart';
      $display->setComponent('variations', $variations_component);
      $display->save();
    }
  }
}
