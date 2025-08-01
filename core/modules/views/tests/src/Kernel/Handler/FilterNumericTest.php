<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Kernel\Handler;

use Drupal\Tests\views\Kernel\ViewsKernelTestBase;
use Drupal\views\Views;

/**
 * Tests the numeric filter handler.
 *
 * @group views
 * @group #slow
 */
class FilterNumericTest extends ViewsKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['test_view'];

  /**
   * Map column names.
   *
   * @var array
   */
  protected $columnMap = [
    'views_test_data_name' => 'name',
    'views_test_data_age' => 'age',
  ];

  /**
   * Defines Views data, allowing 'age' to be empty and 'id' to be required.
   */
  public function viewsData() {
    $data = parent::viewsData();
    $data['views_test_data']['age']['filter']['allow empty'] = TRUE;
    $data['views_test_data']['id']['filter']['allow empty'] = FALSE;

    return $data;
  }

  /**
   * Tests filtering records using an exact match on a numeric field.
   */
  public function testFilterNumericSimple(): void {
    $view = Views::getView('test_view');
    $view->setDisplay();

    // Change the filtering.
    $view->displayHandlers->get('default')->overrideOption('filters', [
      'age' => [
        'id' => 'age',
        'table' => 'views_test_data',
        'field' => 'age',
        'relationship' => 'none',
        'operator' => '=',
        'value' => ['value' => 28],
      ],
    ]);

    $this->executeView($view);
    $resultset = [
      [
        'name' => 'Ringo',
        'age' => 28,
      ],
    ];
    $this->assertIdenticalResultset($view, $resultset, $this->columnMap);
  }

  /**
   * Tests filtering using exposed grouped filters.
   */
  public function testFilterNumericExposedGroupedSimple(): void {
    $filters = $this->getGroupedExposedFilters();
    $view = Views::getView('test_view');
    $view->newDisplay('page', 'Page', 'page_1');

    // Filter: Age, Operator: =, Value: 28.
    $filters['age']['group_info']['default_group'] = 1;
    $view->setDisplay('page_1');
    $view->displayHandlers->get('page_1')->overrideOption('filters', $filters);
    $view->save();

    $this->executeView($view);
    $resultset = [
      [
        'name' => 'Ringo',
        'age' => 28,
      ],
    ];
    $this->assertIdenticalResultset($view, $resultset, $this->columnMap);
  }

  /**
   * Tests the between operator.
   *
   * @param string $operator
   *   The operator to test ('between' or 'not between').
   * @param string $min
   *   The min value.
   * @param string $max
   *   The max value.
   * @param array $expected_result
   *   The expected results.
   *
   * @dataProvider providerTestFilterNumericBetween
   */
  public function testFilterNumericBetween($operator, $min, $max, array $expected_result): void {
    $view = Views::getView('test_view');
    $view->setDisplay();

    $view->displayHandlers->get('default')->overrideOption('filters', [
      'age' => [
        'id' => 'age',
        'table' => 'views_test_data',
        'field' => 'age',
        'relationship' => 'none',
        'operator' => $operator,
        'value' => [
          'min' => $min,
          'max' => $max,
        ],
      ],
    ]);

    $this->executeView($view);
    $this->assertIdenticalResultset($view, $expected_result, $this->columnMap);
  }

  /**
   * Provides data for self::testFilterNumericBetween().
   *
   * @return array
   *   An array of arrays, each containing the parameters for
   *   self::testFilterNumericBetween().
   */
  public static function providerTestFilterNumericBetween() {
    $all_result = [
      ['name' => 'John', 'age' => 25],
      ['name' => 'George', 'age' => 27],
      ['name' => 'Ringo', 'age' => 28],
      ['name' => 'Paul', 'age' => 26],
      ['name' => 'Meredith', 'age' => 30],
    ];

    return [
      // Each test case is operator, min, max, expected result.
      'Test between' => [
        'between', 26, 29, [
          ['name' => 'George', 'age' => 27],
          ['name' => 'Ringo', 'age' => 28],
          ['name' => 'Paul', 'age' => 26],
        ],
      ],
      'Test between with just min' => [
        'between', 28, '', [
          ['name' => 'Ringo', 'age' => 28],
          ['name' => 'Meredith', 'age' => 30],
        ],
      ],
      'Test between with just max' => [
        'between', '', 26,
        [
          ['name' => 'John', 'age' => 25],
          ['name' => 'Paul', 'age' => 26],
        ],
      ],
      'Test between with empty min and max' => [
        'between', '', '', $all_result,
      ],
      'Test not between' => [
        'not between', 26, 29, [
          ['name' => 'John', 'age' => 25],
          ['name' => 'Meredith', 'age' => 30],
        ],
      ],
      'Test not between with just min' => [
        'not between', 28, '', [
          ['name' => 'John', 'age' => 25],
          ['name' => 'George', 'age' => 27],
          ['name' => 'Paul', 'age' => 26],
        ],
      ],
      'Test not between with just max' => [
        'not between', '', 26, [
          ['name' => 'George', 'age' => 27],
          ['name' => 'Ringo', 'age' => 28],
          ['name' => 'Meredith', 'age' => 30],
        ],
      ],
      'Test not between with empty min and max' => [
        'not between', '', '', $all_result,
      ],
    ];
  }

  /**
   * Tests filtering records using a ranged condition on a numeric field.
   */
  public function testFilterNumericExposedGroupedBetween(): void {
    $filters = $this->getGroupedExposedFilters();
    $view = Views::getView('test_view');
    $view->newDisplay('page', 'Page', 'page_1');

    // Filter: Age, Operator: between, Value: 26 and 29.
    $filters['age']['group_info']['default_group'] = 2;
    $view->setDisplay('page_1');
    $view->displayHandlers->get('page_1')->overrideOption('filters', $filters);
    $view->save();

    $this->executeView($view);
    $resultset = [
      [
        'name' => 'George',
        'age' => 27,
      ],
      [
        'name' => 'Ringo',
        'age' => 28,
      ],
      [
        'name' => 'Paul',
        'age' => 26,
      ],
    ];
    $this->assertIdenticalResultset($view, $resultset, $this->columnMap);
  }

  /**
   * Tests filtering records outside a specified numeric range.
   */
  public function testFilterNumericExposedGroupedNotBetween(): void {
    $filters = $this->getGroupedExposedFilters();
    $view = Views::getView('test_view');
    $view->newDisplay('page', 'Page', 'page_1');

    // Filter: Age, Operator: between, Value: 26 and 29.
    $filters['age']['group_info']['default_group'] = 3;
    $view->setDisplay('page_1');
    $view->displayHandlers->get('page_1')->overrideOption('filters', $filters);
    $view->save();

    $this->executeView($view);
    $resultset = [
      [
        'name' => 'John',
        'age' => 25,
      ],
      [
        'name' => 'Meredith',
        'age' => 30,
      ],
    ];
    $this->assertIdenticalResultset($view, $resultset, $this->columnMap);
  }

  /**
   * Tests the numeric filter handler with the 'regular_expression' operator.
   */
  public function testFilterNumericRegularExpression(): void {
    $view = Views::getView('test_view');
    $view->setDisplay();

    // Filtering by regular expression pattern.
    $view->displayHandlers->get('default')->overrideOption('filters', [
      'age' => [
        'id' => 'age',
        'table' => 'views_test_data',
        'field' => 'age',
        'relationship' => 'none',
        'operator' => 'regular_expression',
        'value' => [
          'value' => '2[8]',
        ],
      ],
    ]);

    $this->executeView($view);
    $resultset = [
      [
        'name' => 'Ringo',
        'age' => 28,
      ],
    ];
    $this->assertIdenticalResultset($view, $resultset, $this->columnMap);
  }

  /**
   * Tests the numeric filter with negated 'regular_expression' operator.
   */
  public function testFilterNumericNotRegularExpression(): void {
    $view = Views::getView('test_view');
    $view->setDisplay();

    // Filtering by regular expression pattern.
    $view->displayHandlers->get('default')->overrideOption('filters', [
      'age' => [
        'id' => 'age',
        'table' => 'views_test_data',
        'field' => 'age',
        'relationship' => 'none',
        'operator' => 'not_regular_expression',
        'value' => [
          'value' => '2[8]',
        ],
      ],
    ]);

    $this->executeView($view);
    $resultset = [
      [
        'name' => 'John',
        'age' => 25,
      ],
      [
        'name' => 'George',
        'age' => 27,
      ],
      [
        'name' => 'Paul',
        'age' => 26,
      ],
      [
        'name' => 'Meredith',
        'age' => 30,
      ],
    ];
    $this->assertIdenticalResultset($view, $resultset, $this->columnMap);
  }

  /**
   * Tests the "numeric" filter with grouped exposed filters.
   *
   * The tests are performed with the 'regular_expression' operator.
   */
  public function testFilterNumericExposedGroupedRegularExpression(): void {
    $filters = $this->getGroupedExposedFilters();
    $view = Views::getView('test_view');
    $view->newDisplay('page', 'Page', 'page_1');

    // Filter: Age, Operator: regular_expression, Value: 2[7-8].
    $filters['age']['group_info']['default_group'] = 6;
    $view->setDisplay('page_1');
    $view->displayHandlers->get('page_1')->overrideOption('filters', $filters);
    $view->save();

    $this->executeView($view);
    $resultset = [
      [
        'name' => 'George',
        'age' => 27,
      ],
      [
        'name' => 'Ringo',
        'age' => 28,
      ],
    ];
    $this->assertIdenticalResultset($view, $resultset, $this->columnMap);
  }

  /**
   * Tests the numeric filter with grouped exposed filters.
   *
   * Tests the numeric filter handler with the 'not_regular_expression' operator
   * to grouped exposed filters.
   */
  public function testFilterNumericExposedGroupedNotRegularExpression(): void {
    $filters = $this->getGroupedExposedFilters();
    $view = Views::getView('test_view');
    $view->newDisplay('page', 'Page', 'page_1');

    // Filter: Age, Operator: not_regular_expression, Value: 2[7-8].
    $filters['age']['group_info']['default_group'] = 7;
    $view->setDisplay('page_1');
    $view->displayHandlers->get('page_1')->overrideOption('filters', $filters);
    $view->save();

    $this->executeView($view);
    $resultset = [
      [
        'name' => 'John',
        'age' => 25,
      ],
      [
        'name' => 'Paul',
        'age' => 26,
      ],
      [
        'name' => 'Meredith',
        'age' => 30,
      ],
    ];
    $this->assertIdenticalResultset($view, $resultset, $this->columnMap);
  }

  /**
   * Tests filtering records based on empty and non-empty values.
   */
  public function testFilterNumericEmpty(): void {
    $view = Views::getView('test_view');
    $view->setDisplay();

    // Change the filtering.
    $view->displayHandlers->get('default')->overrideOption('filters', [
      'age' => [
        'id' => 'age',
        'table' => 'views_test_data',
        'field' => 'age',
        'relationship' => 'none',
        'operator' => 'empty',
      ],
    ]);

    $this->executeView($view);
    $resultset = [];
    $this->assertIdenticalResultset($view, $resultset, $this->columnMap);

    $view->destroy();
    $view->setDisplay();

    // Change the filtering.
    $view->displayHandlers->get('default')->overrideOption('filters', [
      'age' => [
        'id' => 'age',
        'table' => 'views_test_data',
        'field' => 'age',
        'relationship' => 'none',
        'operator' => 'not empty',
      ],
    ]);

    $this->executeView($view);
    $resultset = [
      [
        'name' => 'John',
        'age' => 25,
      ],
      [
        'name' => 'George',
        'age' => 27,
      ],
      [
        'name' => 'Ringo',
        'age' => 28,
      ],
      [
        'name' => 'Paul',
        'age' => 26,
      ],
      [
        'name' => 'Meredith',
        'age' => 30,
      ],
    ];
    $this->assertIdenticalResultset($view, $resultset, $this->columnMap);
  }

  /**
   * Tests filtering exposed grouped records based on empty values.
   */
  public function testFilterNumericExposedGroupedEmpty(): void {
    $filters = $this->getGroupedExposedFilters();
    $view = Views::getView('test_view');
    $view->newDisplay('page', 'Page', 'page_1');

    // Filter: Age, Operator: empty, Value:
    $filters['age']['group_info']['default_group'] = 4;
    $view->setDisplay('page_1');
    $view->displayHandlers->get('page_1')->overrideOption('filters', $filters);
    $view->save();

    $this->executeView($view);
    $resultset = [];
    $this->assertIdenticalResultset($view, $resultset, $this->columnMap);
  }

  /**
   * Tests filtering exposed grouped records based on non-empty values.
   */
  public function testFilterNumericExposedGroupedNotEmpty(): void {
    $filters = $this->getGroupedExposedFilters();
    $view = Views::getView('test_view');
    $view->newDisplay('page', 'Page', 'page_1');

    // Filter: Age, Operator: empty, Value:
    $filters['age']['group_info']['default_group'] = 5;
    $view->setDisplay('page_1');
    $view->displayHandlers->get('page_1')->overrideOption('filters', $filters);
    $view->save();

    $this->executeView($view);
    $resultset = [
      [
        'name' => 'John',
        'age' => 25,
      ],
      [
        'name' => 'George',
        'age' => 27,
      ],
      [
        'name' => 'Ringo',
        'age' => 28,
      ],
      [
        'name' => 'Paul',
        'age' => 26,
      ],
      [
        'name' => 'Meredith',
        'age' => 30,
      ],
    ];
    $this->assertIdenticalResultset($view, $resultset, $this->columnMap);
  }

  /**
   * Tests whether empty filters are allowed for specific fields.
   */
  public function testAllowEmpty(): void {
    $view = Views::getView('test_view');
    $view->setDisplay();

    $view->displayHandlers->get('default')->overrideOption('filters', [
      'id' => [
        'id' => 'id',
        'table' => 'views_test_data',
        'field' => 'id',
        'relationship' => 'none',
      ],
      'age' => [
        'id' => 'age',
        'table' => 'views_test_data',
        'field' => 'age',
        'relationship' => 'none',
      ],
    ]);

    $view->initHandlers();

    $id_operators = $view->filter['id']->operators();
    $age_operators = $view->filter['age']->operators();

    $this->assertFalse(isset($id_operators['empty']));
    $this->assertFalse(isset($id_operators['not empty']));
    $this->assertTrue(isset($age_operators['empty']));
    $this->assertTrue(isset($age_operators['not empty']));
  }

  /**
   * Returns predefined grouped filter configurations for 'age'.
   *
   * @return array
   *   An array of grouped exposed filters.
   */
  protected function getGroupedExposedFilters(): array {
    $filters = [
      'age' => [
        'id' => 'age',
        'plugin_id' => 'numeric',
        'table' => 'views_test_data',
        'field' => 'age',
        'relationship' => 'none',
        'exposed' => TRUE,
        'expose' => [
          'operator' => 'age_op',
          'label' => 'age',
          'identifier' => 'age',
        ],
        'is_grouped' => TRUE,
        'group_info' => [
          'label' => 'age',
          'identifier' => 'age',
          'default_group' => 'All',
          'group_items' => [
            1 => [
              'title' => 'Age is 28',
              'operator' => '=',
              'value' => ['value' => 28],
            ],
            2 => [
              'title' => 'Age is between 26 and 29',
              'operator' => 'between',
              'value' => [
                'min' => 26,
                'max' => 29,
              ],
            ],
            3 => [
              'title' => 'Age is not between 26 and 29',
              'operator' => 'not between',
              'value' => [
                'min' => 26,
                'max' => 29,
              ],
            ],
            4 => [
              'title' => 'Age is empty',
              'operator' => 'empty',
            ],
            5 => [
              'title' => 'Age is not empty',
              'operator' => 'not empty',
            ],
            6 => [
              'title' => 'Age is regexp 2[7-8]',
              'operator' => 'regular_expression',
              'value' => [
                'value' => '2[7-8]',
              ],
            ],
            7 => [
              'title' => 'Age is regexp 2[7-8]',
              'operator' => 'not_regular_expression',
              'value' => [
                'value' => '2[7-8]',
              ],
            ],
          ],
        ],
      ],
    ];
    return $filters;
  }

}
