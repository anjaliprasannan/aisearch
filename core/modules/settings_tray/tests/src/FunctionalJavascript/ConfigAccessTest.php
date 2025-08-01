<?php

declare(strict_types=1);

namespace Drupal\Tests\settings_tray\FunctionalJavascript;

use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests handling of configuration overrides.
 */
#[Group('settings_tray')]
class ConfigAccessTest extends SettingsTrayTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'menu_link_content',
    'menu_ui',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $user = $this->createUser([
      'administer blocks',
      'access contextual links',
      'access toolbar',
    ]);
    $this->drupalLogin($user);
  }

  /**
   * Tests access to block forms with related configuration is correct.
   */
  public function testBlockConfigAccess(): void {
    $page = $this->getSession()->getPage();
    $web_assert = $this->assertSession();

    // Confirm that System Branding block does not expose Site Name field
    // without permission.
    $block = $this->placeBlock('system_branding_block');
    $this->drupalGet('user');
    $this->enableEditMode();
    $this->openBlockForm($this->getBlockSelector($block));
    // The site name field should not appear because the user doesn't have
    // permission.
    $web_assert->fieldNotExists('settings[site_information][site_name]');
    $page_load_hash_1 = $this->getSession()->evaluateScript('window.performance.timeOrigin');
    $page->pressButton('Save Site branding');
    // Pressing the button triggered no validation errors and an AJAX redirect
    // that reloaded the page.
    $this->waitForOffCanvasToClose();
    $page_load_hash_2 = $this->getSession()->evaluateScript('window.performance.timeOrigin');
    $this->assertNotSame($page_load_hash_1, $page_load_hash_2);
    $web_assert->elementExists('css', 'div:contains(The block configuration has been saved)');
    // Confirm we did not save changes to the configuration.
    $this->assertEquals('Drupal', \Drupal::configFactory()->getEditable('system.site')->get('name'));

    $this->grantPermissions(Role::load(Role::AUTHENTICATED_ID), ['administer site configuration']);
    $this->drupalGet('user');
    $this->openBlockForm($this->getBlockSelector($block));
    // The site name field should appear because the user does have permission.
    $web_assert->fieldExists('settings[site_information][site_name]');

    // Confirm that the Menu block does not expose menu configuration without
    // permission.
    // Add a link or the menu will not render.
    $menu_link_content = MenuLinkContent::create([
      'title' => 'This is on the menu',
      'menu_name' => 'main',
      'link' => ['uri' => 'route:<front>'],
    ]);
    $menu_link_content->save();
    $this->assertNotEmpty($menu_link_content->isEnabled());
    $menu_without_overrides = \Drupal::configFactory()->getEditable('system.menu.main')->get();
    $block = $this->placeBlock('system_menu_block:main');
    $this->drupalGet('user');
    $web_assert->pageTextContains('This is on the menu');
    $this->openBlockForm($this->getBlockSelector($block));
    // Edit menu form should not appear because the user doesn't have
    // permission.
    $web_assert->pageTextNotContains('Edit menu');
    $page_load_hash_3 = $this->getSession()->evaluateScript('window.performance.timeOrigin');
    $page->pressButton('Save Main navigation');
    $this->waitForOffCanvasToClose();
    // Pressing the button triggered no validation errors and an AJAX redirect
    // that reloaded the page.
    $page_load_hash_4 = $this->getSession()->evaluateScript('window.performance.timeOrigin');
    $this->assertNotSame($page_load_hash_3, $page_load_hash_4);
    $web_assert->elementExists('css', 'div:contains(The block configuration has been saved)');
    // Confirm we did not save changes to the menu or the menu link.
    $this->assertEquals($menu_without_overrides, \Drupal::configFactory()->getEditable('system.menu.main')->get());
    $menu_link_content = MenuLinkContent::load($menu_link_content->id());
    $this->assertNotEmpty($menu_link_content->isEnabled());
    // Confirm menu is still on the page.
    $this->drupalGet('user');
    $web_assert->pageTextContains('This is on the menu');

    $this->grantPermissions(Role::load(Role::AUTHENTICATED_ID), ['administer menu']);
    $this->drupalGet('user');
    $web_assert->pageTextContains('This is on the menu');
    $this->openBlockForm($this->getBlockSelector($block));
    // Edit menu form should appear because the user does have permission.
    $web_assert->pageTextContains('Edit menu');
  }

}
