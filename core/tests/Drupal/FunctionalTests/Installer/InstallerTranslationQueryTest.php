<?php

declare(strict_types=1);

namespace Drupal\FunctionalTests\Installer;

use PHPUnit\Framework\Attributes\Group;

/**
 * Installs Drupal in German and checks resulting site.
 *
 * @see \Drupal\FunctionalTests\Installer\InstallerTranslationTest
 */
#[Group('Installer')]
class InstallerTranslationQueryTest extends InstallerTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Overrides the language code in which to install Drupal.
   *
   * @var string
   */
  protected $langcode = 'de';

  /**
   * {@inheritdoc}
   */
  protected function visitInstaller(): void {
    // Place a custom local translation in the translations directory.
    mkdir($this->root . '/' . $this->siteDirectory . '/files/translations', 0777, TRUE);
    file_put_contents($this->root . '/' . $this->siteDirectory . '/files/translations/drupal-8.0.0.de.po', $this->getPo('de'));

    // The unrouted URL assembler does not exist at this point, so we build the
    // URL ourselves.
    $this->drupalGet($GLOBALS['base_url'] . '/core/install.php?langcode=' . $this->langcode);

    // The language should have been automatically detected, all following
    // screens should be translated already.
    $this->assertSession()->buttonExists('Save and continue de');
    $this->translations['Save and continue'] = 'Save and continue de';

    // Check the language direction.
    $this->assertSession()->elementTextEquals('xpath', '/@dir', 'ltr');
  }

  /**
   * {@inheritdoc}
   */
  protected function setUpLanguage(): void {
    // The language was preset by passing a query parameter in the URL, so no
    // explicit language selection is necessary.
  }

  /**
   * Verifies the expected behaviors of the installation result.
   */
  public function testInstaller(): void {
    $this->assertSession()->addressEquals('user/1');
    $this->assertSession()->statusCodeEquals(200);

    // Verify German was configured but not English.
    $this->drupalGet('admin/config/regional/language');
    // cspell:ignore deutsch
    $this->assertSession()->pageTextContains('Deutsch');
    $this->assertSession()->pageTextNotContains('English');
  }

  /**
   * Returns the string for the test .po file.
   *
   * @param string $langcode
   *   The language code.
   *
   * @return string
   *   Contents for the test .po file.
   */
  protected function getPo($langcode): string {
    return <<<PO
msgid ""
msgstr ""

msgid "Save and continue"
msgstr "Save and continue $langcode"
PO;
  }

}
