<?php

declare(strict_types=1);

namespace Drupal\Tests\file\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\file\Functional\FileFieldCreationTrait;
use Drupal\Tests\TestFileCreationTrait;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests file field validation functions.
 *
 * Values validated include the file type, max file size, max size per node,
 * and whether the field is required.
 */
#[Group('file')]
class FileFieldValidateTest extends WebDriverTestBase {

  use FileFieldCreationTrait;
  use TestFileCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'file'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests the validation message is displayed only once for ajax uploads.
   */
  public function testAjaxValidationMessage(): void {
    $field_name = $this->randomMachineName();
    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);
    $this->createFileField($field_name, 'node', 'article', [], ['file_extensions' => 'txt']);

    $this->drupalLogin($this->drupalCreateUser([
      'access content',
      'create article content',
    ]));

    $page = $this->getSession()->getPage();
    $this->drupalGet('node/add/article');
    $image_file = current($this->getTestFiles('image'));
    $image_path = $this->container->get('file_system')->realpath($image_file->uri);
    $page->attachFileToField('files[' . $field_name . '_0]', $image_path);
    $elements = $page->waitFor(10, function () use ($page) {
      return $page->findAll('css', '.messages--error');
    });
    $this->assertCount(1, $elements, 'Ajax validation messages are displayed once.');
  }

}
