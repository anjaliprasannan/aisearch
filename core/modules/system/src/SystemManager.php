<?php

namespace Drupal\system;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\Menu\MenuActiveTrailInterface;
use Drupal\Core\Menu\MenuLinkInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * System Manager Service.
 */
class SystemManager {

  use StringTranslationTrait;

  /**
   * Module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The menu link tree manager.
   *
   * @var \Drupal\Core\Menu\MenuLinkTreeInterface
   */
  protected $menuTree;

  /**
   * The active menu trail service.
   *
   * @var \Drupal\Core\Menu\MenuActiveTrailInterface
   */
  protected $menuActiveTrail;

  /**
   * A static cache of menu items.
   *
   * @var array
   */
  protected $menuItems;

  /**
   * Requirement severity -- Requirement successfully met.
   *
   * @deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use
   *    \Drupal\Core\Extension\Requirement\RequirementSeverity::OK instead.
   *
   * @see https://www.drupal.org/node/3410939
   */
  const REQUIREMENT_OK = 0;

  /**
   * Requirement severity -- Warning condition; proceed but flag warning.
   *
   * @deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use
   *   \Drupal\Core\Extension\Requirement\RequirementSeverity::Warning instead.
   *
   * @see https://www.drupal.org/node/3410939
   */
  const REQUIREMENT_WARNING = 1;

  /**
   * Requirement severity -- Error condition; abort installation.
   *
   * @deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use
   *  \Drupal\Core\Extension\Requirement\RequirementSeverity::Error instead.
   *
   * @see https://www.drupal.org/node/3410939
   */
  const REQUIREMENT_ERROR = 2;

  /**
   * Constructs a SystemManager object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Menu\MenuLinkTreeInterface $menu_tree
   *   The menu tree manager.
   * @param \Drupal\Core\Menu\MenuActiveTrailInterface $menu_active_trail
   *   The active menu trail service.
   */
  public function __construct(ModuleHandlerInterface $module_handler, RequestStack $request_stack, MenuLinkTreeInterface $menu_tree, MenuActiveTrailInterface $menu_active_trail) {
    $this->moduleHandler = $module_handler;
    $this->requestStack = $request_stack;
    $this->menuTree = $menu_tree;
    $this->menuActiveTrail = $menu_active_trail;
  }

  /**
   * Checks for requirement severity.
   *
   * @return bool
   *   Returns the status of the system.
   */
  public function checkRequirements() {
    $requirements = $this->listRequirements();
    return RequirementSeverity::maxSeverityFromRequirements($requirements) === RequirementSeverity::Error;
  }

  /**
   * Displays the site status report. Can also be used as a pure check.
   *
   * @return array
   *   An array of system requirements.
   */
  public function listRequirements() {
    // Load .install files.
    include_once DRUPAL_ROOT . '/core/includes/install.inc';
    drupal_load_updates();

    // Check run-time requirements and status information.
    $requirements = $this->moduleHandler->invokeAll('requirements', ['runtime']);
    $runtime_requirements = $this->moduleHandler->invokeAll('runtime_requirements');
    $requirements = array_merge($requirements, $runtime_requirements);
    $this->moduleHandler->alter('requirements', $requirements);
    $this->moduleHandler->alter('runtime_requirements', $requirements);
    uasort($requirements, function ($a, $b) {
      if (!isset($a['weight'])) {
        if (!isset($b['weight'])) {
          return strcasecmp($a['title'], $b['title']);
        }
        return -$b['weight'];
      }
      return isset($b['weight']) ? $a['weight'] - $b['weight'] : $a['weight'];
    });

    return $requirements;
  }

  /**
   * Extracts the highest severity from the requirements array.
   *
   * @param array $requirements
   *   An array of requirements, in the same format as is returned by
   *   hook_requirements().
   *
   * @return int
   *   The highest severity in the array.
   *
   * @deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use
   *   \Drupal\Core\Extension\Requirement\RequirementSeverity::getMaxSeverity()
   *   instead.
   *
   * @see https://www.drupal.org/node/3410939
   */
  public function getMaxSeverity(&$requirements) {
    @trigger_error(__METHOD__ . '() is deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use ' . RequirementSeverity::class . '::maxSeverityFromRequirements() instead. See https://www.drupal.org/node/3410939', \E_USER_DEPRECATED);
    return RequirementSeverity::maxSeverityFromRequirements($requirements)->value;
  }

  /**
   * Loads the contents of a menu block.
   *
   * This function is often a destination for these blocks.
   * For example, 'admin/structure/types' needs to have a destination to be
   * valid in the Drupal menu system, but too much information there might be
   * hidden, so we supply the contents of the block.
   *
   * @return array
   *   A render array suitable for
   *   \Drupal\Core\Render\RendererInterface::render().
   */
  public function getBlockContents() {
    // We hard-code the menu name here since otherwise a link in the tools menu
    // or elsewhere could give us a blank block.
    $link = $this->menuActiveTrail->getActiveLink('admin');
    if ($link && $content = $this->getAdminBlock($link)) {
      $output = [
        '#theme' => 'admin_block_content',
        '#content' => $content,
      ];
    }
    else {
      $output = [
        '#markup' => $this->t('You do not have any administrative items.'),
      ];
    }
    return $output;
  }

  /**
   * Provide a single block on the administration overview page.
   *
   * @param \Drupal\Core\Menu\MenuLinkInterface $instance
   *   The menu item to be displayed.
   *
   * @return array
   *   An array of menu items, as expected by admin-block-content.html.twig.
   */
  public function getAdminBlock(MenuLinkInterface $instance) {
    $content = [];
    // Only find the children of this link.
    $link_id = $instance->getPluginId();
    $parameters = new MenuTreeParameters();
    $parameters->setRoot($link_id)->excludeRoot()->setTopLevelOnly()->onlyEnabledLinks();
    $tree = $this->menuTree->load(NULL, $parameters);
    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];
    $tree = $this->menuTree->transform($tree, $manipulators);
    foreach ($tree as $key => $element) {
      // Only render accessible links.
      if (!$element->access->isAllowed()) {
        // @todo Bubble cacheability metadata of both accessible and
        //   inaccessible links. Currently made impossible by the way admin
        //   blocks are rendered.
        continue;
      }

      /** @var \Drupal\Core\Menu\MenuLinkInterface $link */
      $link = $element->link;
      $content[$key]['title'] = $link->getTitle();
      $content[$key]['options'] = $link->getOptions();
      $content[$key]['description'] = $link->getDescription();
      $content[$key]['url'] = $link->getUrlObject();
    }
    ksort($content);
    return $content;
  }

}
