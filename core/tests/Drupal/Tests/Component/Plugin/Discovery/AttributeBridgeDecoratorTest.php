<?php

declare(strict_types=1);

namespace Drupal\Tests\Component\Plugin\Discovery;

use Drupal\Component\Annotation\Plugin\Discovery\AnnotationBridgeDecorator;
use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Component\Plugin\Definition\PluginDefinition;
use Drupal\Component\Plugin\Discovery\AttributeBridgeDecorator;
use Drupal\Component\Plugin\Discovery\DiscoveryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests Drupal\Component\Annotation\Plugin\Discovery\AnnotationBridgeDecorator.
 */
#[CoversClass(AnnotationBridgeDecorator::class)]
#[Group('Plugin')]
class AttributeBridgeDecoratorTest extends TestCase {

  /**
   * @legacy-covers ::getDefinitions
   */
  public function testGetDefinitions(): void {
    // Normally the attribute classes would be autoloaded.
    include_once __DIR__ . '/../../../../../fixtures/plugins/CustomPlugin.php';
    include_once __DIR__ . '/../../../../../fixtures/plugins/Plugin/PluginNamespace/AttributeDiscoveryTest1.php';

    $definitions = [];
    $definitions['object'] = new ObjectDefinition(['id' => 'foo']);
    $definitions['array'] = [
      'id' => 'bar',
      'class' => 'com\example\PluginNamespace\AttributeDiscoveryTest1',
    ];
    $discovery = $this->createMock(DiscoveryInterface::class);
    $discovery->expects($this->any())
      ->method('getDefinitions')
      ->willReturn($definitions);

    $decorator = new AttributeBridgeDecorator($discovery, TestAttribute::class);

    $expected = [
      'object' => new ObjectDefinition(['id' => 'foo']),
      'array' => (new ObjectDefinition(['id' => 'bar']))->setClass('com\example\PluginNamespace\AttributeDiscoveryTest1'),
    ];
    $this->assertEquals($expected, $decorator->getDefinitions());
  }

  /**
   * Tests that the decorator of other methods works.
   *
   * @legacy-covers ::__call
   */
  public function testOtherMethod(): void {
    // Normally the attribute classes would be autoloaded.
    include_once __DIR__ . '/../../../../../fixtures/plugins/CustomPlugin.php';
    include_once __DIR__ . '/../../../../../fixtures/plugins/Plugin/PluginNamespace/AttributeDiscoveryTest1.php';

    $discovery = $this->createMock(ExtendedDiscoveryInterface::class);
    $discovery->expects($this->exactly(2))
      ->method('otherMethod')
      ->willReturnCallback(fn($id) => $id === 'foo');

    $decorator = new AttributeBridgeDecorator($discovery, TestAttribute::class);

    $this->assertTrue($decorator->otherMethod('foo'));
    $this->assertFalse($decorator->otherMethod('bar'));
  }

}

/**
 * An interface for testing the Discovery interface.
 */
interface ExtendedDiscoveryInterface extends DiscoveryInterface {

  public function otherMethod(string $id): bool;

}

/**
 * {@inheritdoc}
 */
class TestAttribute extends Plugin {

  /**
   * {@inheritdoc}
   */
  public function get(): object {
    return new ObjectDefinition(parent::get());
  }

}

/**
 * {@inheritdoc}
 */
class ObjectDefinition extends PluginDefinition {

  /**
   * ObjectDefinition constructor.
   *
   * @param array $definition
   *   An array of definition values.
   */
  public function __construct(array $definition) {
    foreach ($definition as $property => $value) {
      $this->{$property} = $value;
    }
  }

}
