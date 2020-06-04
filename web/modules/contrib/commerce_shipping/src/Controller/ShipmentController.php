<?php

namespace Drupal\commerce_shipping\Controller;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Entity\Controller\EntityController;

/**
 * Provides the add-page and title callbacks for shipments.
 */
class ShipmentController extends EntityController {

  /**
   * Redirects to the shipment add form.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $commerce_order
   *   The commerce order to add a shipment to.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response to the shipment add page.
   */
  public function addShipmentPage(OrderInterface $commerce_order) {
    $order_type = $this->entityTypeManager->getStorage('commerce_order_type')->load($commerce_order->bundle());
    // Find the shipment type associated to this order type.
    $shipment_type = $order_type->getThirdPartySetting('commerce_shipping', 'shipment_type', 'default');

    return $this->redirect('entity.commerce_shipment.add_form', [
      'commerce_order' => $commerce_order->id(),
      'commerce_shipment_type' => $shipment_type,
    ]);
  }

}
