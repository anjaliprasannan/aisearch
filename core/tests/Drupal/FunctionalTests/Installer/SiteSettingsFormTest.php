<?php

declare(strict_types=1);

namespace Drupal\FunctionalTests\Installer;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the extension of the site settings form.
 */
#[Group('Installer')]
class SiteSettingsFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['install_form_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Confirms that the form is extensible.
   */
  public function testSiteSettingsForm(): void {
    // Test that the form page can be loaded without errors.
    $this->drupalGet('test-form');
    $this->assertSession()->statusCodeEquals(200);
  }

}
