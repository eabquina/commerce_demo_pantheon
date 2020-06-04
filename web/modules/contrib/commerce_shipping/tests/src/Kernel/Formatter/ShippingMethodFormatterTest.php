<?php

namespace Drupal\Tests\commerce_shipping\Kernel\Formatter;

use Drupal\commerce_shipping\Entity\Shipment;
use Drupal\commerce_shipping\Entity\ShippingMethod;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\commerce_shipping\Kernel\ShippingKernelTestBase;

/**
 * Tests the shipping method formatter.
 *
 * @coversDefaultClass \Drupal\commerce_shipping\Plugin\Field\FieldFormatter\ShippingMethodFormatter
 *
 * @group commerce_shipping
 */
class ShippingMethodFormatterTest extends ShippingKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'language',
    'content_translation',
  ];

  /**
   * The language used for testing.
   *
   * @var \Drupal\Core\Language\LanguageInterface
   */
  protected $translationLanguage;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->translationLanguage = ConfigurableLanguage::createFromLangcode('fr');
    $this->translationLanguage->save();
    $this->container->get('content_translation.manager')->setEnabled('commerce_shipping_method', 'commerce_shipping_method', TRUE);
  }

  /**
   * Tests the rendered output.
   *
   * @covers ::viewElements
   */
  public function testRender() {
    /** @var \Drupal\commerce_shipping\Entity\ShippingMethodInterface $shipping_method */
    $shipping_method = ShippingMethod::create([
      'name' => $this->randomString(),
      'status' => 1,
      'plugin' => [
        'target_plugin_id' => 'flat_rate',
        'target_plugin_configuration' => [
          'rate_label' => 'Test shipping',
          'rate_amount' => [
            'amount' => '10',
            'currency_code' => 'USD',
          ],
          'services' => [
            'default',
          ],
        ],
      ],
    ]);
    $shipping_method->addTranslation($this->translationLanguage->id(), [
      'name' => 'Shipping method',
      'plugin' => [
        'target_plugin_id' => 'flat_rate',
        'target_plugin_configuration' => [
          'rate_label' => 'Test translation shipping',
          'rate_amount' => [
            'number' => '10',
            'currency_code' => 'USD',
          ],
        ],
      ],
    ]);
    $shipping_method->save();

    $shipment = Shipment::create([
      'type' => 'default',
      // The order ID is invalid, but this test doesn't need to care about that.
      'order_id' => '6',
      'shipping_method' => $shipping_method->id(),
      'shipping_service' => 'default',
      'title' => 'Shipment #1',
      'state' => 'ready',
    ]);
    $shipment->save();

    $default_language = $this->container->get('language_manager')->getDefaultLanguage();
    $this->container->get('language.default')->set($this->translationLanguage);

    // Confirm that the translated rate_label is used.
    $view_display = EntityViewDisplay::collectRenderDisplay($shipment, 'default');
    $build = $view_display->build($shipment);
    $this->render($build);
    $this->assertText('Shipping method');
    $this->assertText('Test translation shipping');

    // Revert the site default language to the original language.
    $this->container->get('language.default')->set($default_language);
    $this->container->get('language_manager')->reset();

    $view_display = EntityViewDisplay::collectRenderDisplay($shipment, 'default');
    $build = $view_display->build($shipment);
    $this->render($build);
    $this->assertText('Shipping method');
    $this->assertText('Test shipping');

    // Confirm that deleting the shipping method doesn't crash the formatter.
    $shipping_method->delete();
    $shipment = $this->reloadEntity($shipment);
    $build = $view_display->build($shipment);
    $this->render($build);
    $this->assertNoText('Shipping method', $this->content);
    $this->assertNoText('Test shipping');
    $this->assertNoText('Test translation shipping');
  }

}
