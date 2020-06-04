<?php

namespace Drupal\commerce_demo;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Controller\TitleResolverInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Builds a catalog breadcrumb.
 */
class CatalogBreadcrumbBuilder implements BreadcrumbBuilderInterface {

  use StringTranslationTrait;

  /**
   * The title resolver.
   *
   * @var \Drupal\Core\Controller\TitleResolverInterface
   */
  protected $titleResolver;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs a new CatalogBreadcrumbBuilder object.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Controller\TitleResolverInterface $title_resolver
   *   The title resolver.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity storage.
   */
  public function __construct(RequestStack $request_stack, TitleResolverInterface $title_resolver, EntityTypeManagerInterface $entity_type_manager) {
    $this->requestStack = $request_stack;
    $this->titleResolver = $title_resolver;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    return $route_match->getRouteName() == 'view.product_catalog.page_1' && $route_match->getParameter('facets_query');
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match) {
    $breadcrumb = new Breadcrumb();
    $breadcrumb->addCacheContexts(['route']);

    $breadcrumb->addLink(Link::createFromRoute($this->t('Home'), '<front>'));

    $title = $this->titleResolver->getTitle($this->requestStack->getCurrentRequest(), $route_match->getRouteObject());
    $breadcrumb->addLink(Link::fromTextAndUrl($title, Url::fromRoute($route_match->getRouteName())));

    $facets_query = $route_match->getParameter('facets_query');
    $facets_query_parts = explode('/', $facets_query);
    if (count($facets_query_parts) % 2 !== 0) {
      array_shift($facets_query_parts);
    }

    foreach ($facets_query_parts as $k => $facets_query_part) {
      if ($facets_query_part == 'category') {
        $facet_query_alias = $facets_query_part;
        $facet_value = $facets_query_parts[$k + 1];

        $alias_value_parts = explode('-', $facet_value);
        $term_id = array_pop($alias_value_parts);
        $term = $this->entityTypeManager->getStorage('taxonomy_term')->load($term_id);
        assert($term instanceof TermInterface);
        $breadcrumb->addLink(Link::fromTextAndUrl($term->getName(), Url::fromRoute($route_match->getRouteName(), [
          'facets_query' => "$facet_query_alias/$facet_value",
        ])));
        $breadcrumb->addCacheableDependency($term);
        break;
      }
    }

    return $breadcrumb;
  }

}
