<?php

namespace Drupal\Core\Render\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Attribute\FormElement;
use Drupal\Core\Render\Element;

/**
 * Provides a form input element for entering an email address.
 *
 * Properties:
 *
 * @property $default_value
 *   An RFC-compliant email address.
 * @property $size
 *   The size of the input element in characters.
 * @property $pattern
 *   A string for the native HTML5 pattern attribute.
 *
 * Example usage:
 * @code
 * $form['email'] = [
 *   '#type' => 'email',
 *   '#title' => $this->t('Email'),
 *   '#pattern' => '*@example.com',
 * ];
 * @endcode
 *
 * @see \Drupal\Core\Render\Element\Textfield
 */
#[FormElement('email')]
class Email extends FormElementBase {

  /**
   * Defines the max length for an email address.
   *
   * The maximum length of an email address is 254 characters. RFC 3696
   * specifies a total length of 320 characters, but mentions that
   * addresses longer than 256 characters are not normally useful. Erratum
   * 1690 was then released which corrected this value to 254 characters.
   *
   * @see http://tools.ietf.org/html/rfc3696#section-3
   * @see http://www.rfc-editor.org/errata_search.php?rfc=3696&eid=1690
   */
  const EMAIL_MAX_LENGTH = 254;

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#input' => TRUE,
      '#size' => 60,
      '#maxlength' => self::EMAIL_MAX_LENGTH,
      '#autocomplete_route_name' => FALSE,
      '#process' => [
        [static::class, 'processAutocomplete'],
        [static::class, 'processAjaxForm'],
        [static::class, 'processPattern'],
      ],
      '#element_validate' => [
        [static::class, 'validateEmail'],
      ],
      '#pre_render' => [
        [static::class, 'preRenderEmail'],
      ],
      '#theme' => 'input__email',
      '#theme_wrappers' => ['form_element'],
    ];
  }

  /**
   * Form element validation handler for #type 'email'.
   *
   * Note that #maxlength and #required is validated by _form_validate()
   * already.
   */
  public static function validateEmail(&$element, FormStateInterface $form_state, &$complete_form) {
    $value = trim($element['#value']);
    $form_state->setValueForElement($element, $value);

    if ($value !== '' && !\Drupal::service('email.validator')->isValid($value)) {
      $form_state->setError($element, t('The email address %mail is not valid. Use the format user@example.com.', ['%mail' => $value]));
    }
  }

  /**
   * Prepares a #type 'email' render element for input.html.twig.
   *
   * @param array $element
   *   An associative array containing the properties of the element.
   *   Properties used: #title, #value, #description, #size, #maxlength,
   *   #placeholder, #required, #attributes.
   *
   * @return array
   *   The $element with prepared variables ready for input.html.twig.
   */
  public static function preRenderEmail($element) {
    $element['#attributes']['type'] = 'email';
    Element::setAttributes($element, ['id', 'name', 'value', 'size', 'maxlength', 'placeholder']);
    static::setAttributes($element, ['form-email']);
    return $element;
  }

}
