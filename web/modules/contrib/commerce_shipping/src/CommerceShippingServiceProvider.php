<?php

namespace Drupal\commerce_shipping;

use Drupal\commerce_shipping\EventSubscriber\CartSubscriber;
use Drupal\commerce_shipping\EventSubscriber\PromotionSubscriber;
use Drupal\commerce_shipping\EventSubscriber\TaxSubscriber;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Registers event subscribers for non-required modules.
 */
class CommerceShippingServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    // We cannot use the module handler as the container is not yet compiled.
    // @see \Drupal\Core\DrupalKernel::compileContainer()
    $modules = $container->getParameter('container.modules');

    if (isset($modules['commerce_promotion'])) {
      $container->register('commerce_shipping.promotion_subscriber', PromotionSubscriber::class)
        ->addArgument(new Reference('entity_type.manager'))
        ->addArgument(new Reference('plugin.manager.commerce_promotion_offer'))
        ->addTag('event_subscriber');
    }
    if (isset($modules['commerce_cart'])) {
      $container->register('commerce_shipping.cart_subscriber', CartSubscriber::class)
        ->addArgument(new Reference('commerce_shipping.order_manager'))
        ->addTag('event_subscriber');
    }
    if (isset($modules['commerce_tax'])) {
      $container->register('commerce_shipping.tax_subscriber', TaxSubscriber::class)
        ->addArgument(new Reference('commerce_shipping.order_manager'))
        ->addTag('event_subscriber');
    }
  }

}
