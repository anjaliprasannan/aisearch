<?php

declare(strict_types=1);

namespace Drupal\FunctionalTests\Installer;

use PHPUnit\Framework\Attributes\Group;

/**
 * Verifies that installing from existing configuration works.
 */
#[Group('Installer')]
class InstallerExistingConfigSyncDirectoryProfileMismatchTest extends InstallerConfigDirectoryTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected $profile = 'testing_config_install_multilingual';

  /**
   * {@inheritdoc}
   */
  protected $existingSyncDirectory = TRUE;

  /**
   * {@inheritdoc}
   */
  protected function getConfigLocation(): string {
    return __DIR__ . '/../../../fixtures/config_install/multilingual';
  }

  /**
   * Installer step: Configure settings.
   */
  protected function setUpSettings(): void {
    // Cause a profile mismatch by hacking the URL.
    $this->drupalGet(str_replace($this->profile, 'minimal', $this->getUrl()));
    parent::setUpSettings();
  }

  protected function setUpSite(): void {
    // This step will not occur because there is an error.
  }

  /**
   * Tests that profile mismatch fails to install.
   */
  public function testConfigSync(): void {
    $this->htmlOutput(NULL);
    $this->assertSession()->titleEquals('Configuration validation | Drupal');
    $this->assertSession()->pageTextContains('The configuration synchronization failed validation.');
    $this->assertSession()->pageTextContains('The selected installation profile minimal does not match the profile stored in configuration testing_config_install_multilingual.');

    // Ensure there is no continuation button.
    $this->assertSession()->pageTextNotContains('Save and continue');
    $this->assertSession()->buttonNotExists('edit-submit');
  }

}
