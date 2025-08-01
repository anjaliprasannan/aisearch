<?php

declare(strict_types=1);

namespace Drupal\FunctionalTests\Installer;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests installing the Testing profile with update notifications on.
 */
#[Group('Installer')]
class TestingProfileInstallTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $profile = 'testing';

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Ensure the Update Status module is installed.
   */
  public function testUpdateModuleInstall(): void {
    $this->assertTrue(\Drupal::moduleHandler()->moduleExists('update'));
  }

  /**
   * {@inheritdoc}
   */
  protected function installParameters() {
    $params = parent::installParameters();
    $params['forms']['install_configure_form']['enable_update_status_module'] = TRUE;
    return $params;
  }

}
