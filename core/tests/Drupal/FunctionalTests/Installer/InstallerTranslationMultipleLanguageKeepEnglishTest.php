<?php

declare(strict_types=1);

namespace Drupal\FunctionalTests\Installer;

use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that keeping English in a foreign language install works.
 */
#[Group('Installer')]
class InstallerTranslationMultipleLanguageKeepEnglishTest extends InstallerTranslationMultipleLanguageForeignTest {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Switch to the multilingual testing profile with English kept.
   *
   * @var string
   */
  protected $profile = 'testing_multilingual_with_english';

}
