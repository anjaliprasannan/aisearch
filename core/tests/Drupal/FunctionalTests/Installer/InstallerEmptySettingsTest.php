<?php

declare(strict_types=1);

namespace Drupal\FunctionalTests\Installer;

use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the installer with empty settings file.
 */
#[Group('Installer')]
class InstallerEmptySettingsTest extends InstallerTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function prepareEnvironment(): void {
    parent::prepareEnvironment();
    // Create an empty settings.php file.
    $path = $this->root . DIRECTORY_SEPARATOR . $this->siteDirectory;
    file_put_contents($path . '/settings.php', '');
  }

  /**
   * Verifies that installation succeeded.
   */
  public function testInstaller(): void {
    $this->assertSession()->addressEquals('user/1');
    $this->assertSession()->statusCodeEquals(200);
  }

}
