<?php

declare(strict_types=1);

namespace Drupal\FunctionalTests\Routing;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Lazy Route Provider Install.
 */
#[Group('routing')]
class LazyRouteProviderInstallTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['lazy_route_provider_install_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests that the lazy route provider is used during a module install.
   */
  public function testInstallation(): void {
    $this->container->get('module_installer')->install(['router_test']);
    // Note that on DrupalCI the test site is installed in a sub directory so
    // we cannot use ::assertEquals().
    $this->assertStringEndsWith('/admin', \Drupal::state()->get('Drupal\lazy_route_provider_install_test\PluginManager'));
    $this->assertStringEndsWith('/router_test/test1', \Drupal::state()->get('router_test_install'));
    // If there is an exception thrown in rebuilding a route then the state
    // 'lazy_route_provider_install_test_menu_links_discovered_alter' will be
    // set.
    // @see lazy_route_provider_install_test_menu_links_discovered_alter().
    $this->assertEquals('success', \Drupal::state()->get('lazy_route_provider_install_test_menu_links_discovered_alter', NULL));
  }

}
