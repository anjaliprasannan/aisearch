<?php

declare(strict_types=1);

namespace Drupal\Tests\views\FunctionalJavascript\Plugin\views\Handler;

use Drupal\field\Entity\FieldConfig;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\views\Tests\ViewTestData;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the grouped exposed filter admin UI.
 */
#[Group('views')]
class GroupedExposedFilterTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'views',
    'views_ui',
    'user',
    'views_test_config',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['test_exposed_admin_ui'];

  /**
   * The account.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $account;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    ViewTestData::createTestViews(static::class, ['views_test_config']);

    // Disable automatic live preview to make the sequence of calls clearer.
    \Drupal::configFactory()->getEditable('views.settings')->set('ui.always_live_preview', FALSE)->save();

    $this->account = $this->drupalCreateUser(['administer views']);
    $this->drupalLogin($this->account);

    // Setup a node type that has the right fields for the test view.
    NodeType::create([
      'type' => 'page',
      'name' => 'Page',
    ])->save();

    FieldConfig::create([
      'entity_type' => 'node',
      'field_name' => 'body',
      'bundle' => 'page',
    ])->save();
  }

  /**
   * Tests if the right fields are shown and the right values set.
   */
  public function testGroupedFilterValuesUI(): void {
    $web_assert = $this->assertSession();

    $this->drupalGet('/admin/structure/views/view/test_exposed_admin_ui');
    $page = $this->getSession()->getPage();

    // Open the dialog for the grouped filter.
    $page->clickLink('Content: Authored on (grouped)');
    $web_assert->assertWaitOnAjaxRequest();

    // Test that the 'min' field is shown and that it contains the right value.
    $between_from = $page->findField('options[group_info][group_items][1][value][min]');
    $this->assertNotEmpty($between_from->isVisible());
    $this->assertEquals('2015-01-01', $between_from->getValue());

    // Test that the 'max' field is shown and that it contains the right value.
    $between_to = $page->findField('options[group_info][group_items][1][value][max]');
    $this->assertNotEmpty($between_to->isVisible());
    $this->assertEquals('2016-01-01', $between_to->getValue());

    $weight = $page->findField('options[group_info][group_items][1][weight]');

    // If there are 3 items, values from -3 to 3 should be available.
    $this->assertNull($weight->find('named', ['option', -4]));
    foreach (range(-3, 3) as $value) {
      $this->assertNotEmpty($weight->find('named', ['option', $value]));
    }
    $this->assertEmpty($weight->find('named', ['option', 4]));

    $page->pressButton("Add another item");
    $web_assert->waitForField('options[group_info][group_items][4][title]');

    // A new items was added, weight options should now be -4 to 4.
    $this->assertEmpty($weight->find('named', ['option', -5]));
    foreach (range(-4, 4) as $value) {
      $this->assertNotEmpty($weight->find('named', ['option', $value]));
    }
    $this->assertEmpty($weight->find('named', ['option', 5]));

    // Set the date value to a zero value and make sure it's stored.
    $between_from = $page->findField('options[group_info][group_items][1][value][min]');
    $between_from->setValue('0');
    $apply_button = $page->find('css', '.views-ui-dialog button.button--primary');
    $this->assertNotEmpty($apply_button);
    $apply_button->press();
    $web_assert->assertWaitOnAjaxRequest();

    // Open the dialog for the grouped filter.
    $page->clickLink('Content: Authored on (grouped)');
    $web_assert->assertWaitOnAjaxRequest();

    // Test that the 'min' field is shown and that it contains the right value.
    $between_from = $page->findField('options[group_info][group_items][1][value][min]');
    $this->assertNotEmpty($between_from->isVisible());
    $this->assertEquals('0', $between_from->getValue());
  }

}
