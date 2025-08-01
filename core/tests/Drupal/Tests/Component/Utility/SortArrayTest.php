<?php

declare(strict_types=1);

namespace Drupal\Tests\Component\Utility;

use Drupal\Component\Utility\SortArray;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the SortArray component.
 */
#[CoversClass(SortArray::class)]
#[Group('Utility')]
class SortArrayTest extends TestCase {

  /**
   * Tests SortArray::sortByWeightElement() input against expected output.
   *
   * @param array $a
   *   The first input array for the SortArray::sortByWeightElement() method.
   * @param array $b
   *   The second input array for the SortArray::sortByWeightElement().
   * @param int $expected
   *   The expected output from calling the method.
   *
   * @legacy-covers ::sortByWeightElement
   * @legacy-covers ::sortByKeyInt
   */
  #[DataProvider('providerSortByWeightElement')]
  public function testSortByWeightElement($a, $b, $expected): void {
    $result = SortArray::sortByWeightElement($a, $b);
    $this->assertBothNegativePositiveOrZero($expected, $result);
  }

  /**
   * Data provider for SortArray::sortByWeightElement().
   *
   * @return array
   *   An array of tests, matching the parameter inputs for
   *   testSortByWeightElement.
   *
   * @see \Drupal\Tests\Component\Utility\SortArrayTest::testSortByWeightElement()
   */
  public static function providerSortByWeightElement() {
    $tests = [];

    // Weights set and equal.
    $tests[] = [
      ['weight' => 1],
      ['weight' => 1],
      0,
    ];

    // Weights set and $a is less (lighter) than $b.
    $tests[] = [
      ['weight' => 1],
      ['weight' => 2],
      -1,
    ];

    // Weights set and $a is greater (heavier) than $b.
    $tests[] = [
      ['weight' => 2],
      ['weight' => 1],
      1,
    ];

    // Weights not set.
    $tests[] = [
      [],
      [],
      0,
    ];

    // Weights for $b not set.
    $tests[] = [
      ['weight' => 1],
      [],
      1,
    ];

    // Weights for $a not set.
    $tests[] = [
      [],
      ['weight' => 1],
      -1,
    ];

    return $tests;
  }

  /**
   * Tests SortArray::sortByWeightProperty() input against expected output.
   *
   * @param array $a
   *   The first input array for the SortArray::sortByWeightProperty() method.
   * @param array $b
   *   The second input array for the SortArray::sortByWeightProperty().
   * @param int $expected
   *   The expected output from calling the method.
   *
   * @legacy-covers ::sortByWeightProperty
   * @legacy-covers ::sortByKeyInt
   */
  #[DataProvider('providerSortByWeightProperty')]
  public function testSortByWeightProperty($a, $b, $expected): void {
    $result = SortArray::sortByWeightProperty($a, $b);
    $this->assertBothNegativePositiveOrZero($expected, $result);
  }

  /**
   * Data provider for SortArray::sortByWeightProperty().
   *
   * @return array
   *   An array of tests, matching the parameter inputs for
   *   testSortByWeightProperty.
   *
   * @see \Drupal\Tests\Component\Utility\SortArrayTest::testSortByWeightProperty()
   */
  public static function providerSortByWeightProperty() {
    $tests = [];

    // Weights set and equal.
    $tests[] = [
      ['#weight' => 1],
      ['#weight' => 1],
      0,
    ];

    // Weights set and $a is less (lighter) than $b.
    $tests[] = [
      ['#weight' => 1],
      ['#weight' => 2],
      -1,
    ];

    // Weights set and $a is greater (heavier) than $b.
    $tests[] = [
      ['#weight' => 2],
      ['#weight' => 1],
      1,
    ];

    // Weights not set.
    $tests[] = [
      [],
      [],
      0,
    ];

    // Weights for $b not set.
    $tests[] = [
      ['#weight' => 1],
      [],
      1,
    ];

    // Weights for $a not set.
    $tests[] = [
      [],
      ['#weight' => 1],
      -1,
    ];

    return $tests;
  }

