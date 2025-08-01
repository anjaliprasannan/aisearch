<?php

declare(strict_types=1);

namespace Drupal\FunctionalTests\Installer;

use Drupal\Core\Database\Database;
use PHPUnit\Framework\Attributes\Group;

/**
 * Verifies that installing from existing configuration works.
 */
#[Group('Installer')]
class InstallerExistingConfigExistingSettingsTest extends InstallerExistingConfigTest {

  /**
   * {@inheritdoc}
   *
   * Partially configures a preexisting settings.php file before invoking the
   * interactive installer.
   */
  protected function prepareEnvironment(): void {
    parent::prepareEnvironment();
    // Pre-configure hash salt.
    // Any string is valid, so simply use the class name of this test.
    $this->settings['settings']['hash_salt'] = (object) [
      'value' => __CLASS__,
      'required' => TRUE,
    ];

    // Pre-configure database credentials.
    $connection_info = Database::getConnectionInfo();
    unset($connection_info['default']['pdo']);
    unset($connection_info['default']['init_commands']);

    $this->settings['databases']['default'] = (object) [
      'value' => $connection_info,
      'required' => TRUE,
    ];
  }

}
