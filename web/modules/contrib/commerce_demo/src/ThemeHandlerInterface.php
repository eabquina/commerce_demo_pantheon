<?php

namespace Drupal\commerce_demo;

/**
 * Handles demo components required in specific themes.
 */
interface ThemeHandlerInterface {

  /**
   * Places blocks for the theme.
   *
   * @param string $theme
   *   The theme name.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function placeBlocks($theme);

}
