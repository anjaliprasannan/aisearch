<?php

declare(strict_types=1);

namespace Drupal\Tests\config\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the Config operations through the UI.
 */
#[Group('config')]
class ConfigEntityTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['config_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests ajax operations through the UI on 'Add' page.
   */
  public function testAjaxOnAddPage(): void {
    $this->drupalLogin($this->drupalCreateUser([
      'administer site configuration',
    ]));

    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    $this->drupalGet('admin/structure/config_test/add');
    // Test that 'size value' field is not show initially, and it is show after
    // selecting value in the 'size' field.
    $this->assertNull($page->findField('size_value'));
    $page->findField('size')->setValue('custom');
    $this->assertNotNull($assert_session->waitForField('size_value'));
  }

}
