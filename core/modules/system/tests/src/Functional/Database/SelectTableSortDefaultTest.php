<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Functional\Database;

/**
 * Tests the tablesort query extender.
 *
 * @group Database
 */
class SelectTableSortDefaultTest extends DatabaseTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Confirms that a tablesort query returns the correct results.
   *
   * Note that we have to make an HTTP request to a test page handler
   * because the pager depends on GET parameters.
   */
  public function testTableSortQuery(): void {
    $sorts = [
      ['field' => 'Task ID', 'sort' => 'desc', 'first' => 'perform at superbowl', 'last' => 'eat'],
      ['field' => 'Task ID', 'sort' => 'asc', 'first' => 'eat', 'last' => 'perform at superbowl'],
      ['field' => 'Task', 'sort' => 'asc', 'first' => 'code', 'last' => 'sleep'],
      ['field' => 'Task', 'sort' => 'desc', 'first' => 'sleep', 'last' => 'code'],
      // More elements here.

    ];

    foreach ($sorts as $sort) {
      $this->drupalGet('database_test/tablesort/', ['query' => ['order' => $sort['field'], 'sort' => $sort['sort']]]);
      $data = json_decode($this->getSession()->getPage()->getContent());

      $first = array_shift($data->tasks);
      $last = array_pop($data->tasks);

      $this->assertEquals($sort['first'], $first->task, 'Items appear in the correct order.');
      $this->assertEquals($sort['last'], $last->task, 'Items appear in the correct order.');
    }
  }

  /**
   * Confirms precedence of tablesorts headers.
   *
   * If a tablesort orderByHeader is called before another orderBy, then its
   * header happens first.
   */
  public function testTableSortQueryFirst(): void {
    $sorts = [
      ['field' => 'Task ID', 'sort' => 'desc', 'first' => 'perform at superbowl', 'last' => 'eat'],
      ['field' => 'Task ID', 'sort' => 'asc', 'first' => 'eat', 'last' => 'perform at superbowl'],
      ['field' => 'Task', 'sort' => 'asc', 'first' => 'code', 'last' => 'sleep'],
      ['field' => 'Task', 'sort' => 'desc', 'first' => 'sleep', 'last' => 'code'],
      // More elements here.

    ];

    foreach ($sorts as $sort) {
      $this->drupalGet('database_test/tablesort_first/', ['query' => ['order' => $sort['field'], 'sort' => $sort['sort']]]);
      $data = json_decode($this->getSession()->getPage()->getContent());

      $first = array_shift($data->tasks);
      $last = array_pop($data->tasks);

      $this->assertEquals($sort['first'], $first->task, "Items appear in the correct order sorting by {$sort['field']} {$sort['sort']}.");
      $this->assertEquals($sort['last'], $last->task, "Items appear in the correct order sorting by {$sort['field']} {$sort['sort']}.");
    }
  }

  /**
   * Confirms that tableselect is rendered without error.
   *
   * Specifically that no sort is set in a tableselect, and that header links
   * are correct.
   */
  public function testTableSortDefaultSort(): void {
    $assert = $this->assertSession();

    $this->drupalGet('database_test/tablesort_default_sort');

    // Verify that the table was displayed. Just the header is checked for
    // because if there were any fatal errors or exceptions in displaying the
    // sorted table, it would not print the table.
    $assert->pageTextContains('Username');

    // Verify that the header links are built properly.
    $assert->linkByHrefExists('database_test/tablesort_default_sort');
    $assert->responseMatches('/\<a.*title\=\"sort by Username\".*\>/');
  }

}
