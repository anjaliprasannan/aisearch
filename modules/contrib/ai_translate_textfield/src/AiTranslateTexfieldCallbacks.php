<?php

namespace Drupal\ai_translate_textfield;

use Drupal\ai\OperationType\TranslateText\TranslateTextInput;
use Drupal\ai\Plugin\ProviderProxy;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Security\TrustedCallbackInterface;
use Soundasleep\Html2Text;

/**
 * Callbacks for the AI Textfield Translation module.
 */
class AiTranslateTexfieldCallbacks implements TrustedCallbackInterface {

  /**
   * The supported field widgets.
   */
  public const SUPPORTED_FIELD_WIDGETS = [
    'string_textarea',
    'text_textarea',
    'string_textfield',
    'text_textfield',
    'text_textarea_with_summary'
  ];

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['ajaxTranslateText', 'processElement'];
  }

  /**
   * Process hook to add the translation feature for supported form elements.
   *
   * @param array|mixed $element
   *   The render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   * @param array $context
   *   The context.
   *
   * @return array
   *   The processed render array.
   */
  public static function processElement(array &$element, FormStateInterface $form_state, $context) {

    // We're on another type of field.
    if (!isset($context['widget']) || !in_array($context['widget']->getPluginId(), self::SUPPORTED_FIELD_WIDGETS, TRUE)) {
      return $element;
    }

    // Let's check if the feature is enabled for this field.
    if (!$context['widget']->getThirdPartySetting('ai_translate_textfield', 'enable_translations')) {
      return $element;
    }

    if (!\Drupal::currentUser()->hasPermission('use ai translation')) {
      return $element;
    }

    $config = \Drupal::config('ai_translate_textfield.settings');

    $fieldName = $context['items']->getName();
    $parents = implode('-', $element['#field_parents']);
    $id = ($parents ? $parents . '-ai-translator-' : 'ai-translator-') . $fieldName . '-' . $context['delta'];
    $element['#prefix'] = '<div class="' . $id . '">';
    $element['#suffix'] = '</div>';
    $settings = [
      'strip_tags' => $context['widget']->getThirdPartySetting('ai_translate_textfield', 'strip_tags'),
      'html' => str_starts_with($context['items']->getFieldDefinition()->getType(), 'text_'),
    ];

    $buttonText = $config->get('button_text') ?? t('Request automatic translation');

    if ($config->get('warning_enabled')) {
      $element['#attached']['library'][] = 'ai_translate_textfield/modal-button-action';
      $element['#attached']['drupalSettings']['ai_translate_textfield_modal'] = [
        'dialog_title' => $config->get('dialog_title') ?? 'title missing',
        'dialog_content' => $config->get('dialog_content')['value'] ?? 'content missing',
        'dialog_ok_button' => $config->get('dialog_ok_button') ?? 'Ok',
        'dialog_cancel_button' => $config->get('dialog_cancel_button') ?? 'Cancel',
      ];
      $element['translation_warning_button'] = [
        '#type' => 'button',
        '#value' => $buttonText,
        '#limit_validation_errors' => [],
        '#attributes' => [
          'data-ai-translator-id' => $id,
          'class' => [
            'ai-translator-warning-button',
          ],
        ],
        '#weight' => 500,
        '#name' => $id . '-modal-button',
      ];
      $element['translate_button'] = [
        '#type' => 'button',
        '#name' => 'translate_button-' . $id,
        '#value' => $buttonText,
        '#ajax' => [
          'callback' => [static::class, 'ajaxTranslateText'],
          'wrapper' => $id . '-wrapper',
          'event' => 'click',
        ],
        '#limit_validation_errors' => [],
        '#attributes' => [
          'data-ai-translator' => $id,
          'class' => [
            'hidden',
            'js-hide',
          ],
        ],
        '#weight' => 500,
        '#widget_settings' => $settings,
      ];

    }
    else {
      // Add a button to trigger translation with Ajax.
      $element['translate_button'] = [
        '#type' => 'button',
        '#name' => 'translate_button-' . $id,
        '#value' => $buttonText,
        '#ajax' => [
          'callback' => [static::class, 'ajaxTranslateText'],
          'wrapper' => $id . '-wrapper',
        ],
        '#limit_validation_errors' => [],
        '#attributes' => [
          'data-ai-translator' => $id,
        ],
        '#weight' => 500,
        '#widget_settings' => $settings,
      ];

    }

    return $element;
  }

  /**
   * Ajax callback to translate text and update the field value.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response.
   */
  public static function ajaxTranslateText(array &$form, FormStateInterface $formState): AjaxResponse {
    $element = $formState->getTriggeringElement();
    $parents = array_slice($element['#parents'], 0, -1);
    $array_parents = array_slice($element['#array_parents'], 0, -1);

    $widgetSettings = $element['#widget_settings'];

    $selector = $element['#attributes']['data-ai-translator'];

    // Get the current field value.
    $fieldValue = $formState->getValue(array_merge($parents, ['value']));

    $target_language = $formState->getFormObject()->getEntity()->language()->getId();

    try {
      $success = FALSE;
      // Translate the text using the selected service.
      $translated = self::translateText($fieldValue, $widgetSettings, $target_language);
      if ($fieldValue === $translated) {
        $messages = [
          MessengerInterface::TYPE_ERROR => [t('The text and its translation are the same so maybe there was an error trying to translate it. Was the text already written on the current language?')],
        ];
      }
      elseif (empty($translated)) {
        $messages = [
          MessengerInterface::TYPE_ERROR => [t('The translation result is empty for an unknown reason. Not replacing the original content.')],
        ];
      }
      else {
        $success = TRUE;
        $messages = [
          MessengerInterface::TYPE_STATUS => [
            [
              '#type' => 'markup',
              '#markup' => (string) t('Replaced text with an AI translation. Please review it thoroughly before saving. The original text was:') . '<br>' . $fieldValue,
            ],
          ],
        ];
      }
    }
    catch (\Exception $e) {
      $messages = [
        MessengerInterface::TYPE_ERROR => [
          t('The translation failed and the service returned the following error: @error',
            ['@error' => $e->getMessage()],
          ),
        ],
      ];
    }

    $messages = [
      '#theme' => 'status_messages',
      '#weight' => -1000,
      '#message_list' => $messages,
    ];

    // The rendering system insist on not rendering groups again. That's cool,
    // but works against our purpose, if the element happens to be inside one.
    unset($form[$array_parents[0]]['#group']);

    NestedArray::setValue($form, array_merge($array_parents, ['messages']), $messages);

    // Only change the field value on success.
    if ($success) {
      $formState->setValue(array_merge($parents, ['value']), $translated);
      NestedArray::setValue($form, array_merge($array_parents, ['value', '#value']), $translated);
    }

    $changed = NestedArray::getValue($form, $array_parents);
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('.' . $selector, $changed));
    return $response;
  }

  /**
   * Translate text using the selected translation service.
   *
   * @param string $text
   *   The text to be translated.
   * @param array $settings
   *   Settings for this field.
   * @param string $targetLang
   *   The language code of the target language.
   *
   * @return string
   *   The translated text.
   */
  protected static function translateText(string $text, array $settings, string $targetLang): string {
    $config = \Drupal::config('ai_translate_textfield.settings');
    $model = $config->get('model');

    $stripTags = $settings['strip_tags'];
    if ($stripTags) {
      if (class_exists(Html2Text::class)) {
        $text = Html2Text::convert($text);
      }
      else {
        $text = strip_tags($text);
      }
    }

    $text = new TranslateTextInput($text, NULL, $targetLang);

    /** @var \Drupal\ai\OperationType\TranslateText\TranslateTextInterface $translator */
    $translator = self::getProvider();
    if (!$translator instanceof ProviderProxy) {
      throw new \Exception('No valid translator found.');
    }

    $options = [];
    if ($settings['html'] && $translator->getModuleDataName() === 'ai_provider_deepl') {
      $options['tag_handling'] = 'html';
    }

    $translation = $translator->translateText($text, $model, $options);

    return $translation->getNormalized();
  }

  /**
   * Get the preferred provider if configured, else take the default one.
   *
   * @return array
   *   An array with the model and provider.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *   An exception.
   */
  private static function getProvider() {
    /** @var \Drupal\ai\AiProviderPluginManager $aiProviderManager */
    $aiProviderManager = \Drupal::service('ai.provider');
    $config = \Drupal::config('ai_translate_textfield.settings');
    $provider_id = $config->get('provider');
    $model_id = $config->get('provider') ?? '';
    if (!empty($provider_id)) {
      $provider = $aiProviderManager->loadProviderFromSimpleOption($provider_id . '__' . $model_id);
    }
    else {
      // Get the default provider.
      $default_provider = $aiProviderManager->getDefaultProviderForOperationType('translate_text');
      if (empty($default_provider['provider_id'])) {
        return NULL;
      }
      $provider = $aiProviderManager->createInstance($default_provider['provider_id']);
    }
    return $provider;
  }

}
