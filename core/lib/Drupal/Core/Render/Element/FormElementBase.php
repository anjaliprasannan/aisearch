<?php

namespace Drupal\Core\Render\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Url;

/**
 * Provides a base class for form element plugins.
 *
 * Form elements are a subset of render elements, representing elements for
 * HTML forms, which can be referenced in form arrays. See the
 *
 * @see \Drupal\Core\Render\Attribute\FormElement
 * @see \Drupal\Core\Render\Element\FormElementInterface
 * @see \Drupal\Core\Render\ElementInfoManager
 * @see \Drupal\Core\Render\Element\RenderElementBase
 * @see plugin_api
 *
 * @ingroup theme_render
 * @see \Drupal\Core\Form\FormHelper::processStates()
 * @link theme_render Render API topic @endlink for an overview of render
 * arrays and render elements, and the @link form_api Form API topic @endlink
 * for an overview of forms and form arrays.
 *
 * The elements of form arrays are divided up into properties (whose keys
 * start with #) and children (whose keys do not start with #). The properties
 * provide data or settings that are used in rendering and form processing.
 * Some properties are specific to a particular type of form/render element,
 * some are available for any render element, and some are available for any
 * form input element. A list of the properties that are available for all form
 * elements follows; see \Drupal\Core\Render\Element\RenderElementBase for some
 * additional information, as well as a list of properties that are common to
 * all render elements (including form elements). Properties specific to a
 * particular element are documented on that element's class.
 *
 * Here is a list of properties that are used during the rendering and form
 * processing of form elements, besides those properties documented in
 * \Drupal\Core\Render\Element\RenderElementBase (for example: #prefix,
 * #suffix):
 * @property array $after_build
 *   Array of callables or function names, which are called after the element
 *   is built. Arguments: $element, $form_state.
 * @property array $ajax
 *   Array of elements to specify Ajax behavior. See the @link ajax Ajax API
 *   topic @endlink for more information.
 * @property array<string> $array_parents
 *   Array of names of all the element's parents (including itself) in the
 *   render array. See also #parents, #tree.
 * @property mixed $default_value
 *   Default value for the element. See also #value.
 * @property scalar|\Stringable|\Drupal\Core\Render\RenderableInterface|array $description
 *   Help or description text for the element. In an ideal user interface,
 *   the #title should be enough to describe the element, so most elements
 *   should not have a description; if you do need one, make sure it is
 *   translated. It can be anything that Twig can print and will be filtered
 *   for XSS as necessary.
 * @property bool $disabled
 *   If TRUE, the element is shown but does not accept user input.
 * @property array<callable> $element_validate
 *   Array of callables or function names, which are called to validate the
 *   input. Arguments: $element, $form_state, $form.
 * @property string $field_prefix
 *   Prefix to display before the HTML input element. Should be translated,
 *   normally. If it is not already wrapped in a safe markup object, will be
 *   filtered for XSS safety. Note that the contents of this prefix are
 *   wrapped in a <span> element, so the value should not contain block level
 *   HTML. Any HTML added must be valid, i.e. any tags introduced inside this
 *   prefix must also be terminated within the prefix.
 * @property string $field_suffix
 *   Suffix to display after the HTML input element. Should be translated,
 *   normally. If it is not already wrapped in a safe markup object, will be
 *   filtered for XSS safety. Note that the contents of this suffix are
 *   wrapped in a <span> element, so the value should not contain block
 *   level HTML. Any HTML must also be valid, i.e. any tags introduce inside
 *   this suffix must also be terminated within the suffix.
 * @property mixed $value
 *    A value that cannot be edited by the user.
 * @property bool $has_garbage_value
 * @internal
 *   Set to TRUE to indicate that the #value property of an
 *   element should not be used or processed.
 * @property bool $input
 * @internal
 *   Whether the element accepts input.
 * @property array<string> $parents
 *   Array of names of the element's parents for purposes of getting values
 *   out of $form_state. See also #array_parents, #tree.
 * @property array $process
 *   Array of callables or function names, which are called during form
 *   building. Arguments: $element, $form_state, $form.
 * @property bool, internal $processed
 *   Set to TRUE when the element is processed.
 * @property bool $required
 *   Whether input is required on the element.
 * @property array $states
 *   Information about JavaScript states, such as when to hide or show the
 *   element based on input on other elements.
 * @property string $title
 *   Title of the form element. Should be translated.
 * @property \Drupal\Core\Render\Element\TitleDisplay $title_display
 *   Where and how to display the #title.
 * @property bool $tree
 *   TRUE if the values of this element and its children should be hierarchical
 *   in $form_state; FALSE if the values should be flat. See also #parents,
 *   #array_parents.
 * @property callable $value_callback
 *   Callable or function name, which is called to transform the raw user
 *   input to the element's value. Arguments: $element, $input, $form_state.
 */
