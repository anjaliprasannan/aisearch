<?php

namespace Drupal\big_pipe\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for big_pipe.
 */
class BigPipeThemeHooks {

  /**
   * Implements hook_theme_suggestions_HOOK().
   */
  #[Hook('theme_suggestions_big_pipe_interface_preview')]
  public function themeSuggestionsBigPipeInterfacePreview(array $variables): array {
    $common_callbacks_simplified_suggestions = [
      'Drupal_block_BlockViewBuilder__lazyBuilder' => 'block',
    ];
    $suggestions = [];
    $suggestion = 'big_pipe_interface_preview';
    if ($variables['callback']) {
      $callback = preg_replace('/[^a-zA-Z0-9]/', '_', $variables['callback']);
      if (is_array($callback)) {
        $callback = implode('__', $callback);
      }
      // Use simplified template suggestion, if any.
      // For example, this simplifies
            // phpcs:ignore Drupal.Files.LineLength
      // big-pipe-interface-preview--Drupal-block-BlockViewBuilder--lazyBuilder--<BLOCK ID>.html.twig
      // to
      // big-pipe-interface-preview--block--<BLOCK ID>.html.twig.
      if (isset($common_callbacks_simplified_suggestions[$callback])) {
        $callback = $common_callbacks_simplified_suggestions[$callback];
      }
      $suggestions[] = $suggestion .= '__' . $callback;
      if (is_array($variables['arguments'])) {
        $arguments = preg_replace('/[^a-zA-Z0-9]/', '_', $variables['arguments']);
        foreach ($arguments as $argument) {
          if (empty($argument)) {
            continue;
          }
          $suggestions[] = $suggestion . '__' . $argument;
        }
      }
    }
    return $suggestions;
  }

}
