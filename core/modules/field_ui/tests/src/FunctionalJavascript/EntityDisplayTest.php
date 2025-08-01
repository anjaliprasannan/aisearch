<?php

declare(strict_types=1);

namespace Drupal\Tests\field_ui\FunctionalJavascript;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\entity_test\EntityTestHelper;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the UI for entity displays.
 */
#[Group('field_ui')]
class EntityDisplayTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['field_ui', 'entity_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $entity = EntityTest::create([
      'name' => 'The name for this entity',
      'field_test_text' => [
        ['value' => 'The field test text value'],
      ],
    ]);
    $entity->save();
    $this->drupalLogin($this->drupalCreateUser([
      'access administration pages',
      'view test entity',
      'administer entity_test content',
      'administer entity_test fields',
      'administer entity_test display',
      'administer entity_test form display',
      'view the administration theme',
    ]));
  }

  /**
   * Tests the use of regions for entity form displays.
   */
  public function testEntityForm(): void {
    $this->drupalGet('entity_test/manage/1/edit');
    $this->assertSession()->fieldExists('field_test_text[0][value]');

    $this->drupalGet('entity_test/structure/entity_test/form-display');
    $this->assertTrue($this->assertSession()->optionExists('fields[field_test_text][region]', 'content')->isSelected());
    $this->getSession()->getPage()->pressButton('Show row weights');
    $this->assertSession()->waitForElementVisible('css', '[name="fields[field_test_text][region]"]');
    $this->getSession()->getPage()->selectFieldOption('fields[field_test_text][region]', 'hidden');
    $this->assertTrue($this->assertSession()->optionExists('fields[field_test_text][region]', 'hidden')->isSelected());

    $this->submitForm([], 'Save');
    $this->assertSession()->pageTextContains('Your settings have been saved.');
    $this->assertTrue($this->assertSession()->optionExists('fields[field_test_text][region]', 'hidden')->isSelected());

    $this->drupalGet('entity_test/manage/1/edit');
    $this->assertSession()->fieldNotExists('field_test_text[0][value]');
  }

  /**
   * Tests the use of regions for entity view displays.
   */
  public function testEntityView(): void {
    $this->drupalGet('entity_test/1');
    $this->assertSession()->pageTextNotContains('The field test text value');

    $this->drupalGet('entity_test/structure/entity_test/display');
    $this->assertSession()->elementExists('css', '.region-content-message.region-empty');
    $this->getSession()->getPage()->pressButton('Show row weights');
    $this->assertSession()->waitForElementVisible('css', '[name="fields[field_test_text][region]"]');
    $this->assertTrue($this->assertSession()->optionExists('fields[field_test_text][region]', 'hidden')->isSelected());

    $this->getSession()->getPage()->selectFieldOption('fields[field_test_text][region]', 'content');
    $this->assertTrue($this->assertSession()->optionExists('fields[field_test_text][region]', 'content')->isSelected());

    $this->submitForm([], 'Save');
    $this->assertSession()->pageTextContains('Your settings have been saved.');
    $this->assertTrue($this->assertSession()->optionExists('fields[field_test_text][region]', 'content')->isSelected());

    $this->drupalGet('entity_test/1');
    $this->assertSession()->pageTextContains('The field test text value');
  }

  /**
   * Tests extra fields.
   */
  public function testExtraFields(): void {
    EntityTestHelper::createBundle('bundle_with_extra_fields');
    $this->drupalGet('entity_test/structure/bundle_with_extra_fields/display');
    $this->assertSession()->waitForElement('css', '.tabledrag-handle');
    $id = $this->getSession()->getPage()->find('css', '[name="form_build_id"]')->getValue();

    $extra_field_row = $this->getSession()->getPage()->find('css', '#display-extra-field');
    $disabled_region_row = $this->getSession()->getPage()->find('css', '.region-hidden-title');

    $extra_field_row->find('css', '.handle')->dragTo($disabled_region_row);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()
      ->waitForElement('css', "[name='form_build_id']:not([value='$id'])");

    $this->submitForm([], 'Save');
    $this->assertSession()->pageTextContains('Your settings have been saved.');
  }

}
