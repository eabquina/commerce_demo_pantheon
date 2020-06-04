<?php

namespace Drupal\commerce_demo;

use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\facets\FacetInterface;
use Drupal\pathauto\AliasCleanerInterface;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * Builds a product breadcrumb based on the "field_product_categories" field.
 */
class ProductBreadcrumbBuilder implements BreadcrumbBuilderInterface {

  use StringTranslationTrait;

  /**
   * The alias cleaner.
   *
   * @var \Drupal\pathauto\AliasCleanerInterface
   */
  protected $aliasCleaner;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $facetStorage;

  /**
   * The route provider.
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface
   */
  protected $routeProvider;

  /**
   * Constructs a new ProductBreadcrumbBuilder object.
   *
   * @param \Drupal\pathauto\AliasCleanerInterface $alias_cleaner
   *   The alias cleaner.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Routing\RouteProviderInterface $route_provider
   *   The route provider.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(AliasCleanerInterface $alias_cleaner, EntityTypeManagerInterface $entity_type_manager, RouteProviderInterface $route_provider) {
    $this->aliasCleaner = $alias_cleaner;
    $this->facetStorage = $entity_type_manager->getStorage('facets_facet');
    $this->routeProvider = $route_provider;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    if ($route_match->getRouteName() !== 'entity.commerce_product.canonical') {
      return FALSE;
    }
    try {
      $this->routeProvider->getRouteByName('view.product_catalog.page_1');
    }
    catch (RouteNotFoundException $e) {
      // The catalog View may have been disabled or deleted.
      return FALSE;
    }
    $product = $route_match->getParameter('commerce_product');

    return $product && $product->hasField('field_product_categories');
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match) {
    $breadcrumb = new Breadcrumb();
    $breadcrumb->addCacheContexts(['route']);
    $breadcrumb->addLink(Link::createFromRoute($this->t('Home'), '<front>'));
    $breadcrumb->addLink(Link::createFromRoute($this->t('Catalog'), 'view.product_catalog.page_1'));

    $product = $route_match->getParameter('commerce_product');
    assert($product instanceof ProductInterface);
    $breadcrumb->addCacheableDependency($product);

    $category = $product->get('field_product_categories')->entity;
    if (!$category instanceof TermInterface) {
      return $breadcrumb;
    }
    $breadcrumb->addCacheableDependency($category);

    $facet = $this->facetStorage->load($category->bundle());
    if (!$facet instanceof FacetInterface) {
      return $breadcrumb;
    }
    $label = $this->aliasCleaner->cleanString($category->label());

    $view_url = Url::fromRoute('view.product_catalog.page_1');
    $facet_url = Url::fromUserInput($view_url->toString() . '/' . $facet->getUrlAlias() . '/' . $label . '-' . $category->id());
    $breadcrumb->addLink(Link::fromTextAndUrl($category->label(), $facet_url));
    return $breadcrumb;
  }

}
