<?php

declare(strict_types=1);

namespace Drupal\Tests\Component\Annotation;

use Drupal\Component\Annotation\Plugin;
use Drupal\Component\Annotation\Plugin\Discovery\AnnotatedClassDiscovery;
use Drupal\Component\FileCache\FileCacheFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * Tests Drupal\Component\Annotation\Plugin\Discovery\AnnotatedClassDiscovery.
 */
#[CoversClass(AnnotatedClassDiscovery::class)]
#[Group('Annotation')]
#[RunTestsInSeparateProcesses]
class AnnotatedClassDiscoveryTest extends TestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Ensure the file cache is disabled.
    FileCacheFactory::setConfiguration([FileCacheFactory::DISABLE_CACHE => TRUE]);
    // Ensure that FileCacheFactory has a prefix.
    FileCacheFactory::setPrefix('prefix');
  }

  /**
   * @legacy-covers ::__construct
   * @legacy-covers ::getPluginNamespaces
   */
  public function testGetPluginNamespaces(): void {
    $discovery = new AnnotatedClassDiscovery(['com/example' => [__DIR__]]);

    $reflection = new \ReflectionMethod($discovery, 'getPluginNamespaces');
    $result = $reflection->invoke($discovery);
    $this->assertEquals(['com/example' => [__DIR__]], $result);
  }

  /**
   * @legacy-covers ::getDefinitions
   * @legacy-covers ::prepareAnnotationDefinition
   * @legacy-covers ::getAnnotationReader
   */
  public function testGetDefinitions(): void {
    $discovery = new AnnotatedClassDiscovery(['com\example' => [__DIR__ . '/Fixtures']]);
    $this->assertEquals([
      'discovery_test_1' => [
        'id' => 'discovery_test_1',
        'class' => 'com\example\PluginNamespace\DiscoveryTest1',
      ],
    ], $discovery->getDefinitions());

    $custom_annotation_discovery = new AnnotatedClassDiscovery(['com\example' => [__DIR__ . '/Fixtures']], CustomPlugin::class, ['Drupal\Tests\Component\Annotation']);
    $this->assertEquals([
      'discovery_test_1' => [
        'id' => 'discovery_test_1',
        'class' => 'com\example\PluginNamespace\DiscoveryTest1',
        'title' => 'Discovery test plugin',
      ],
    ], $custom_annotation_discovery->getDefinitions());

    $empty_discovery = new AnnotatedClassDiscovery(['com\example' => [__DIR__ . '/Fixtures']], CustomPlugin2::class, ['Drupal\Tests\Component\Annotation']);
    $this->assertEquals([], $empty_discovery->getDefinitions());
  }

}

/**
 * Custom plugin annotation.
 *
 * @Annotation
 */
class CustomPlugin extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The plugin title.
   *
   * @var string
   *
   * @ingroup plugin_translatable
   */
  public $title = '';

}

/**
 * Custom plugin annotation.
 *
 * @Annotation
 */
class CustomPlugin2 extends Plugin {}
