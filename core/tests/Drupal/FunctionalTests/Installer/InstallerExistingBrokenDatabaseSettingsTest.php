<?php

declare(strict_types=1);

namespace Drupal\FunctionalTests\Installer;

use Drupal\Core\Database\Database;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the installer with broken database connection info in settings.php.
 */
#[Group('Installer')]
class InstallerExistingBrokenDatabaseSettingsTest extends InstallerTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function prepareEnvironment(): void {
    parent::prepareEnvironment();
    // Pre-configure database credentials in settings.php.
    $connection_info = Database::getConnectionInfo();

    if ($connection_info['default']['driver'] !== 'mysql') {
      $this->markTestSkipped('This test relies on overriding the mysql driver');
    }

    // Use a database driver that reports a fake database version that does
    // not meet requirements.
    unset($connection_info['default']['pdo']);
    unset($connection_info['default']['init_commands']);
    $connection_info['default']['driver'] = 'DriverTestMysqlDeprecatedVersion';
    $namespace = 'Drupal\\driver_test\\Driver\\Database\\DriverTestMysqlDeprecatedVersion';
    $connection_info['default']['namespace'] = $namespace;
    $connection_info['default']['autoload'] = \Drupal::service('extension.list.database_driver')
      ->get($namespace)
      ->getAutoloadInfo()['autoload'];

    $this->settings['databases']['default'] = (object) [
      'value' => $connection_info,
      'required' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function setUpSettings(): void {
    // This form will never be reached.
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
   * {@inheritdoc}
   */
  protected function setUpSite(): void {
    // This form will never be reached.
  }

  /**
   * Tests the expected requirements problem.
   */
  public function testRequirementsProblem(): void {
    $this->assertSession()->titleEquals('Requirements problem | Drupal');
    $this->assertSession()->pageTextContains('Database settings');
    $this->assertSession()->pageTextContains('Resolve all issues below to continue the installation. For help configuring your database server,');
    $this->assertSession()->pageTextContains('The database server version 10.2.31-MariaDB-1:10.2.31+maria~bionic-log is less than the minimum required version');
  }

}
