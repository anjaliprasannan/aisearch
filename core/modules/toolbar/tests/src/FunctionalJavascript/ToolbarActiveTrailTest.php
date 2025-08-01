<?php

declare(strict_types=1);

namespace Drupal\Tests\toolbar\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestWith;

/**
 * Tests that the active trail is maintained in the toolbar.
 */
#[Group('toolbar')]
class ToolbarActiveTrailTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['toolbar', 'node', 'field_ui'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);
    $this->drupalLogin($this->drupalCreateUser([
      'access administration pages',
      'administer content types',
      'administer node fields',
      'access toolbar',
    ]));
  }

  /**
   * Tests that the active trail is maintained even when traversed deeper.
   *
   * @param string $orientation
   *   The toolbar orientation.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   */
  #[TestWith(["vertical"])]
  #[TestWith(["horizontal"])]
  public function testToolbarActiveTrail(string $orientation): void {
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    $this->drupalGet('<front>');
    $this->assertNotEmpty($this->assertSession()->waitForElement('css', 'body.toolbar-horizontal'));
    $this->assertNotEmpty($this->assertSession()->waitForElementVisible('css', '.toolbar-tray'));
    $this->assertSession()->waitForElementRemoved('css', '.toolbar-loading');
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', '#toolbar-item-administration.is-active'));

    // If testing for vertical orientation of the toolbar then switch to it.
    if ($orientation === 'vertical') {
      $page->pressButton('Vertical orientation');
    }

    // Traverse deeper.
    $this->clickLink('Structure');
    $this->clickLink('Content types');
    $this->clickLink('Manage fields');
    $this->clickLink('Edit');

    if ($orientation === 'vertical') {
      $this->assertNotEmpty($assert_session->waitForElementVisible('named',
        ['link', 'Structure']));
      // Assert that menu-item--active-trail was maintained.
      $this->assertTrue($assert_session->waitForElementVisible('named',
        ['link', 'Structure'])->getParent()->getParent()->hasClass('menu-item--active-trail'));
      $this->assertTrue($assert_session->waitForElementVisible('named',
        ['link', 'Content types'])->getParent()->getParent()->hasClass('menu-item--active-trail'));
      // Change orientation and check focus is maintained.
      $page->pressButton('Horizontal orientation');
      $this->assertTrue($assert_session->waitForElementVisible('css',
        '#toolbar-link-system-admin_structure')->hasClass('is-active'));
    }
    else {
      $this->assertNotEmpty($assert_session->waitForElementVisible('css', '#toolbar-link-system-admin_structure'));
      // Assert that is-active was maintained.
      $this->assertTrue($assert_session->waitForElementVisible('css', '#toolbar-link-system-admin_structure')->hasClass('is-active'));
      // Change orientation and check focus is maintained.
      $page->pressButton('Vertical orientation');
      // Introduce a delay to let the focus load.
      $this->getSession()->wait(150);
      $this->assertTrue($assert_session->waitForElementVisible('named',
        ['link', 'Structure'])->getParent()->getParent()->hasClass('menu-item--active-trail'));
      $this->assertTrue($assert_session->waitForElementVisible('named',
        ['link', 'Content types'])->getParent()->getParent()->hasClass('menu-item--active-trail'));
    }
  }

}
