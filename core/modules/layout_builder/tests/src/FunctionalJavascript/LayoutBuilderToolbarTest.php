<?php

declare(strict_types=1);

namespace Drupal\Tests\layout_builder\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Test Layout Builder integration with Toolbar.
 */
#[Group('layout_builder')]
class LayoutBuilderToolbarTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'node',
    'field_ui',
    'layout_builder',
    'node',
    'toolbar',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'starterkit_theme';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalPlaceBlock('local_tasks_block');

    // Create a content type.
    $this->createContentType([
      'type' => 'bundle_with_section_field',
      'name' => 'Bundle with section field',
    ]);

    $this->createNode([
      'type' => 'bundle_with_section_field',
      'title' => 'The first node title',
      'body' => [
        [
          'value' => 'The first node body',
        ],
      ],
    ]);

  }

  /**
   * Tests the 'Back to site' link behaves with manage layout as admin page.
   */
  public function testBackToSiteLink(): void {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    $this->drupalLogin($this->drupalCreateUser([
      'configure any layout',
      'access administration pages',
      'administer node display',
      'administer node fields',
      'access toolbar',
    ]));

    $field_ui_prefix = 'admin/structure/types/manage/bundle_with_section_field';
    // From the manage display page, go to manage the layout.
    $this->drupalGet("$field_ui_prefix/display/default");
    $this->submitForm(['layout[enabled]' => TRUE], 'Save');
    $assert_session->linkExists('Manage layout');
    $this->clickLink('Manage layout');
    // Save the defaults.
    $page->pressButton('Save layout');
    $assert_session->addressEquals("$field_ui_prefix/display/default");

    // As the Layout Builder UI is typically displayed using the frontend theme,
    // it is not marked as an administrative page at the route level even though
    // it performs an administrative task, therefore, we need to verify that it
    // behaves as such, redirecting out of the admin section.
    // Clicking "Back to site" navigates to the homepage.
    $this->drupalGet("$field_ui_prefix/display/default/layout");
    $this->clickLink('Back to site');
    $assert_session->addressEquals("/user/2");

    $this->drupalGet("$field_ui_prefix/display/default/layout/discard-changes");
    $page->pressButton('Confirm');
    $this->clickLink('Back to site');
    $assert_session->addressEquals("/user/2");

    $this->drupalGet("$field_ui_prefix/display/default/layout/disable");
    $page->pressButton('Confirm');
    $this->clickLink('Back to site');
    $assert_session->addressEquals("/user/2");
  }

}
