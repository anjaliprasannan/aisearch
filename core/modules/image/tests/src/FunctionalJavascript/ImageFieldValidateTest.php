<?php

declare(strict_types=1);

namespace Drupal\Tests\image\FunctionalJavascript;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests validation functions such as min/max dimensions.
 */
#[Group('image')]
class ImageFieldValidateTest extends ImageFieldTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests the validation message is displayed only once for ajax uploads.
   */
  public function testAJAXValidationMessage(): void {
    $field_name = $this->randomMachineName();
    $this->createImageField($field_name, 'node', 'article', ['cardinality' => -1]);

    $this->drupalGet('node/add/article');
    /** @var \Drupal\file\FileInterface[] $text_files */
    $text_files = $this->drupalGetTestFiles('text');
    $text_file = reset($text_files);

    $field = $this->getSession()->getPage()->findField('files[' . $field_name . '_0][]');
    $field->attachFile($this->container->get('file_system')->realpath($text_file->uri));
    $this->assertSession()->waitForElement('css', '.messages--error');

    // Verify that Ajax validation messages are displayed only once.
    $this->assertSession()->elementsCount('xpath', '//div[contains(@class, "messages--error")]', 1);
  }

  /**
   * Tests that image field validation works with other form submit handlers.
   */
  public function testFriendlyAjaxValidation(): void {
    // Add a custom field to the Article content type that contains an AJAX
    // handler on a select field.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_dummy_select',
      'type' => 'image_module_test_dummy_ajax',
      'entity_type' => 'node',
      'cardinality' => 1,
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'entity_type' => 'node',
      'bundle' => 'article',
      'field_name' => 'field_dummy_select',
      'label' => 'Dummy select',
    ])->save();

    \Drupal::entityTypeManager()
      ->getStorage('entity_form_display')
      ->load('node.article.default')
      ->setComponent(
      'field_dummy_select',
      [
        'type' => 'image_module_test_dummy_ajax_widget',
        'weight' => 1,
      ])
      ->save();

    // Then, add an image field.
    $this->createImageField('field_dummy_image', 'node', 'article');

    // Open an article and trigger the AJAX handler.
    $this->drupalGet('node/add/article');
    $id = $this->getSession()->getPage()->find('css', '[name="form_build_id"]')->getValue();
    $field = $this->getSession()->getPage()->findField('field_dummy_select[select_widget]');
    $field->setValue('bam');
    // Make sure that the operation did not end with an exception.
    $this->assertSession()->waitForElement('css', "[name='form_build_id']:not([value='$id'])");
  }

}
