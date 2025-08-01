<?php

namespace Drupal\Core\Render\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Attribute\FormElement;
use Drupal\Core\Render\Element;

/**
 * Provides a one-line text field form element.
 *
 * Properties:
 *
 * @property $maxlength
 *   Maximum number of characters of input allowed.
 * @property $size
 *   The size of the input element in characters.
 * @property $autocomplete_route_name
 *   A route to be used as callback URL by the
 *   autocomplete JavaScript library.
 * @property $autocomplete_route_parameters
 *   An array of parameters to be used in
 *   conjunction with the route name.
 * @property $pattern
 *   A string for the native HTML5 pattern attribute.
 * @property $placeholder
 *   A string to displayed in a textfield when it has no value.
 *
 * Usage example:
 *
 * @code
 * $form['title'] = [
 *   '#type' => 'textfield',
 *   '#title' => $this->t('Subject'),
 *   '#default_value' => $node->title,
 *   '#size' => 60,
 *   '#maxlength' => 128,
 *   '#pattern' => 'some-prefix-[a-z]+',
 *   '#required' => TRUE,
 * ];
 * @endcode
 *
 * @see \Drupal\Core\Render\Element
 * @see \Drupal\Core\Render\Element\Textarea
 */
#[FormElement('textfield')]
class Textfield extends FormElementBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#input' => TRUE,
      '#size' => 60,
      '#maxlength' => 128,
      '#autocomplete_route_name' => FALSE,
      '#process' => [
        [static::class, 'processAutocomplete'],
        [static::class, 'processAjaxForm'],
        [static::class, 'processPattern'],
        [static::class, 'processGroup'],
      ],
      '#pre_render' => [
        [static::class, 'preRenderTextfield'],
        [static::class, 'preRenderGroup'],
      ],
      '#theme' => 'input__textfield',
      '#theme_wrappers' => ['form_element'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    if ($input !== FALSE && $input !== NULL) {
      // This should be a string, but allow other scalars since they might be
      // valid input in programmatic form submissions.
      if (!is_scalar($input)) {
        $input = '';
      }
      return str_replace(["\r", "\n"], '', $input);
    }
    return NULL;
  }

  /**
   * Prepares a #type 'textfield' render element for input.html.twig.
   *
   * @param array $element
   *   An associative array containing the properties of the element.
   *   Properties used: #title, #value, #description, #size, #maxlength,
   *   #placeholder, #required, #attributes.
   *
   * @return array
   *   The $element with prepared variables ready for input.html.twig.
   */
  public static function preRenderTextfield($element) {
    $element['#attributes']['type'] = 'text';
    Element::setAttributes($element, ['id', 'name', 'value', 'size', 'maxlength', 'placeholder']);
    static::setAttributes($element, ['form-text']);

    return $element;
  }

}
