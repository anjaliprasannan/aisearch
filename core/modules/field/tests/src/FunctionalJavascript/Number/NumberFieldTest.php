<?php

declare(strict_types=1);

namespace Drupal\Tests\field\FunctionalJavascript\Number;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\node\Entity\Node;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the numeric field widget.
 */
#[Group('field')]
class NumberFieldTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'entity_test', 'field_ui'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalLogin($this->drupalCreateUser([
      'view test entity',
      'administer entity_test content',
      'administer content types',
      'administer node fields',
      'administer node display',
      'bypass node access',
      'administer entity_test fields',
    ]));
  }

  /**
   * Tests default formatter behavior.
   */
  public function testNumberFormatter(): void {
    $type = $this->randomMachineName();
    $float_field = $this->randomMachineName();
    $integer_field = $this->randomMachineName();
    $thousand_separators = ['', '.', ',', ' ', chr(8201), "'"];
    $decimal_separators = ['.', ','];
    $prefix = $this->randomMachineName();
    $suffix = $this->randomMachineName();
    $random_float = rand(0, pow(10, 6));
    $random_integer = rand(0, pow(10, 6));
    $assert_session = $this->assertSession();

    // Create a content type containing float and integer fields.
    $this->drupalCreateContentType(['type' => $type]);

    FieldStorageConfig::create([
      'field_name' => $float_field,
      'entity_type' => 'node',
      'type' => 'float',
    ])->save();

    FieldStorageConfig::create([
      'field_name' => $integer_field,
      'entity_type' => 'node',
      'type' => 'integer',
    ])->save();

    FieldConfig::create([
      'field_name' => $float_field,
      'entity_type' => 'node',
      'bundle' => $type,
      'settings' => [
        'prefix' => $prefix,
        'suffix' => $suffix,
      ],
    ])->save();

    FieldConfig::create([
      'field_name' => $integer_field,
      'entity_type' => 'node',
      'bundle' => $type,
      'settings' => [
        'prefix' => $prefix,
        'suffix' => $suffix,
      ],
    ])->save();

    \Drupal::service('entity_display.repository')->getFormDisplay('node', $type, 'default')
      ->setComponent($float_field, [
        'type' => 'number',
        'settings' => [
          'placeholder' => '0.00',
        ],
      ])
      ->setComponent($integer_field, [
        'type' => 'number',
        'settings' => [
          'placeholder' => '0.00',
        ],
      ])
      ->save();

    \Drupal::service('entity_display.repository')->getViewDisplay('node', $type)
      ->setComponent($float_field, [
        'type' => 'number_decimal',
      ])
      ->setComponent($integer_field, [
        'type' => 'number_unformatted',
      ])
      ->save();

    // Create a node to test formatters.
    $node = Node::create([
      'type' => $type,
      'title' => $this->randomMachineName(),
      $float_field => ['value' => $random_float],
      $integer_field => ['value' => $random_integer],
    ]);
    $node->save();

    // Go to manage display page.
    $this->drupalGet("admin/structure/types/manage/$type/display");

    // Configure number_decimal formatter for the 'float' field type.
    $thousand_separator = $thousand_separators[array_rand($thousand_separators)];
    $decimal_separator = $decimal_separators[array_rand($decimal_separators)];
    $scale = rand(0, 10);

    $page = $this->getSession()->getPage();
    $page->pressButton("{$float_field}_settings_edit");
    $assert_session->waitForElement('css', '.ajax-new-content');
    $edit = [
      "fields[{$float_field}][settings_edit_form][settings][prefix_suffix]" => TRUE,
      "fields[{$float_field}][settings_edit_form][settings][scale]" => $scale,
      "fields[{$float_field}][settings_edit_form][settings][decimal_separator]" => $decimal_separator,
      "fields[{$float_field}][settings_edit_form][settings][thousand_separator]" => $thousand_separator,
    ];
    foreach ($edit as $name => $value) {
      $page->fillField($name, $value);
    }
    $page->pressButton("{$float_field}_plugin_settings_update");
    $assert_session->waitForElement('css', '.field-plugin-summary-cell > .ajax-new-content');
    $this->submitForm([], 'Save');

    // Check number_decimal and number_unformatted formatters behavior.
    $this->drupalGet('node/' . $node->id());
    $float_formatted = number_format($random_float, $scale, $decimal_separator, $thousand_separator);
    $this->assertSession()->responseContains("$prefix$float_formatted$suffix");
    $this->assertSession()->responseContains((string) $random_integer);

    // Configure the number_decimal formatter.
    \Drupal::service('entity_display.repository')->getViewDisplay('node', $type)
      ->setComponent($integer_field, [
        'type' => 'number_integer',
      ])
      ->save();
    $this->drupalGet("admin/structure/types/manage/$type/display");

    $thousand_separator = $thousand_separators[array_rand($thousand_separators)];

    $page = $this->getSession()->getPage();
    $page->pressButton("{$integer_field}_settings_edit");
    $assert_session->waitForElement('css', '.ajax-new-content');
    $edit = [
      "fields[{$integer_field}][settings_edit_form][settings][prefix_suffix]" => FALSE,
      "fields[{$integer_field}][settings_edit_form][settings][thousand_separator]" => $thousand_separator,
    ];
    foreach ($edit as $name => $value) {
      $page->fillField($name, $value);
    }
    $page->pressButton("{$integer_field}_plugin_settings_update");
    $assert_session->waitForElement('css', '.field-plugin-summary-cell > .ajax-new-content');
    $this->submitForm([], 'Save');

    // Check number_integer formatter behavior.
    $this->drupalGet('node/' . $node->id());

    $integer_formatted = number_format($random_integer, 0, '', $thousand_separator);
    $this->assertSession()->responseContains($integer_formatted);
  }

}
