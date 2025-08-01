<?php

declare(strict_types=1);

namespace Drupal\FunctionalTests\Installer;

use PHPUnit\Framework\Attributes\Group;

/**
 * Verifies that profiles with hook_install() can't be installed from config.
 */
#[Group('Installer')]
class InstallerExistingConfigProfileHookInstallTest extends InstallerConfigDirectoryTestBase {

  /**
   * {@inheritdoc}
   */
  protected $profile = 'config_profile_with_hook_install';

  /**
   * {@inheritdoc}
   */
  protected function visitInstaller(): void {
    // Create an .install file with a hook_install() implementation.
    $path = $this->siteDirectory . '/profiles/' . $this->profile;
    $contents = <<<EOF
<?php

function config_profile_with_hook_install_install() {
}
EOF;
    file_put_contents("$path/{$this->profile}.install", $contents);
    parent::visitInstaller();
  }

  /**
   * Installer step: Configure settings.
   */
  protected function setUpSettings(): void {
    // There are errors therefore there is nothing to do here.
  }

  /**
   * {@inheritdoc}
   */
  protected function setUpRequirementsProblem(): void {
    // The parent method asserts that there are no requirements errors, but
    // this test expects a requirements error in the test method below.
    // Therefore, we override this method to suppress the parent's assertions.
  }

  /**
   * Final installer step: Configure site.
   */
  protected function setUpSite(): void {
    // There are errors therefore there is nothing to do here.
  }

  /**
   * {@inheritdoc}
   */
  protected function getConfigLocation(): string {
    // We're not going to get to the config import stage so this does not
    // matter.
    return __DIR__ . '/../../../fixtures/config_install/testing_config_install_no_config';
  }

  /**
   * Confirms the installation has failed and the expected error is displayed.
   */
  public function testConfigSync(): void {
    $this->assertSession()->titleEquals('Requirements problem | Drupal');
    $this->assertSession()->pageTextContains($this->profile);
    $this->assertSession()->pageTextContains('The selected profile has a hook_install() implementation and therefore can not be installed from configuration.');
  }

}
