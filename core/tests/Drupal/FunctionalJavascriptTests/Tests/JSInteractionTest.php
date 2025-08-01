<?php

declare(strict_types=1);

namespace Drupal\FunctionalJavascriptTests\Tests;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\Group;
use WebDriver\Exception;

/**
 * Tests fault tolerant interactions.
 */
#[Group('javascript')]
class JSInteractionTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'js_interaction_test',
  ];

  /**
   * Assert an exception is thrown when the blocker element is never removed.
   */
  public function testNotClickable(): void {
    $this->expectException(Exception::class);
    $this->drupalGet('/js_interaction_test');
    $this->assertSession()->elementExists('named', ['link', 'Target link'])->click();
  }

  /**
   * Assert an exception is thrown when the field is never enabled.
   */
  public function testFieldValueNotSettable(): void {
    $this->expectException(Exception::class);
    $this->drupalGet('/js_interaction_test');
    $this->assertSession()->fieldExists('target_field')->setValue('Test');
  }

  /**
   * Assert no exception is thrown when elements become interactive.
   */
  public function testElementsInteraction(): void {
    $this->drupalGet('/js_interaction_test');
    // Remove blocking element after 100 ms.
    $this->clickLink('Remove Blocker Trigger');
    $this->clickLink('Target link');

    // Enable field after 100 ms.
    $this->clickLink('Enable Field Trigger');
    $this->assertSession()->fieldExists('target_field')->setValue('Test');
    $this->assertSession()->fieldValueEquals('target_field', 'Test');
  }

}
