<?php

declare(strict_types=1);

namespace Drupal\FunctionalTests\Installer;

use Drupal\block\Entity\Block;
use PHPUnit\Framework\Attributes\Group;

/**
 * Verifies that the installer does not generate theme blocks.
 */
#[Group('Installer')]
class InstallerThemesBlocksProfileTest extends InstallerTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'testing_theme_required_blocks';

  /**
   * {@inheritdoc}
   */
  protected $profile = 'testing_themes_blocks';

  /**
   * Verify that there is no automatic block generation.
   */
  public function testInstaller(): void {

    // Account menu is a block that testing_theme_required_blocks provides,
    // but not testing_theme_optional_blocks. There shouldn't be a account menu
    // block for testing_theme_optional_blocks after the installation.
    $this->assertEmpty(Block::load('testing_theme_optional_blocks_account_menu'));
    $this->assertNotEmpty(Block::load('testing_theme_optional_blocks_page_title'));

    // Ensure that for themes without blocks, some default blocks will be
    // created.
    $this->assertNotEmpty(Block::load('testing_theme_without_blocks_account_menu'));
  }

}
