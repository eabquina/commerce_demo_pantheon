<?php

namespace Drupal\commerce_demo\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneInterface;
use Drupal\commerce_promotion\Entity\CouponInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the Order summary pane.
 *
 * @CommerceCheckoutPane(
 *   id = "demo_coupon_callout",
 *   label = @Translation("Demo Coupon Callout"),
 *   default_step = "_sidebar",
 *   wrapper_element = "container",
 * )
 */
class DemoCouponCallout extends CheckoutPaneBase implements CheckoutPaneInterface {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'weight' => 50,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    $build = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['well'],
      ],
    ];
    $build['heading'] = [
      '#type' => 'inline_template',
      '#template' => '<h4 class="text-muted"><strong>{{ title }}</strong></h4>',
      '#context' => [
        'title' => $this->t('Try out a coupon in our demo!'),
      ],
    ];
    $build['coupon_codes'] = [
      '#theme' => 'item_list',
      '#items' => [],
      '#attributes' => [
        'class' => ['text-muted'],
      ],
    ];

    /** @var \Drupal\commerce_promotion\CouponStorageInterface $coupon_storage */
    $coupon_storage = $this->entityTypeManager->getStorage('commerce_promotion_coupon');
    $coupons = array_filter($coupon_storage->loadMultiple(), function (CouponInterface $coupon) {
      return $coupon->available($this->order) && $coupon->getPromotion()->applies($this->order);
    });
    $build['coupon_codes']['#items'] = array_map(function (CouponInterface $coupon) {
      return $coupon->getCode();
    }, $coupons);

    return $build;
  }

}
