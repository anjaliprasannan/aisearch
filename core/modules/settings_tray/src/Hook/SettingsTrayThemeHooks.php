<?php

namespace Drupal\settings_tray\Hook;

use Drupal\block\Entity\Block;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for settings_tray.
 */
class SettingsTrayThemeHooks {

  /**
   * Implements hook_preprocess_HOOK() for block templates.
   */
  #[Hook('preprocess_block')]
  public function preprocessBlock(&$variables): void {
    // Only blocks that have a settings_tray form and have no configuration
    // overrides will have a "Quick Edit" link. We could wait for the contextual
    // links to be initialized on the client side,  and then add the class and
    // data- attribute below there (via JavaScript). But that would mean that it
    // would be impossible to show Settings Tray's clickable regions immediately
    // when the page loads. When latency is high, this will cause flicker.
    // @see \Drupal\settings_tray\Access\BlockPluginHasSettingsTrayFormAccessCheck
    /** @var \Drupal\settings_tray\Access\BlockPluginHasSettingsTrayFormAccessCheck $access_checker */
    $access_checker = \Drupal::service('access_check.settings_tray.block.settings_tray_form');
    /** @var \Drupal\Core\Block\BlockManagerInterface $block_plugin_manager */
    $block_plugin_manager = \Drupal::service('plugin.manager.block');
    /** @var \Drupal\Core\Block\BlockPluginInterface $block_plugin */
    $block_plugin = $block_plugin_manager->createInstance($variables['plugin_id']);
    if (isset($variables['elements']['#contextual_links']['block']['route_parameters']['block'])) {
      $block = Block::load($variables['elements']['#contextual_links']['block']['route_parameters']['block']);
      if ($access_checker->accessBlockPlugin($block_plugin)->isAllowed() && !_settings_tray_has_block_overrides($block)) {
        // Add class and attributes to all blocks to allow JavaScript to target.
        $variables['attributes']['class'][] = 'settings-tray-editable';
        $variables['attributes']['data-drupal-settingstray'] = 'editable';
      }
    }
  }

}
