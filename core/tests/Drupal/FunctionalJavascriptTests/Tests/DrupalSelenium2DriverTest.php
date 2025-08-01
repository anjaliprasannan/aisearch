<?php

declare(strict_types=1);

namespace Drupal\FunctionalJavascriptTests\Tests;

use Behat\Mink\Driver\Selenium2Driver;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\FunctionalJavascriptTests\DrupalSelenium2Driver;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\file\Functional\FileFieldCreationTrait;
use Drupal\Tests\TestFileCreationTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the DrupalSelenium2Driver methods.
 */
#[CoversClass(DrupalSelenium2Driver::class)]
#[Group('javascript')]
class DrupalSelenium2DriverTest extends WebDriverTestBase {

  use TestFileCreationTrait;
  use FileFieldCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['file', 'field_ui', 'entity_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $storage_settings = ['cardinality' => 3];
    $this->createFileField('field_file', 'entity_test', 'entity_test', $storage_settings);
    $this->drupalLogin($this->drupalCreateUser([
      'administer entity_test content',
      'access content',
    ]));
  }

  /**
   * Tests uploading remote files.
   */
  public function testGetRemoteFilePath(): void {
    $web_driver = $this->getSession()->getDriver();
    $this->assertInstanceOf(Selenium2Driver::class, $web_driver);

    $file_system = \Drupal::service('file_system');
    $entity = EntityTest::create();
    $entity->save();

    $files = array_slice($this->getTestFiles('text'), 0, 3);
    $real_paths = [];
    foreach ($files as $file) {
      $real_paths[] = $file_system->realpath($file->uri);
    }
    $remote_paths = [];
    foreach ($real_paths as $path) {
      $remote_paths[] = $web_driver->uploadFileAndGetRemoteFilePath($path);
    }

    // Tests that uploading multiple remote files works with remote path.
    $this->drupalGet($entity->toUrl('edit-form'));
    $multiple_field = $this->assertSession()->elementExists('xpath', '//input[@multiple]');
    $multiple_field->setValue(implode("\n", $remote_paths));
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->findButton('Save')->click();
    $entity = EntityTest::load($entity->id());
    $this->assertCount(3, $entity->field_file);
  }

}
