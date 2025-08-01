<?php

declare(strict_types=1);

namespace Drupal\Tests\media\FunctionalJavascript;

use Drupal\Component\Utility\Html;
use PHPUnit\Framework\Attributes\Group;

// cspell:ignore pastafazoul
/**
 * Tests the media type creation.
 */
#[Group('media')]
class MediaTypeCreationTest extends MediaJavascriptTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests the source field behavior on the add media type form.
   */
  public function testSourceChangeOnMediaTypeCreationForm(): void {
    $session = $this->getSession();
    $page = $session->getPage();
    $assert_session = $this->assertSession();

    $label = 'Type with Default Field';
    $mediaTypeMachineName = str_replace(' ', '_', strtolower($label));

    $this->drupalGet('admin/structure/media/add');

    // Fill in a label to the media type.
    $page->fillField('label', $label);
    $this->assertNotEmpty(
      $assert_session->waitForElementVisible('css', '.machine-name-value')
    );

    // Select the media source used by our media type.
    $assert_session->selectExists('Media source')->selectOption('test_different_displays');
    $this->assertNotEmpty(
      $assert_session->waitForElementVisible('css', 'fieldset[data-drupal-selector="edit-source-configuration"]')
    );

    // Change the media source.
    $assert_session->selectExists('Media source')->selectOption('test');
    $this->assertNotEmpty(
      $assert_session->waitForElement('css', 'fieldset[data-drupal-selector="edit-source-configuration"] .fieldset-wrapper .placeholder:contains("Text (plain)")')
    );

    $page->pressButton('Save');

    // Check that source can not be changed anymore.
    $this->drupalGet("admin/structure/media/manage/{$mediaTypeMachineName}");
    $assert_session->pageTextContains('The media source cannot be changed after the media type is created');
    $assert_session->fieldDisabled('Media source');
  }

  /**
   * Tests the media type creation form.
   */
  public function testMediaTypeCreationFormWithDefaultField(): void {
    $session = $this->getSession();
    $page = $session->getPage();
    $assert_session = $this->assertSession();

    $label = 'Type with Default Field';
    $mediaTypeMachineName = str_replace(' ', '_', strtolower($label));

    $this->drupalGet('admin/structure/media/add');

    // Select the media source used by our media type. Do this before setting
    // the label or machine name in order to guard against the regression in
    // https://www.drupal.org/project/drupal/issues/2557299.
    $assert_session->fieldExists('Media source');
    $assert_session->optionExists('Media source', 'test');
    $page->selectFieldOption('Media source', 'test');
    $result = $assert_session->waitForElementVisible('css', 'fieldset[data-drupal-selector="edit-source-configuration"]');
    $this->assertNotEmpty($result);

    // Fill in a label to the media type.
    $page->fillField('label', $label);
    // Wait for machine name generation. Default: waitUntilVisible(), does not
    // work properly.
    $session->wait(5000, "jQuery('.machine-name-value').text() === '{$mediaTypeMachineName}'");

    $page->pressButton('Save');

    // Check whether the source field was correctly created.
    $this->drupalGet("admin/structure/media/manage/{$mediaTypeMachineName}/fields");

    // Check 2nd column of first data row, to be machine name for field name.
    $assert_session->elementContains('xpath', '(//table[@id="field-overview"]//tr)[2]//td[2]', 'field_media_test');
    // Check 3rd column of first data row, to be correct field type.
    $assert_session->elementTextContains('xpath', '(//table[@id="field-overview"]//tr)[2]//td[3]', 'Text (plain)');

    // Check that the source field is correctly assigned to media type.
    $this->drupalGet("admin/structure/media/manage/{$mediaTypeMachineName}");

    $assert_session->pageTextContains('Test source field is used to store the essential information about the media item.');

    // Check that the plugin cannot be changed after it is set on type creation.
    $assert_session->fieldDisabled('Media source');
    $assert_session->pageTextContains('The media source cannot be changed after the media type is created.');

    // Check that the field map options are sorted alphabetically.
    // Source field should not be included.
    $options = $this->xpath('//select[@name="field_map[attribute_1]"]/option');
    $this->assertGreaterThanOrEqual(2, count($options));
    $this->assertSame('- Skip field -', $options[0]->getText());
    $this->assertSame('Name', $options[1]->getText());
    // It should not be possible to map the source field.
    $assert_session->optionNotExists('field_map[attribute_1]', 'Test source');

    // Open up the media add form and verify that the source field is right
    // after the name, and before the vertical tabs.
    $this->drupalGet("/media/add/$mediaTypeMachineName");

    // Get the form element, and its HTML representation.
    $form_selector = '#media-' . Html::cleanCssIdentifier($mediaTypeMachineName) . '-add-form';
    $form = $assert_session->elementExists('css', $form_selector);
    $form_html = $form->getOuterHtml();

    // The name field should come before the source field, which should itself
    // come before the vertical tabs.
    $name_field = $assert_session->fieldExists('Name', $form)->getOuterHtml();
    $test_source_field = $assert_session->fieldExists('Test source', $form)->getOuterHtml();
    $vertical_tabs = $assert_session->elementExists('css', '.vertical-tabs', $form)->getOuterHtml();
    $date_field = $assert_session->fieldExists('Date', $form)->getOuterHtml();
    $published_checkbox = $assert_session->fieldExists('Published', $form)->getOuterHtml();
    $this->assertGreaterThan(strpos($form_html, $name_field), strpos($form_html, $test_source_field));
    $this->assertGreaterThan(strpos($form_html, $test_source_field), strpos($form_html, $vertical_tabs));
    // The "Published" checkbox should be the last element.
    $this->assertGreaterThan(strpos($form_html, $date_field), strpos($form_html, $published_checkbox));

    // Check that a new type with the same machine name cannot be created.
    $this->drupalGet('admin/structure/media/add');
    $page->fillField('label', $label);
    $session->wait(5000, "jQuery('.machine-name-value').text() === '{$mediaTypeMachineName}'");
    $page->selectFieldOption('Media source', 'test');
    $assert_session->assertWaitOnAjaxRequest();
    $page->pressButton('Save');
    $assert_session->pageTextContains('The machine-readable name is already in use. It must be unique.');
  }

  /**
   * Tests creation of media type, reusing an existing source field.
   */
  public function testMediaTypeCreationReuseSourceField(): void {
    $session = $this->getSession();
    $page = $session->getPage();
    $assert_session = $this->assertSession();

    // Create a new media type, which should create a new field we can reuse.
    $this->drupalGet('/admin/structure/media/add');
    // Choose the source plugin before setting the label and machine name.
    $page->selectFieldOption('Media source', 'test');
    $result = $assert_session->waitForElementVisible('css', 'fieldset[data-drupal-selector="edit-source-configuration"]');
    $this->assertNotEmpty($result);
    $page->fillField('label', 'Pastafazoul');
    $session->wait(5000, "jQuery('.machine-name-value').text() === 'pastafazoul'");
    $page->pressButton('Save');

    $label = 'Type reusing Default Field';
    $mediaTypeMachineName = str_replace(' ', '_', strtolower($label));

    $this->drupalGet('admin/structure/media/add');

    // Select the media source used by our media type. Do this before setting
    // the label and machine name.
    $assert_session->fieldExists('Media source');
    $assert_session->optionExists('Media source', 'test');
    $page->selectFieldOption('Media source', 'test');
    $result = $assert_session->waitForElementVisible('css', 'fieldset[data-drupal-selector="edit-source-configuration"]');
    $this->assertNotEmpty($result);
    // Select the existing field for re-use.
    $page->selectFieldOption('source_configuration[source_field]', 'field_media_test');

    // Fill in a label to the media type.
    $page->fillField('label', $label);

    // Wait for machine name generation. Default: waitUntilVisible(), does not
    // work properly.
    $session->wait(5000, "jQuery('.machine-name-value').text() === '{$mediaTypeMachineName}'");

    $page->pressButton('Save');

    // Check that no new fields were created.
    $this->drupalGet("admin/structure/media/manage/{$mediaTypeMachineName}/fields");
    // The reused field should be present...
    $assert_session->pageTextContains('field_media_test');
    // ...not a new, unique one.
    $assert_session->pageTextNotContains('field_media_test_1');
  }

}
