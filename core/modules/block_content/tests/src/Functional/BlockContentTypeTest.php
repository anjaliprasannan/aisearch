<?php

declare(strict_types=1);

namespace Drupal\Tests\block_content\Functional;

use Drupal\block_content\Entity\BlockContentType;
use Drupal\Component\Utility\Html;
use Drupal\Core\Url;
use Drupal\Tests\system\Functional\Menu\AssertBreadcrumbTrait;

/**
 * Ensures that block type functions work correctly.
 *
 * @group block_content
 */
class BlockContentTypeTest extends BlockContentTestBase {

  use AssertBreadcrumbTrait;
  /**
   * {@inheritdoc}
   */
  protected static $modules = ['field_ui'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Permissions to grant admin user.
   *
   * @var array
   */
  protected $permissions = [
    'administer block content',
    'administer blocks',
    'administer block_content fields',
    'administer block types',
    'administer block content',
    'access block library',
  ];

  /**
   * Whether or not to create an initial block type.
   *
   * @var bool
   */
  protected $autoCreateBasicBlockType = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalPlaceBlock('page_title_block');
  }

  /**
   * Tests the block types on the block/add page.
   */
  public function testBlockContentAddPage(): void {
    $this->createBlockContentType([
      'id' => 'bundle_1',
      'label' => 'Bundle 1',
      'description' => 'Bundle 1 description',
    ]);
    $this->createBlockContentType([
      'id' => 'bundle_2',
      'label' => 'Aaa Bundle 2',
      'description' => 'Bundle 2 description',
    ]);
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('block/add');
    // Ensure bundles are ordered by their label, not id.
    $this->assertSession()->pageTextMatches('/Aaa Bundle 2(.*)Bundle 1/');
    // Block type descriptions should display.
    $this->assertSession()->pageTextContains('Bundle 1 description');
    $this->assertSession()->pageTextContains('Bundle 2 description');
  }

