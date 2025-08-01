<?php

declare(strict_types=1);

namespace Drupal\Tests\Component\Plugin\Context;

use Drupal\Component\Plugin\Context\Context;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests Drupal\Component\Plugin\Context\Context.
 */
#[CoversClass(Context::class)]
#[Group('Plugin')]
class ContextTest extends TestCase {

  /**
   * Data provider for testGetContextValue.
   */
  public static function providerGetContextValue() {
    return [
      ['context_value', 'context_value', FALSE, 'data_type'],
      [NULL, NULL, FALSE, 'data_type'],
      ['will throw exception', NULL, TRUE, 'data_type'],
    ];
  }

  /**
   * @legacy-covers ::getContextValue
   */
  #[DataProvider('providerGetContextValue')]
  public function testGetContextValue($expected, $context_value, $is_required, $data_type): void {
    // Mock a Context object.
    $mock_context = $this->getMockBuilder('Drupal\Component\Plugin\Context\Context')
      ->disableOriginalConstructor()
      ->onlyMethods(['getContextDefinition'])
      ->getMock();

    // If the context value exists, getContextValue() behaves like a normal
    // getter.
    if ($context_value) {
      // Set visibility of contextValue.
      $ref_context_value = new \ReflectionProperty($mock_context, 'contextValue');
      // Set contextValue to a testable state.
      $ref_context_value->setValue($mock_context, $context_value);
      // Exercise getContextValue().
      $this->assertEquals($context_value, $mock_context->getContextValue());
    }
    // If no context value exists, we have to cover either returning NULL or
    // throwing an exception if the definition requires it.
    else {
      // Create a mock definition.
      $mock_definition = $this->createMock('Drupal\Component\Plugin\Context\ContextDefinitionInterface');

      // Set expectation for isRequired().
      $mock_definition->expects($this->once())
        ->method('isRequired')
        ->willReturn($is_required);

      // Set expectation for getDataType().
      $mock_definition->expects($this->exactly(
            $is_required ? 1 : 0
        ))
        ->method('getDataType')
        ->willReturn($data_type);

      // Set expectation for getContextDefinition().
      $mock_context->expects($this->once())
        ->method('getContextDefinition')
        ->willReturn($mock_definition);

      // Set expectation for exception.
      if ($is_required) {
        $this->expectException('Drupal\Component\Plugin\Exception\ContextException');
        $this->expectExceptionMessage(sprintf("The %s context is required and not present.", $data_type));
      }

      // Exercise getContextValue().
      $this->assertEquals($context_value, $mock_context->getContextValue());
    }
  }

  /**
   * Data provider for testHasContextValue.
   */
  public static function providerHasContextValue() {
    return [
      [TRUE, FALSE],
      [TRUE, 0],
      [TRUE, -0],
      [TRUE, 0.0],
      [TRUE, -0.0],
      [TRUE, ''],
      [TRUE, '0'],
      [TRUE, []],
      [FALSE, NULL],
    ];
  }

  /**
   * @legacy-covers ::hasContextValue
   */
  #[DataProvider('providerHasContextValue')]
  public function testHasContextValue($has_context_value, $default_value): void {
    $mock_definition = $this->createMock('Drupal\Component\Plugin\Context\ContextDefinitionInterface');

    $mock_definition->expects($this->atLeastOnce())
      ->method('getDefaultValue')
      ->willReturn($default_value);

    $context = new Context($mock_definition);

    $this->assertSame($has_context_value, $context->hasContextValue());
    $this->assertSame($default_value, $context->getContextValue());
  }

  /**
   * @legacy-covers ::getContextValue
   */
  public function testDefaultValue(): void {
    $mock_definition = $this->createMock('Drupal\Component\Plugin\Context\ContextDefinitionInterface');

    $mock_definition->expects($this->once())
      ->method('getDefaultValue')
      ->willReturn('test');

    $context = new Context($mock_definition);
    $this->assertEquals('test', $context->getContextValue());
  }

}
