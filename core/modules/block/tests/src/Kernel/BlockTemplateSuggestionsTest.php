<?php

declare(strict_types=1);

namespace Drupal\Tests\block\Kernel;

use Drupal\block\Entity\Block;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the block theme suggestions.
 *
 * @group block
 */
class BlockTemplateSuggestionsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'system',
  ];

  /**
   * Tests template suggestions from the block module.
   */
  public function testBlockThemeHookSuggestions(): void {
    $this->installConfig(['system']);

    // Create a block using a plugin with derivative to be preprocessed.
    $block = Block::create([
      'plugin' => 'system_menu_block:admin',
      'region' => 'footer',
      'id' => 'machine_name',
    ]);

    $variables = [];
    /** @var \Drupal\Core\Block\BlockPluginInterface $plugin */
    $plugin = $block->getPlugin();
    $variables['elements']['#configuration'] = $plugin->getConfiguration();
    $variables['elements']['#plugin_id'] = $plugin->getPluginId();
    $variables['elements']['#id'] = $block->id();
    $variables['elements']['#base_plugin_id'] = $plugin->getBaseId();
    $variables['elements']['#derivative_plugin_id'] = $plugin->getDerivativeId();
    $variables['elements']['content'] = [];
    $module_handler = $this->container->get('module_handler');
    $suggestions = $module_handler->invoke('block', 'theme_suggestions_block', [$variables]);
    $this->assertSame([
      'block__system',
      'block__system_menu_block',
      'block__system_menu_block__admin',
      'block__machine_name',
    ], $suggestions);
  }

}
