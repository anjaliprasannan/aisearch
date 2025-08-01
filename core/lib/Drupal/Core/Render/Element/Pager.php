<?php

namespace Drupal\Core\Render\Element;

use Drupal\Core\Render\Attribute\RenderElement;

/**
 * Provides a render element for a pager.
 *
 * The pager must be initialized with a call to
 * \Drupal\Core\Pager\PagerManagerInterface::createPager() in order to render
 * properly. When used with database queries, this is performed for you when you
 * extend a select query with \Drupal\Core\Database\Query\PagerSelectExtender.
 *
 * Properties:
 *
 * @property $element
 *   (optional, int) The pager ID, to distinguish between multiple
 *   pagers on the same page (defaults to 0).
 * @property $pagination_heading_level
 *   (optional) A heading level for the pager.
 * @property $parameters
 *   (optional) An associative array of query string parameters to
 *   append to the pager.
 * @property $quantity
 *   The maximum number of numbered page links to create (defaults
 *   to 9).
 * @property $tags
 *   (optional) An array of labels for the controls in the pages.
 * @property $route_name
 *   (optional) The name of the route to be used to build pager
 *   links. Defaults to '<none>', which will make links relative to the current
 *   URL. This makes the page more effectively cacheable.
 *
 * @code
 * $build['pager'] = [
 *   '#type' => 'pager',
 * ];
 * @endcode
 */
#[RenderElement('pager')]
class Pager extends RenderElementBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#pre_render' => [
        static::class . '::preRenderPager',
      ],
      '#theme' => 'pager',
      // The pager ID, to distinguish between multiple pagers on the same page.
      '#element' => 0,
      // The heading level to use for the pager.
      '#pagination_heading_level' => 'h4',
      // An associative array of query string parameters to append to the pager
      // links.
      '#parameters' => [],
      // The number of pages in the list.
      '#quantity' => 9,
      // An array of labels for the controls in the pager.
      '#tags' => [],
      // The name of the route to be used to build pager links. By default no
      // path is provided, which will make links relative to the current URL.
      // This makes the page more effectively cacheable.
      '#route_name' => '<none>',
    ];
  }

  /**
   * Render API callback: Associates the appropriate cache context.
   *
   * This function is assigned as a #pre_render callback.
   *
   * @param array $pager
   *   A renderable array of #type => pager.
   *
   * @return array
   *   The render array with cache contexts added.
   */
  public static function preRenderPager(array $pager) {
    // Note: the default pager theme preprocess function
    // \Drupal\Core\Pager\PagerPreprocess::preprocessPager() also calls
    // \Drupal\Core\Pager\PagerManagerInterface::getUpdatedParameters(), which
    // maintains the existing query string. Therefore
    // \Drupal\Core\Pager\PagerPreprocess::preprocessPager() adds the
    // 'url.query_args' cache context which causes the more specific cache
    // context below to be optimized away. In other themes, however, that may
    // not be the case.
    $pager['#cache']['contexts'][] = 'url.query_args.pagers:' . $pager['#element'];
    return $pager;
  }

}
