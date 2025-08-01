<?php

declare(strict_types=1);

namespace Drupal\Tests\Component\Plugin\Discovery;

use Drupal\Component\Plugin\Discovery\DiscoveryCachedTrait;
use Drupal\Component\Plugin\Discovery\DiscoveryTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests Drupal\Component\Plugin\Discovery\DiscoveryCachedTrait.
 */
#[CoversClass(DiscoveryCachedTrait::class)]
#[Group('Plugin')]
#[UsesClass(DiscoveryTrait::class)]
class DiscoveryCachedTraitTest extends TestCase {

  /**
   * Data provider for testGetDefinition().
   *
   * @return array
   *   - Expected result from getDefinition().
   *   - Cached definitions to be placed into self::$definitions
   *   - Definitions to be returned by getDefinitions().
   *   - Plugin name to query for.
   */
  public static function providerGetDefinition() {
    return [
      ['definition', [], ['plugin_name' => 'definition'], 'plugin_name'],
      ['definition', ['plugin_name' => 'definition'], [], 'plugin_name'],
      [NULL, ['plugin_name' => 'definition'], [], 'bad_plugin_name'],
    ];
  }

  /**
   * @legacy-covers ::getDefinition
   */
  #[DataProvider('providerGetDefinition')]
  public function testGetDefinition($expected, $cached_definitions, $get_definitions, $plugin_id): void {
    $trait = $this->getMockBuilder(DiscoveryCachedTraitMockableClass::class)
      ->onlyMethods(['getDefinitions'])
      ->getMock();
    $reflection_definitions = new \ReflectionProperty($trait, 'definitions');
    // getDefinition() needs the ::$definitions property to be set in one of two
    // ways: 1) As existing cached data, or 2) as a side-effect of calling
    // getDefinitions().
    // If there are no cached definitions, then we have to fake the side-effect
    // of getDefinitions().
    if (count($cached_definitions) < 1) {
      $trait->expects($this->once())
        ->method('getDefinitions')
        // Use a callback method, so we can perform the side-effects.
        ->willReturnCallback(function () use ($reflection_definitions, $trait, $get_definitions) {
          $reflection_definitions->setValue($trait, $get_definitions);
          return $get_definitions;
        });
    }
    else {
      // Put $cached_definitions into our mocked ::$definitions.
      $reflection_definitions->setValue($trait, $cached_definitions);
    }
    // Call getDefinition(), with $exception_on_invalid always FALSE.
    $this->assertSame(
      $expected,
      $trait->getDefinition($plugin_id, FALSE)
    );
  }

}

/**
 * A class using the DiscoveryCachedTrait for mocking purposes.
 */
class DiscoveryCachedTraitMockableClass {

  use DiscoveryCachedTrait;

  public function getDefinitions(): array {
    return [];
  }

}
