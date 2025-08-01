<?php

declare(strict_types=1);

namespace Drupal\Tests\link\Functional;

use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\link\LinkItemInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\BrowserTestBase;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\Traits\Core\PathAliasTestTrait;

/**
 * Tests link field widgets and formatters.
 *
 * @group link
 * @group #slow
 */
class LinkFieldTest extends BrowserTestBase {

  use PathAliasTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
    'link',
    'node',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'link_test_theme';

  /**
   * A field to use in this test class.
   *
   * @var \Drupal\field\Entity\FieldStorageConfig
   */
  protected $fieldStorage;

  /**
   * The instance used in this test class.
   *
   * @var \Drupal\field\Entity\FieldConfig
   */
  protected $field;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalLogin($this->drupalCreateUser([
      'view test entity',
      'administer entity_test content',
      'link to any page',
    ]));
  }

  /**
   * Tests the functionality and rendering of the link field.
   *
   * This is being as one to avoid multiple Drupal install.
   */
  public function testLinkField(): void {
    $this->doTestURLValidation();
    $this->doTestLinkTitle();
    $this->doTestEditNonNodeEntityLink();
  }

  /**
   * Tests link field URL validation.
   */
  protected function doTestURLValidation(): void {
    $field_name = $this->randomMachineName();
    // Create a field with settings to validate.
    $this->fieldStorage = FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'entity_test',
      'type' => 'link',
    ]);
    $this->fieldStorage->save();
    $this->field = FieldConfig::create([
      'field_storage' => $this->fieldStorage,
      'bundle' => 'entity_test',
      'settings' => [
        'title' => DRUPAL_DISABLED,
        'link_type' => LinkItemInterface::LINK_GENERIC,
      ],
    ]);
    $this->field->save();
    /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $display_repository */
    $display_repository = \Drupal::service('entity_display.repository');
    $display_repository->getFormDisplay('entity_test', 'entity_test')
      ->setComponent($field_name, [
        'type' => 'link_default',
        'settings' => [
          'placeholder_url' => 'http://example.com',
        ],
      ])
      ->save();
    $display_repository->getViewDisplay('entity_test', 'entity_test', 'full')
      ->setComponent($field_name, [
        'type' => 'link',
      ])
      ->save();

    // Display creation form.
    $this->drupalGet('entity_test/add');
    $this->assertSession()->fieldValueEquals("{$field_name}[0][uri]", '');
    $this->assertSession()->responseContains('placeholder="http://example.com"');

    // Create a path alias.
    $this->createPathAlias('/admin', '/a/path/alias');

    // Create a node to test the link widget.
    $node = $this->drupalCreateNode();

    $restricted_node = $this->drupalCreateNode(['status' => NodeInterface::NOT_PUBLISHED]);

    // Define some valid URLs (keys are the entered values, values are the
    // strings displayed to the user).
    $valid_external_entries = [
      'http://www.example.com/' => 'http://www.example.com/',
      // Strings within parenthesis without leading space char.
      'http://www.example.com/strings_(string_within_parenthesis)' => 'http://www.example.com/strings_(string_within_parenthesis)',
      // Numbers within parenthesis without leading space char.
      'http://www.example.com/numbers_(9999)' => 'http://www.example.com/numbers_(9999)',
    ];
    $valid_internal_entries = [
      '/entity_test/add' => '/entity_test/add',
      '/a/path/alias' => '/a/path/alias',

      // Front page, with query string and fragment.
      '/' => '&lt;front&gt;',
      '/?example=llama' => '&lt;front&gt;?example=llama',
      '/#example' => '&lt;front&gt;#example',

      // Trailing spaces should be ignored.
      '/ ' => '&lt;front&gt;',
      '/path with spaces ' => '/path with spaces',

      // @todo '<front>' is valid input for BC reasons, may be removed by
      //   https://www.drupal.org/node/2421941
      '<front>' => '&lt;front&gt;',
      '<front>#example' => '&lt;front&gt;#example',
      '<front>?example=llama' => '&lt;front&gt;?example=llama',

      // Text-only links.
      '<nolink>' => '&lt;nolink&gt;',
      'route:<nolink>' => '&lt;nolink&gt;',
      '<none>' => '&lt;none&gt;',
      '<button>' => '&lt;button&gt;',

      // Query string and fragment.
      '?example=llama' => '?example=llama',
      '#example' => '#example',

      // Complex query string. Similar to facet links.
      '?a[]=1&a[]=2'     => '?a[]=1&amp;a[]=2',
      '?b[0]=1&b[1]=2'   => '?b[0]=1&amp;b[1]=2',
      '?c[]=1&d=3&c[]=2' => '?c[]=1&amp;d=3&amp;c[]=2',
      '?e[f][g]=h'       => '?e[f][g]=h',
      '?i[j[k]]=l'       => '?i[j[k]]=l',
      '?x=1&x=2'         => '?x=1&amp;x=2',
      '?z[0]=1&z[0]=2'   => '?z[0]=1&amp;z[0]=2',

      // Entity reference autocomplete value.
      $node->label() . ' (1)' => $node->label() . ' (1)',
      // Entity URI displayed as ER autocomplete value when displayed in a form.
      'entity:node/1' => $node->label() . ' (1)',
      // URI for an entity that exists, but is not accessible by the user.
      'entity:node/' . $restricted_node->id() => '- Restricted access - (' . $restricted_node->id() . ')',
      // URI for an entity that doesn't exist, but with a valid ID.
      'entity:user/999999' => 'entity:user/999999',
    ];

    // Define some invalid URLs.
    $validation_error_1 = "The path '@link_path' is invalid.";
    $validation_error_2 = 'Manually entered paths should start with one of the following characters: / ? #';
    $validation_error_3 = "The path '@link_path' is inaccessible.";
    $invalid_external_entries = [
      // Invalid protocol.
      'invalid://not-a-valid-protocol' => $validation_error_1,
      // Missing host name.
      'http://' => $validation_error_1,
    ];
    $invalid_internal_entries = [
      'no-leading-slash' => $validation_error_2,
      'entity:non_existing_entity_type/yar' => $validation_error_1,
      // URI for an entity that doesn't exist, with an invalid ID.
      'entity:user/invalid-parameter' => $validation_error_1,
    ];

    // Test external and internal URLs for
    // 'link_type' = LinkItemInterface::LINK_GENERIC.
    $this->assertValidEntries($field_name, $valid_external_entries + $valid_internal_entries);
    $this->assertInvalidEntries($field_name, $invalid_external_entries + $invalid_internal_entries);

    // Test external URLs for 'link_type' = LinkItemInterface::LINK_EXTERNAL.
    $this->field->setSetting('link_type', LinkItemInterface::LINK_EXTERNAL);
    $this->field->save();
    $this->assertValidEntries($field_name, $valid_external_entries);
    $this->assertInvalidEntries($field_name, $valid_internal_entries + $invalid_external_entries);

    // Test external URLs for 'link_type' = LinkItemInterface::LINK_INTERNAL.
    $this->field->setSetting('link_type', LinkItemInterface::LINK_INTERNAL);
    $this->field->save();
    $this->assertValidEntries($field_name, $valid_internal_entries);
    $this->assertInvalidEntries($field_name, $valid_external_entries + $invalid_internal_entries);

    // Ensure that users with 'link to any page', don't apply access checking.
    $this->drupalLogin($this->drupalCreateUser([
      'view test entity',
      'administer entity_test content',
    ]));
    $this->assertValidEntries($field_name, ['/entity_test/add' => '/entity_test/add']);
    $this->assertInValidEntries($field_name, ['/admin' => $validation_error_3]);
  }

  /**
   * Asserts that valid URLs can be submitted.
   *
   * @param string $field_name
   *   The field name.
   * @param array $valid_entries
   *   An array of valid URL entries.
   *
   * @internal
   */
  protected function assertValidEntries(string $field_name, array $valid_entries): void {
    foreach ($valid_entries as $uri => $string) {
      $edit = [
        "{$field_name}[0][uri]" => $uri,
      ];
      $this->drupalGet('entity_test/add');
      $this->submitForm($edit, 'Save');
      preg_match('|entity_test/manage/(\d+)|', $this->getUrl(), $match);
      $id = $match[1];
      $this->assertSession()->statusMessageContains('entity_test ' . $id . ' has been created.', 'status');
      $this->assertSession()->responseContains('"' . $string . '"');
    }
  }

  /**
   * Asserts that invalid URLs cannot be submitted.
   *
   * @param string $field_name
   *   The field name.
   * @param array $invalid_entries
   *   An array of invalid URL entries.
   *
   * @internal
   */
  protected function assertInvalidEntries(string $field_name, array $invalid_entries): void {
    foreach ($invalid_entries as $invalid_value => $error_message) {
      $edit = [
        "{$field_name}[0][uri]" => $invalid_value,
      ];
      $this->drupalGet('entity_test/add');
      $this->submitForm($edit, 'Save');
      $this->assertSession()->responseContains(strtr($error_message, ['@link_path' => $invalid_value]));
    }
  }

  /**
   * Tests the link title settings of a link field.
   */
  protected function doTestLinkTitle(): void {
    $field_name = $this->randomMachineName();
    // Create a field with settings to validate.
    $this->fieldStorage = FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'entity_test',
      'type' => 'link',
    ]);
    $this->fieldStorage->save();
    $this->field = FieldConfig::create([
      'field_storage' => $this->fieldStorage,
      'bundle' => 'entity_test',
      'label' => 'Read more about this entity',
      'settings' => [
        'title' => DRUPAL_OPTIONAL,
        'link_type' => LinkItemInterface::LINK_GENERIC,
      ],
    ]);
    $this->field->save();
    /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $display_repository */
    $display_repository = \Drupal::service('entity_display.repository');
    $display_repository->getFormDisplay('entity_test', 'entity_test')
      ->setComponent($field_name, [
        'type' => 'link_default',
        'settings' => [
          'placeholder_url' => 'http://example.com',
          'placeholder_title' => 'Enter the text for this link',
        ],
      ])
      ->save();
    $display_repository->getViewDisplay('entity_test', 'entity_test', 'full')
      ->setComponent($field_name, [
        'type' => 'link',
        'label' => 'hidden',
      ])
      ->save();

    // Verify that the link text field works according to the field setting.
    foreach ([DRUPAL_DISABLED, DRUPAL_REQUIRED, DRUPAL_OPTIONAL] as $title_setting) {
      // Update the link title field setting.
      $this->field->setSetting('title', $title_setting);
      $this->field->save();

      // Display creation form.
      $this->drupalGet('entity_test/add');
      // Assert label is shown.
      $this->assertSession()->pageTextContains('Read more about this entity');
      $this->assertSession()->fieldValueEquals("{$field_name}[0][uri]", '');
      $this->assertSession()->responseContains('placeholder="http://example.com"');

      if ($title_setting === DRUPAL_DISABLED) {
        $this->assertSession()->fieldNotExists("{$field_name}[0][title]");
        $this->assertSession()->responseNotContains('placeholder="Enter the text for this link"');
      }
      else {
        $this->assertSession()->responseContains('placeholder="Enter the text for this link"');

        $this->assertSession()->fieldValueEquals("{$field_name}[0][title]", '');
        if ($title_setting === DRUPAL_OPTIONAL) {
          // Verify that the URL is required, if the link text is non-empty.
          $edit = [
            "{$field_name}[0][title]" => 'Example',
          ];
          $this->submitForm($edit, 'Save');
          $this->assertSession()->statusMessageContains('The URL field is required when the Link text field is specified.', 'error');
        }
        if ($title_setting === DRUPAL_REQUIRED) {
          // Verify that the link text is required, if the URL is non-empty.
          $edit = [
            "{$field_name}[0][uri]" => 'http://www.example.com',
          ];
          $this->submitForm($edit, 'Save');
          $this->assertSession()->statusMessageContains('Link text field is required if there is URL input.', 'error');

          // Verify that the link text is not required, if the URL is empty.
          $edit = [
            "{$field_name}[0][uri]" => '',
          ];
          $this->submitForm($edit, 'Save');
          $this->assertSession()->statusMessageNotContains('Link text field is required.');

          // Verify that a URL and link text meets requirements.
          $this->drupalGet('entity_test/add');
          $edit = [
            "{$field_name}[0][uri]" => 'http://www.example.com',
            "{$field_name}[0][title]" => 'Example',
          ];
          $this->submitForm($edit, 'Save');
          $this->assertSession()->statusMessageNotContains('Link text field is required.');
        }
      }
    }

    // Verify that a link without link text is rendered using the URL as text.
    $value = 'http://www.example.com/';
    $edit = [
      "{$field_name}[0][uri]" => $value,
      "{$field_name}[0][title]" => '',
    ];
    $this->submitForm($edit, 'Save');
    preg_match('|entity_test/manage/(\d+)|', $this->getUrl(), $match);
    $id = $match[1];
    $this->assertSession()->statusMessageContains('entity_test ' . $id . ' has been created.', 'status');

    $output = $this->renderTestEntity($id);
    $expected_link = (string) Link::fromTextAndUrl($value, Url::fromUri($value))->toString();
    $this->assertStringContainsString($expected_link, $output);

    // Verify that a link with text is rendered using the link text.
    $title = $this->randomMachineName();
    $edit = [
      "{$field_name}[0][title]" => $title,
    ];
    $this->drupalGet("entity_test/manage/{$id}/edit");
    $this->submitForm($edit, 'Save');
    $this->assertSession()->statusMessageContains('entity_test ' . $id . ' has been updated.', 'status');

    $output = $this->renderTestEntity($id);
    $expected_link = (string) Link::fromTextAndUrl($title, Url::fromUri($value))->toString();
    $this->assertStringContainsString($expected_link, $output);
  }

  /**
   * Tests editing a link to a non-node entity.
   */
  protected function doTestEditNonNodeEntityLink(): void {

    $entity_type_manager = \Drupal::entityTypeManager();
    $entity_test_storage = $entity_type_manager->getStorage('entity_test');

    // Create a field with settings to validate.
    $this->fieldStorage = FieldStorageConfig::create([
      'field_name' => 'field_link',
      'entity_type' => 'entity_test',
      'type' => 'link',
      'cardinality' => 1,
    ]);
    $this->fieldStorage->save();
    FieldConfig::create([
      'field_storage' => $this->fieldStorage,
      'label' => 'Read more about this entity',
      'bundle' => 'entity_test',
      'settings' => [
        'title' => DRUPAL_OPTIONAL,
      ],
    ])->save();

    $entity_type_manager
      ->getStorage('entity_form_display')
      ->load('entity_test.entity_test.default')
      ->setComponent('field_link', [
        'type' => 'link_default',
      ])
      ->save();

    // Create a node and a test entity to have a possibly valid reference for
    // both. Create another test entity that references the first test entity.
    $entity_test_link = $entity_test_storage->create(['name' => 'correct link target']);
    $entity_test_link->save();

    // Create a node with the same ID as the test entity to ensure that the link
    // doesn't match incorrectly.
    $this->drupalCreateNode(['title' => 'wrong link target']);

    $correct_link = 'entity:entity_test/' . $entity_test_link->id();
    $entity_test = $entity_test_storage->create([
      'name' => 'correct link target',
      'field_link' => $correct_link,
    ]);
    $entity_test->save();

    // Edit the entity and save it, verify the correct link is kept and not
    // changed to point to a node. Currently, widget does not support non-node
    // autocomplete and therefore must show the link unaltered.
    $this->drupalGet($entity_test->toUrl('edit-form'));
    $this->assertSession()->fieldValueEquals('field_link[0][uri]', $correct_link);
    $this->submitForm([], 'Save');

    $entity_test_storage->resetCache();
    $entity_test = $entity_test_storage->load($entity_test->id());

    $this->assertEquals($correct_link, $entity_test->get('field_link')->uri);
  }

  /**
   * Renders a test_entity and returns the output.
   *
   * @param int $id
   *   The test_entity ID to render.
   * @param string $view_mode
   *   (optional) The view mode to use for rendering.
   * @param bool $reset
   *   (optional) Whether to reset the entity_test storage cache. Defaults to
   *   TRUE to simplify testing.
   *
   * @return string
   *   The rendered HTML output.
   */
  protected function renderTestEntity($id, $view_mode = 'full', $reset = TRUE): string {
    if ($reset) {
      $this->container->get('entity_type.manager')->getStorage('entity_test')->resetCache([$id]);
    }
    $entity = EntityTest::load($id);
    $display = \Drupal::service('entity_display.repository')
      ->getViewDisplay($entity->getEntityTypeId(), $entity->bundle(), $view_mode);
    $content = $display->build($entity);
    $output = \Drupal::service('renderer')->renderRoot($content);
    return (string) $output;
  }

  /**
   * Test link widget exception handled if link uri value is invalid.
   */
  public function testLinkWidgetCaughtExceptionEditingInvalidUrl(): void {
    $field_name = $this->randomMachineName();
    $this->fieldStorage = FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'entity_test',
      'type' => 'link',
      'cardinality' => 1,
    ]);
    $this->fieldStorage->save();
    FieldConfig::create([
      'field_storage' => $this->fieldStorage,
      'label' => 'Link',
      'bundle' => 'entity_test',
      'settings' => [
        'title' => DRUPAL_OPTIONAL,
        'link_type' => LinkItemInterface::LINK_GENERIC,
      ],
    ])->save();

    $entityTypeManager = $this->container->get('entity_type.manager');
    $entityTypeManager
      ->getStorage('entity_form_display')
      ->load('entity_test.entity_test.default')
      ->setComponent($field_name, [
        'type' => 'link_default',
      ])
      ->save();

    $entityTypeManager
      ->getStorage('entity_view_display')
      ->create([
        'targetEntityType' => 'entity_test',
        'bundle' => 'entity_test',
        'mode' => 'full',
        'status' => TRUE,
      ])
      ->setComponent($field_name, [
        'type' => 'link',
      ])
      ->save();

    // Entities can be saved without validation, for example via migration.
    // Link fields may contain invalid uris such as external URLs without
    // scheme.
    $invalidUri = 'www.example.com';
    $invalidLinkUrlEntity = $entityTypeManager
      ->getStorage('entity_test')
      ->create([
        'name' => 'Test entity with invalid link URL',
        $field_name => ['uri' => $invalidUri],
      ]);
    $invalidLinkUrlEntity->save();

    // If a user without 'link to any page' permission edits an entity, widget
    // checks access by converting uri to Url object, which will throw an
    // InvalidArgumentException if uri is invalid.
    $this->drupalLogin($this->drupalCreateUser([
      'view test entity',
      'administer entity_test content',
    ]));
    $this->drupalGet("/entity_test/manage/{$invalidLinkUrlEntity->id()}/edit");
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldValueEquals("{$field_name}[0][uri]", $invalidUri);
  }

}
