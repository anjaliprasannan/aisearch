<?php

declare(strict_types=1);

namespace Drupal\Tests\Component\Annotation;

use Drupal\Component\Annotation\PluginID;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests Drupal\Component\Annotation\PluginID.
 */
#[CoversClass(PluginID::class)]
#[Group('Annotation')]
class PluginIdTest extends TestCase {

  /**
   * @legacy-covers ::get
   */
  public function testGet(): void {
    // Assert plugin starts empty.
    $plugin = new PluginID();
    $this->assertEquals([
      'id' => NULL,
      'class' => NULL,
      'provider' => NULL,
    ], $plugin->get());

    // Set values and ensure we can retrieve them.
    $plugin->value = 'foo';
    $plugin->setClass('bar');
    $plugin->setProvider('baz');
    $this->assertEquals([
      'id' => 'foo',
      'class' => 'bar',
      'provider' => 'baz',
    ], $plugin->get());
  }

  /**
   * @legacy-covers ::getId
   */
  public function testGetId(): void {
    $plugin = new PluginID();
    $plugin->value = 'example';
    $this->assertEquals('example', $plugin->getId());
  }

}