  /**
   * Tests creating a block type programmatically and via a form.
   */
  public function testBlockContentTypeCreation(): void {
    // Log in a test user.
    $this->drupalLogin($this->adminUser);

    // Test the page with no block-types.
    $this->drupalGet('block/add');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('You have not created any block types yet');
    $this->clickLink('block type creation page');

    // Create a block type via the user interface.
    $edit = [
      'id' => 'foo',
      'label' => 'title for foo',
    ];
    $this->submitForm($edit, 'Save and manage fields');

    // Asserts that form submit redirects to the expected manage fields page.
    $this->assertSession()->addressEquals('admin/structure/block-content/manage/' . $edit['id'] . '/fields');

    $block_type = BlockContentType::load('foo');
    $this->assertInstanceOf(BlockContentType::class, $block_type);

    $field_definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions('block_content', 'foo');
    $this->assertTrue(isset($field_definitions['body']), 'Body field created when using the UI to create block content types.');

    // Check that the block type was created in site default language.
    $default_langcode = \Drupal::languageManager()->getDefaultLanguage()->getId();
    $this->assertEquals($block_type->language()->getId(), $default_langcode);

    // Create block types programmatically.
    $this->createBlockContentType(['id' => 'basic'], TRUE);
    $field_definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions('block_content', 'basic');
    $this->assertTrue(isset($field_definitions['body']), "Body field for 'basic' block type created when using the testing API to create block content types.");

    $this->createBlockContentType(['id' => 'other']);
    $field_definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions('block_content', 'other');
    $this->assertFalse(isset($field_definitions['body']), "Body field for 'other' block type not created when using the testing API to create block content types.");

    $block_type = BlockContentType::load('other');
    $this->assertInstanceOf(BlockContentType::class, $block_type);

    $this->drupalGet('block/add/' . $block_type->id());
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests editing a block type using the UI.
   */
  public function testBlockContentTypeEditing(): void {
    $this->drupalPlaceBlock('system_breadcrumb_block');
    // Now create an initial block-type.
    $this->createBlockContentType(['id' => 'basic'], TRUE);

    $this->drupalLogin($this->adminUser);
    // We need two block types to prevent /block/add redirecting.
    $this->createBlockContentType(['id' => 'other']);

    $field_definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions('block_content', 'other');
    $this->assertFalse(isset($field_definitions['body']), 'Body field was not created when using the API to create block content types.');

    // Verify that title and body fields are displayed.
    $this->drupalGet('block/add/basic');
    $this->assertSession()->pageTextContains('Block description');
    $this->assertNotEmpty($this->cssSelect('#edit-body-0-value'), 'Body field was found.');

    // Change the block type name.
    $edit = [
      'label' => 'Bar',
    ];
    $this->drupalGet('admin/structure/block-content/manage/basic');
    $this->assertSession()->titleEquals('Edit basic block type | Drupal');
    $this->submitForm($edit, 'Save');
    $front_page_path = Url::fromRoute('<front>')->toString();
    $this->assertBreadcrumb('admin/structure/block-content/manage/basic/fields', [
      $front_page_path => 'Home',
      'admin/structure/block-content' => 'Block types',
      'admin/structure/block-content/manage/basic' => 'Edit Bar',
    ]);
    \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();

    $this->drupalGet('block/add');
    $this->assertSession()->pageTextContains('Bar');
    $this->clickLink('Bar');
    // Verify that the original machine name was used in the URL.
    $this->assertSession()->addressEquals(Url::fromRoute('block_content.add_form', ['block_content_type' => 'basic']));

    // Remove the body field.
    $this->drupalGet('admin/structure/block-content/manage/basic/fields/block_content.basic.body/delete');
    $this->submitForm([], 'Delete');
    // Resave the settings for this type.
    $this->drupalGet('admin/structure/block-content/manage/basic');
    $this->submitForm([], 'Save');
    // Check that the body field doesn't exist.
    $this->drupalGet('block/add/basic');
    $this->assertEmpty($this->cssSelect('#edit-body-0-value'), 'Body field was not found.');
  }

  /**
   * Tests deleting a block type that still has content.
   */
  public function testBlockContentTypeDeletion(): void {
    // Now create an initial block-type.
    $this->createBlockContentType(['id' => 'basic'], TRUE);

    // Create a block type programmatically.
    $type = $this->createBlockContentType(['id' => 'foo']);

    $this->drupalLogin($this->adminUser);

    // Add a new block of this type.
    $block = $this->createBlockContent(FALSE, 'foo');
    // Attempt to delete the block type, which should not be allowed.
    $this->drupalGet('admin/structure/block-content/manage/' . $type->id() . '/delete');
    $this->assertSession()->pageTextContains($type->label() . ' is used by 1 content block on your site. You can not remove this block type until you have removed all of the ' . $type->label() . ' blocks.');
    $this->assertSession()->pageTextNotContains('This action cannot be undone.');

    // Delete the block.
    $block->delete();
    // Attempt to delete the block type, which should now be allowed.
    $this->drupalGet('admin/structure/block-content/manage/' . $type->id() . '/delete');
    $this->assertSession()->pageTextContains('Are you sure you want to delete the block type ' . $type->id() . '?');
    $this->assertSession()->pageTextContains('This action cannot be undone.');
  }

  /**
   * Tests that redirects work as expected when multiple block types exist.
   */
  public function testsBlockContentAddTypes(): void {
    // Now create an initial block-type.
    $this->createBlockContentType(['id' => 'basic'], TRUE);

    $this->drupalLogin($this->adminUser);
    // Create two block types programmatically.
    $this->createBlockContentType(['id' => 'foo']);
    $this->createBlockContentType(['id' => 'bar']);

    // Get the content block storage.
    $storage = $this->container
      ->get('entity_type.manager')
      ->getStorage('block_content');

    // Install all themes.
    $themes = ['olivero', 'stark', 'claro'];
    \Drupal::service('theme_installer')->install($themes);
    $theme_settings = $this->config('system.theme');
    foreach ($themes as $default_theme) {
      // Change the default theme.
      $theme_settings->set('default', $default_theme)->save();
      $this->drupalPlaceBlock('local_actions_block');

      // For each installed theme, go to its block page and test the redirects.
      foreach ($themes as $theme) {
        // Test that adding a block from the 'place blocks' form sends you to
        // the block configure form.
        $path = $theme == $default_theme ? 'admin/structure/block' : "admin/structure/block/list/$theme";
        $this->drupalGet($path);
        $this->clickLink('Place block');
        $this->clickLink('Add content block');
        $this->clickLink('foo');
        // Create a new block.
        $edit = ['info[0][value]' => $this->randomMachineName(8)];
        $this->submitForm($edit, 'Save and configure');
        $blocks = $storage->loadByProperties(['info' => $edit['info[0][value]']]);
        if (!empty($blocks)) {
          $block = reset($blocks);
          $this->assertSession()->addressEquals(Url::fromRoute('block.admin_add', ['plugin_id' => 'block_content:' . $block->uuid(), 'theme' => $theme]));
          $this->submitForm(['region' => 'content'], 'Save block');
          $this->assertSession()->addressEquals(Url::fromRoute('block.admin_display_theme', ['theme' => $theme], ['query' => ['block-placement' => $theme . '-' . Html::getClass($edit['info[0][value]'])]]));
        }
        else {
          $this->fail('Could not load created block.');
        }
      }
    }

    // Test that adding a block from the 'content blocks list' doesn't send you
    // to the block configure form.
    $this->drupalGet('admin/content/block');
    $this->clickLink('Add content block');
    $this->clickLink('foo');
    $edit = ['info[0][value]' => $this->randomMachineName(8)];
    $this->submitForm($edit, 'Save');
    $blocks = $storage->loadByProperties(['info' => $edit['info[0][value]']]);
    if (!empty($blocks)) {
      $this->assertSession()->addressEquals(Url::fromRoute('entity.block_content.collection'));
    }
    else {
      $this->fail('Could not load created block.');
    }
  }

}