  /**
   * Tests SortArray::sortByTitleElement() input against expected output.
   *
   * @param array $a
   *   The first input item for comparison.
   * @param array $b
   *   The second item for comparison.
   * @param int $expected
   *   The expected output from calling the method.
   *
   * @legacy-covers ::sortByTitleElement
   * @legacy-covers ::sortByKeyString
   */
  #[DataProvider('providerSortByTitleElement')]
  public function testSortByTitleElement($a, $b, $expected): void {
    $result = SortArray::sortByTitleElement($a, $b);
    $this->assertBothNegativePositiveOrZero($expected, $result);
  }

  /**
   * Data provider for SortArray::sortByTitleElement().
   *
   * @return array
   *   An array of tests, matching the parameter inputs for
   *   testSortByTitleElement.
   *
   * @see \Drupal\Tests\Component\Utility\SortArrayTest::testSortByTitleElement()
   */
  public static function providerSortByTitleElement() {
    $tests = [];

    // Titles set and equal.
    $tests[] = [
      ['title' => 'test'],
      ['title' => 'test'],
      0,
    ];

    // Title $a not set.
    $tests[] = [
      [],
      ['title' => 'test'],
      -4,
    ];

    // Title $b not set.
    $tests[] = [
      ['title' => 'test'],
      [],
      4,
    ];

    // Titles set but not equal.
    $tests[] = [
      ['title' => 'test'],
      ['title' => 'testing'],
      -1,
    ];

    // Titles set but not equal.
    $tests[] = [
      ['title' => 'testing'],
      ['title' => 'test'],
      1,
    ];

    return $tests;
  }

  /**
   * Tests SortArray::sortByTitleProperty() input against expected output.
   *
   * @param array $a
   *   The first input item for comparison.
   * @param array $b
   *   The second item for comparison.
   * @param int $expected
   *   The expected output from calling the method.
   *
   * @legacy-covers ::sortByTitleProperty
   * @legacy-covers ::sortByKeyString
   */
  #[DataProvider('providerSortByTitleProperty')]
  public function testSortByTitleProperty($a, $b, $expected): void {
    $result = SortArray::sortByTitleProperty($a, $b);
    $this->assertBothNegativePositiveOrZero($expected, $result);
  }

  /**
   * Data provider for SortArray::sortByTitleProperty().
   *
   * @return array
   *   An array of tests, matching the parameter inputs for
   *   testSortByTitleProperty.
   *
   * @see \Drupal\Tests\Component\Utility\SortArrayTest::testSortByTitleProperty()
   */
  public static function providerSortByTitleProperty() {
    $tests = [];

    // Titles set and equal.
    $tests[] = [
      ['#title' => 'test'],
      ['#title' => 'test'],
      0,
    ];

    // Title $a not set.
    $tests[] = [
      [],
      ['#title' => 'test'],
      -4,
    ];

    // Title $b not set.
    $tests[] = [
      ['#title' => 'test'],
      [],
      4,
    ];

    // Titles set but not equal.
    $tests[] = [
      ['#title' => 'test'],
      ['#title' => 'testing'],
      -1,
    ];

    // Titles set but not equal.
    $tests[] = [
      ['#title' => 'testing'],
      ['#title' => 'test'],
      1,
    ];

    return $tests;
  }

  /**
   * Asserts that numbers are either both negative, both positive or both zero.
   *
   * The exact values returned by comparison functions differ between PHP
   * versions and are considered an "implementation detail".
   *
   * @param int $expected
   *   Expected comparison function return value.
   * @param int $result
   *   Actual comparison function return value.
   *
   * @internal
   */
  protected function assertBothNegativePositiveOrZero(int $expected, int $result): void {
    $this->assertIsNumeric($expected);
    $this->assertIsNumeric($result);
    $message = "Numbers should be both negative, both positive or both zero. Expected: $expected, actual: $result";
    if ($expected > 0) {
      $this->assertGreaterThan(0, $result, $message);
    }
    elseif ($expected < 0) {
      $this->assertLessThan(0, $result, $message);
    }
    else {
      $this->assertEquals(0, $result, $message);
    }
  }

}
