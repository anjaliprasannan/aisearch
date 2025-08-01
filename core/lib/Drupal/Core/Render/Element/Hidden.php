<?php

namespace Drupal\Core\Render\Element;

use Drupal\Core\Render\Attribute\FormElement;
use Drupal\Core\Render\Element;

/**
 * Provides a form element for an HTML 'hidden' input element.
 *
 * Specify either #default_value or #value but not both.
 *
 * Properties:
 *
 * @property $default_value
 *   The initial value of the form element. JavaScript may
 *   alter the value prior to submission.
 * @property $value
 *   The value of the form element. The Form API ensures that this
 *   value remains unchanged by the browser.
 *
 * Usage example:
 * @code
 * $form['entity_id'] = ['#type' => 'hidden', '#value' => $entity_id];
 * @endcode
 *
 * @see \Drupal\Core\Render\Element\Value
 */
#[FormElement('hidden')]
class Hidden extends FormElementBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#input' => TRUE,
      '#process' => [
        [static::class, 'processAjaxForm'],
      ],
      '#pre_render' => [
        [static::class, 'preRenderHidden'],
      ],
      '#theme' => 'input__hidden',
    ];
  }

  /**
   * Prepares a #type 'hidden' render element for input.html.twig.
   *
   * @param array $element
   *   An associative array containing the properties of the element.
   *   Properties used: #name, #value, #attributes.
   *
   * @return array
   *   The $element with prepared variables ready for input.html.twig.
   */
  public static function preRenderHidden($element) {
    $element['#attributes']['type'] = 'hidden';
    Element::setAttributes($element, ['name', 'value']);

    return $element;
  }

}
