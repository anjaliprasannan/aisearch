<?php

namespace Drupal\Core\Render\Element;

use Drupal\Core\Render\Attribute\RenderElement;

/**
 * Provides a render element for a set of links rendered as a drop-down button.
 *
 * By default, this element sets #theme so that the 'links' theme hook is used
 * for rendering, with suffixes so that themes can override this specifically
 * without overriding all links theming. If the #subtype property is provided in
 * your render array with value 'foo', #theme is set to links__dropbutton__foo;
 * if not, it's links__dropbutton; both of these can be overridden by setting
 * the #theme property in your render array. See template_preprocess_links()
 * for documentation on the other properties used in theming; for instance, use
 * element property #links to provide $variables['links'] for theming.
 *
 * Properties:
 *
 * @property $links
 *   An array of links to actions. See template_preprocess_links() for
 *   documentation the properties of links in this array.
 * @property $dropbutton_type
 *   A string defining a type of dropbutton variant for
 *   styling proposes. Renders as class `dropbutton--#dropbutton_type`.
 *
 * Usage Example:
 * @code
 * $form['actions']['extra_actions'] = [
 *   '#type' => 'dropbutton',
 *   '#dropbutton_type' => 'small',
 *   '#links' => [
 *     'simple_form' => [
 *       'title' => $this->t('Simple Form'),
 *       'url' => Url::fromRoute('fapi_example.simple_form'),
 *     ],
 *     'demo' => [
 *       'title' => $this->t('Build Demo'),
 *       'url' => Url::fromRoute('fapi_example.build_demo'),
 *     ],
 *   ],
 * ];
 * @endcode
 *
 * @see \Drupal\Core\Render\Element\Operations
 */
#[RenderElement('dropbutton')]
class Dropbutton extends RenderElementBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#pre_render' => [
        [static::class, 'preRenderDropbutton'],
      ],
      '#theme' => 'links__dropbutton',
    ];
  }

  /**
   * Pre-render callback: Attaches the dropbutton library and required markup.
   */
  public static function preRenderDropbutton($element) {
    $element['#attached']['library'][] = 'core/drupal.dropbutton';
    $element['#attributes']['class'][] = 'dropbutton';

    if (!empty($element['#dropbutton_type'])) {
      $element['#attributes']['class'][] = 'dropbutton--' . $element['#dropbutton_type'];
    }

    if (!isset($element['#theme_wrappers'])) {
      $element['#theme_wrappers'] = [];
    }
    array_unshift($element['#theme_wrappers'], 'dropbutton_wrapper');

    // Enable targeted theming of specific dropbuttons (e.g., 'operations' or
    // 'operations__node').
    if (isset($element['#subtype'])) {
      $element['#theme'] .= '__' . $element['#subtype'];
    }

    return $element;
  }

}
