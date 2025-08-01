<?php

declare(strict_types=1);

namespace Drupal\Tests\Component\Utility;

use Drupal\Component\Utility\Variable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Test variable export functionality in Variable component.
 */
#[CoversClass(Variable::class)]
#[Group('Variable')]
#[Group('Utility')]
class VariableTest extends TestCase {

  /**
   * Data provider for testCallableToString().
   *
   * @return array[]
   *   Sets of arguments to pass to the test method.
   */
  public static function providerCallableToString(): array {
    $mock = VariableTestMock::class;
    return [
      'string' => [
        "$mock::fake",
        "$mock::fake",
      ],
      'static method as array' => [
        [$mock, 'fake'],
        "$mock::fake",
      ],
      'closure' => [
        function () {
          return NULL;
        },
        '[closure]',
      ],
      'object method' => [
        [new VariableTestMock(), 'fake'],
        "$mock::fake",
      ],
      'service method' => [
        'fake_service:method',
        'fake_service:method',
      ],
      'single-item array' => [
        ['some_function'],
        'some_function',
      ],
      'empty array' => [
        [],
        '[unknown]',
      ],
      'object' => [
        new \stdClass(),
        '[unknown]',
      ],
      'definitely not callable' => [
        TRUE,
        '[unknown]',
      ],
    ];
  }

  /**
   * Tests generating a human-readable name for a callable.
   *
   * @param callable $callable
   *   A callable.
   * @param string $expected_name
   *   The expected human-readable name of the callable.
   *
   * @legacy-covers ::callableToString
   */
  #[DataProvider('providerCallableToString')]
  public function testCallableToString($callable, string $expected_name): void {
    $this->assertSame($expected_name, Variable::callableToString($callable));
  }

  /**
   * Data provider for testExport().
   *
   * @return array
   *   An array containing:
   *     - The expected export string.
   *     - The variable to export.
   */
  public static function providerTestExport() {
    return [
      // Array.
      [
        '[]',
        [],
      ],
      [
        // non-associative.
        "[\n  1,\n  2,\n  3,\n  4,\n]",
        [1, 2, 3, 4],
      ],
      [
        // associative.
        "[\n  'a' => 1,\n]",
        ['a' => 1],
      ],
      // Bool.
      [
        'TRUE',
        TRUE,
      ],
      [
        'FALSE',
        FALSE,
      ],
      // Strings.
      [
        "'string'",
        'string',
      ],
      [
        '"\n\r\t"',
        "\n\r\t",
      ],
      [
        // 2 backslashes. \\
        "'\\'",
        '\\',
      ],
      [
        // Double-quote ".
        "'\"'",
        "\"",
      ],
      [
        // Single-quote '.
        '"\'"',
        "'",
      ],
      [
        // Quotes with $ symbols.
        '"\$settings[\'foo\']"',
        '$settings[\'foo\']',
      ],
      // Object.
      [
        // A stdClass object.
        '(object) []',
        new \stdClass(),
      ],
      [
        // A not-stdClass object. Since PHP 8.2 exported namespace is prefixed,
        // see https://github.com/php/php-src/pull/8233 for reasons.
        PHP_VERSION_ID >= 80200 ?
        "\Drupal\Tests\Component\Utility\StubVariableTestClass::__set_state(array(\n))" :
        "Drupal\Tests\Component\Utility\StubVariableTestClass::__set_state(array(\n))",
        new StubVariableTestClass(),
      ],
    ];
  }

  /**
   * Tests exporting variables.
   *
   * @param string $expected
   *   The expected exported variable.
   * @param mixed $variable
   *   The variable to be exported.
   *
   * @legacy-covers ::export
   */
  #[DataProvider('providerTestExport')]
  public function testExport($expected, $variable): void {
    $this->assertEquals($expected, Variable::export($variable));
  }

}

/**
 * A class for testing Variable::callableToString().
 */
class VariableTestMock {

  /**
   * A bogus callable for testing ::callableToString().
   */
  public static function fake(): void {
  }

}

/**
 * No-op test class for VariableTest::testExport().
 *
 * @see \Drupal\Tests\Component\Utility\VariableTest::testExport()
 * @see \Drupal\Tests\Component\Utility\VariableTest::providerTestExport()
 */
class StubVariableTestClass {

}
