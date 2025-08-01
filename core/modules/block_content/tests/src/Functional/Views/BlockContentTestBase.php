<?php

declare(strict_types=1);

namespace Drupal\Tests\block_content\Functional\Views;

use Drupal\Tests\block_content\Traits\BlockContentCreationTrait;
use Drupal\Tests\views\Functional\ViewTestBase;

/**
 * Base class for all block_content tests.
 */
abstract class BlockContentTestBase extends ViewTestBase {

  use BlockContentCreationTrait {
    createBlockContent as baseCreateBlockContent;
    createBlockContentType as baseCreateBlockContentType;
  }

  /**
   * Admin user.
   *
   * @var object
   */
  protected $adminUser;

  /**
   * Permissions to grant admin user.
   *
   * @var array
   */
  protected $permissions = [
    'administer blocks',
    'administer block content',
    'access block library',
  ];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'block_content',
    'block_content_test_views',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE, $modules = ['block_content_test_views']): void {
    parent::setUp($import_test_views, $modules);
    // Ensure the basic bundle exists. This is provided by the standard profile.
    $this->createBlockContentType(['id' => 'basic']);

    $this->adminUser = $this->drupalCreateUser($this->permissions);
  }

  /**
   * Creates a content block.
   *
   * @param array $values
   *   (optional) The values for the block_content entity.
   *
   * @return \Drupal\block_content\Entity\BlockContent
   *   Created content block.
   */
  protected function createBlockContent(array $values = []) {
    $status = 0;
    $values += [
      'info' => $this->randomMachineName(),
      'type' => 'basic',
      'langcode' => 'en',
    ];
    if ($block_content = $this->baseCreateBlockContent(save: FALSE, values: $values)) {
      $status = $block_content->save();
    }
    $this->assertEquals(SAVED_NEW, $status, "Created block content {$block_content->label()}.");
    return $block_content;
  }

  /**
   * Creates a block type (bundle).
   *
   * @param array $values
   *   An array of settings to change from the defaults.
   *
   * @return \Drupal\block_content\Entity\BlockContentType
   *   Created block type.
   */
  protected function createBlockContentType(array $values = []) {
    return $this->baseCreateBlockContentType($values, TRUE);
  }

}
