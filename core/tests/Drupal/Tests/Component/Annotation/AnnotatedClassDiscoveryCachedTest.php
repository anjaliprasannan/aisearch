<?php

declare(strict_types=1);

namespace Drupal\Tests\Component\Annotation;

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
class AnnotatedClassDiscoveryCachedTest extends TestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Ensure FileCacheFactory::DISABLE_CACHE is *not* set, since we're testing
    // integration with the file cache.
    FileCacheFactory::setConfiguration([]);
    // Ensure that FileCacheFactory has a prefix.
    FileCacheFactory::setPrefix('prefix');
  }

  /**
   * Tests that getDefinitions() retrieves the file cache correctly.
   *
   * @legacy-covers ::getDefinitions
   */
  public function testGetDefinitions(): void {
    // Path to the classes which we'll discover and parse annotation.
    $discovery_path = __DIR__ . '/Fixtures';
    // File path that should be discovered within that directory.
    $file_path = $discovery_path . '/PluginNamespace/DiscoveryTest1.php';

    $discovery = new AnnotatedClassDiscovery(['com\example' => [$discovery_path]]);
    $this->assertEquals([
      'discovery_test_1' => [
        'id' => 'discovery_test_1',
        'class' => 'com\example\PluginNamespace\DiscoveryTest1',
      ],
    ], $discovery->getDefinitions());

    // Gain access to the file cache so we can change it.
    $ref_file_cache = new \ReflectionProperty($discovery, 'fileCache');
    /** @var \Drupal\Component\FileCache\FileCacheInterface $file_cache */
    $file_cache = $ref_file_cache->getValue($discovery);
    // The file cache is keyed by the file path, and we'll add some known
    // content to test against.
    $file_cache->set($file_path, [
      'id' => 'wrong_id',
      'content' => serialize(['an' => 'array']),
    ]);

    // Now perform the same query and check for the cached results.
    $this->assertEquals([
      'wrong_id' => [
        'an' => 'array',
      ],
    ], $discovery->getDefinitions());
  }

}
