<?php

declare(strict_types=1);

namespace Drupal\FunctionalTests\Core\Container;

use Drupal\Component\DependencyInjection\Container;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;

/**
 * Test whether deprecation notices are triggered via \Drupal::service().
 *
 * Note: this test must be a BrowserTestBase so the container is properly
 * compiled. The container in KernelTestBase tests is always an instance of
 * \Drupal\Core\DependencyInjection\ContainerBuilder.
 */
#[CoversClass(Container::class)]
#[Group('Container')]
#[IgnoreDeprecations]
class ServiceDeprecationTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['deprecation_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests get deprecated.
   *
   * @legacy-covers ::get
   */
  public function testGetDeprecated(): void {
    $this->expectDeprecation('The "deprecation_test.service" service is deprecated in drupal:9.0.0 and is removed from drupal:20.0.0. This is a test.');
    $this->expectDeprecation('The "deprecation_test.alias" alias is deprecated in drupal:9.0.0 and is removed from drupal:20.0.0. This is a test.');
    // @phpstan-ignore-next-line
    \Drupal::service('deprecation_test.service');
    // @phpstan-ignore-next-line
    \Drupal::service('deprecation_test.alias');
  }

}
