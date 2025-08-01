<?php

declare(strict_types=1);

namespace Drupal\FunctionalTests\Installer;

use Drupal\Core\Extension\ModuleUninstallValidatorException;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that an install profile can require modules.
 */
#[Group('Installer')]
class InstallProfileDependenciesTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected $profile = 'testing_install_profile_dependencies';

  /**
   * Tests that an install profile can require modules.
   */
  public function testUninstallingModules(): void {
    $user = $this->drupalCreateUser(['administer modules']);
    $this->drupalLogin($user);
    $this->drupalGet('admin/modules/uninstall');
    $this->assertSession()->fieldDisabled('uninstall[dblog]');
    $this->getSession()->getPage()->checkField('uninstall[dependency_foo_test]');
    $this->click('#edit-submit');
    // Click the confirm button.
    $this->click('#edit-submit');
    $this->assertSession()->responseContains('The selected modules have been uninstalled.');
    // We've uninstalled a module therefore we need to rebuild the container in
    // the test runner.
    $this->rebuildContainer();
    $this->assertFalse($this->container->get('module_handler')->moduleExists('dependency_foo_test'));
    try {
      $this->container->get('module_installer')->uninstall(['dblog']);
      $this->fail('Uninstalled dblog module.');
    }
    catch (ModuleUninstallValidatorException $e) {
      $this->assertStringContainsString("The 'Testing install profile dependencies' install profile requires 'Database Logging'", $e->getMessage());
    }
  }

}
