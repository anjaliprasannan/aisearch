<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Kernel\Handler;

use Drupal\Tests\views\Kernel\ViewsKernelTestBase;
use Drupal\views\Views;

/**
 * Tests for core Drupal\views\Plugin\views\sort\Date handler.
 *
 * @group views
 */
class SortDateTest extends ViewsKernelTestBase {

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['test_view'];

  /**
   * Generates an expected result set based on the specified granularity and order.
   */
  protected function expectedResultSet($granularity, $reverse = TRUE): array {
    $expected = [];
    if (!$reverse) {
      switch ($granularity) {
        case 'second':
          $expected = [
            ['name' => 'John'],
            ['name' => 'Paul'],
            ['name' => 'Meredith'],
            ['name' => 'Ringo'],
            ['name' => 'George'],
          ];
          break;

        case 'minute':
          $expected = [
            ['name' => 'John'],
            ['name' => 'Paul'],
            ['name' => 'Ringo'],
            ['name' => 'Meredith'],
            ['name' => 'George'],
          ];
          break;

        case 'hour':
          $expected = [
            ['name' => 'John'],
            ['name' => 'Ringo'],
            ['name' => 'Paul'],
            ['name' => 'Meredith'],
            ['name' => 'George'],
          ];
          break;

        case 'day':
          $expected = [
            ['name' => 'John'],
            ['name' => 'Ringo'],
            ['name' => 'Paul'],
            ['name' => 'Meredith'],
            ['name' => 'George'],
          ];
          break;

        case 'week':
          $expected = [
            ['name' => 'John'],
            ['name' => 'George'],
            ['name' => 'Ringo'],
            ['name' => 'Paul'],
            ['name' => 'Meredith'],
          ];
          break;

        case 'month':
          $expected = [
            ['name' => 'John'],
            ['name' => 'George'],
            ['name' => 'Ringo'],
            ['name' => 'Paul'],
            ['name' => 'Meredith'],
          ];
          break;

        case 'year':
          $expected = [
            ['name' => 'John'],
            ['name' => 'George'],
            ['name' => 'Ringo'],
            ['name' => 'Paul'],
            ['name' => 'Meredith'],
          ];
          break;
      }
    }
    else {
      switch ($granularity) {
        case 'second':
          $expected = [
            ['name' => 'George'],
            ['name' => 'Ringo'],
            ['name' => 'Meredith'],
            ['name' => 'Paul'],
            ['name' => 'John'],
          ];
          break;

        case 'minute':
          $expected = [
            ['name' => 'George'],
            ['name' => 'Ringo'],
            ['name' => 'Meredith'],
            ['name' => 'Paul'],
            ['name' => 'John'],
          ];
          break;

        case 'hour':
          $expected = [
            ['name' => 'George'],
            ['name' => 'Ringo'],
            ['name' => 'Paul'],
            ['name' => 'Meredith'],
            ['name' => 'John'],
          ];
          break;

        case 'day':
          $expected = [
            ['name' => 'George'],
            ['name' => 'John'],
            ['name' => 'Ringo'],
            ['name' => 'Paul'],
            ['name' => 'Meredith'],
          ];
          break;

        case 'week':
          $expected = [
            ['name' => 'John'],
            ['name' => 'George'],
            ['name' => 'Ringo'],
            ['name' => 'Paul'],
            ['name' => 'Meredith'],
          ];
          break;

        case 'month':
          $expected = [
            ['name' => 'John'],
            ['name' => 'George'],
            ['name' => 'Ringo'],
            ['name' => 'Paul'],
            ['name' => 'Meredith'],
          ];
          break;

        case 'year':
          $expected = [
            ['name' => 'John'],
            ['name' => 'George'],
            ['name' => 'Ringo'],
            ['name' => 'Paul'],
            ['name' => 'Meredith'],
          ];
          break;
      }
    }

    return $expected;
  }

  /**
   * Tests numeric ordering of the result set.
   */
  public function testDateOrdering(): void {
    foreach (['second', 'minute', 'hour', 'day', 'week', 'month', 'year'] as $granularity) {
      foreach ([FALSE, TRUE] as $reverse) {
        $view = Views::getView('test_view');
        $view->setDisplay();

        // Change the fields.
        $view->displayHandlers->get('default')->overrideOption('fields', [
          'name' => [
            'id' => 'name',
            'table' => 'views_test_data',
            'field' => 'name',
            'relationship' => 'none',
          ],
          'created' => [
            'id' => 'created',
            'table' => 'views_test_data',
            'field' => 'created',
            'relationship' => 'none',
          ],
        ]);

        // Change the ordering.
        $view->displayHandlers->get('default')->overrideOption('sorts', [
          'created' => [
            'id' => 'created',
            'table' => 'views_test_data',
            'field' => 'created',
            'relationship' => 'none',
            'granularity' => $granularity,
            'order' => $reverse ? 'DESC' : 'ASC',
          ],
          'id' => [
            'id' => 'id',
            'table' => 'views_test_data',
            'field' => 'id',
            'relationship' => 'none',
            'order' => 'ASC',
          ],
        ]);

        // Execute the view.
        $this->executeView($view);

        // Verify the result.
        $this->assertIdenticalResultset($view, $this->expectedResultSet($granularity, $reverse), [
          'views_test_data_name' => 'name',
        ], sprintf('Result is returned correctly when ordering by granularity %s, %s.', $granularity, $reverse ? 'reverse' : 'forward'));
        $view->destroy();
        unset($view);
      }
    }
  }

}
