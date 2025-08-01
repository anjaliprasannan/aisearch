<?php

declare(strict_types=1);

namespace Drupal\pager_test\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for pager_test.
 */
class PagerTestThemeHooks {

  /**
   * Implements hook_preprocess_HOOK().
   */
  #[Hook('preprocess_pager')]
  public function preprocessPager(&$variables): void {
    // Nothing to do if there is only one page.
    $element = $variables['pager']['#element'];
    /** @var \Drupal\Core\Pager\PagerManagerInterface $pager_manager */
    $pager_manager = \Drupal::service('pager.manager');
    $pager = $pager_manager->getPager($element);
    // Nothing to do if there is no pager.
    if (!isset($pager)) {
      return;
    }
    // Nothing to do if there is only one page.
    if ($pager->getTotalPages() <= 1) {
      return;
    }
    foreach ($variables['items']['pages'] as &$pager_item) {
      $pager_item['attributes']['pager-test'] = 'yes';
      $pager_item['attributes']->addClass('lizards');
    }
    unset($pager_item);
    foreach ([
      'first',
      'previous',
      'next',
      'last',
    ] as $special_pager_item) {
      if (isset($variables['items'][$special_pager_item])) {
        $variables['items'][$special_pager_item]['attributes']->addClass('lizards');
        $variables['items'][$special_pager_item]['attributes']['pager-test'] = $special_pager_item;
      }
    }
  }

}