abstract class FormElementBase extends RenderElementBase implements FormElementInterface {

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    return NULL;
  }

  /**
   * Render API callback: Handles the #pattern form element property.
   *
   * This function is assigned as a #process callback.
   *
   * @param array $element
   *   An associative array containing the properties and children of the
   *   generic input element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   *
   * @return array
   *   The processed element.
   */
  public static function processPattern(&$element, FormStateInterface $form_state, &$complete_form) {
    if (isset($element['#pattern']) && !isset($element['#attributes']['pattern'])) {
      $element['#attributes']['pattern'] = $element['#pattern'];
      $element['#element_validate'][] = [static::class, 'validatePattern'];
    }

    return $element;
  }

  /**
   * Render API callback: Handles the #pattern form element property..
   *
   * This function is assigned as a #element_validate callback.
   *
   * @param array $element
   *   An associative array containing the properties and children of the
   *   generic form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   */
  public static function validatePattern(&$element, FormStateInterface $form_state, &$complete_form) {
    if ($element['#value'] !== '') {
      // The pattern must match the entire string and should have the same
      // behavior as the RegExp object in ECMA 262.
      // - Use bracket-style delimiters to avoid introducing a special delimiter
      //   character like '/' that would have to be escaped.
      // - Put in brackets so that the pattern can't interfere with what's
      //   prepended and appended.
      $pattern = '{^(?:' . $element['#pattern'] . ')$}';

      if (!preg_match($pattern, $element['#value'])) {
        $form_state->setError($element, t('%name field is not in the right format.', ['%name' => $element['#title']]));
      }
    }
  }

  /**
   * Adds autocomplete functionality to elements.
   *
   * This sets up autocomplete functionality for elements with an
   * #autocomplete_route_name property, using the #autocomplete_route_parameters
   * and #autocomplete_query_parameters properties if present.
   *
   * For example, suppose your autocomplete route name is
   * 'my_module.autocomplete' and its path is
   * '/my_module/autocomplete/{a}/{b}'. In a form array, you would create a text
   * field with properties:
   * @code
   * '#autocomplete_route_name' => 'my_module.autocomplete',
   * '#autocomplete_route_parameters' => ['a' => $some_key, 'b' => $some_id],
   * @endcode
   * If the user types "keywords" in that field, the full path called would be:
   * 'my_module_autocomplete/$some_key/$some_id?q=keywords'
   *
   * @param array $element
   *   The form element to process. Properties used:
   *   - #autocomplete_route_name: A route to be used as callback URL by the
   *     autocomplete JavaScript library.
   *   - #autocomplete_route_parameters: The parameters to be used in
   *     conjunction with the route name.
   *   - #autocomplete_query_parameters: The parameters to be used in
   *     query string.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   *
   * @return array
   *   The form element.
   */
  public static function processAutocomplete(&$element, FormStateInterface $form_state, &$complete_form) {
    $url = NULL;
    $access = FALSE;

    if (!empty($element['#autocomplete_route_name'])) {
      $parameters = $element['#autocomplete_route_parameters'] ?? [];
      $options = [];
      if (!empty($element['#autocomplete_query_parameters'])) {
        $options['query'] = $element['#autocomplete_query_parameters'];
      }
      $url = Url::fromRoute($element['#autocomplete_route_name'], $parameters, $options)->toString(TRUE);
      /** @var \Drupal\Core\Access\AccessManagerInterface $access_manager */
      $access_manager = \Drupal::service('access_manager');
      $access = $access_manager->checkNamedRoute($element['#autocomplete_route_name'], $parameters, \Drupal::currentUser(), TRUE);
    }

    if ($access) {
      $metadata = BubbleableMetadata::createFromRenderArray($element);
      if ($access->isAllowed()) {
        $element['#attributes']['class'][] = 'form-autocomplete';
        $metadata->addAttachments(['library' => ['core/drupal.autocomplete']]);
        // Provide a data attribute for the JavaScript behavior to bind to.
        $element['#attributes']['data-autocomplete-path'] = $url->getGeneratedUrl();
        $metadata = $metadata->merge($url);
      }
      $metadata
        ->merge(BubbleableMetadata::createFromObject($access))
        ->applyTo($element);
    }

    return $element;
  }

}
