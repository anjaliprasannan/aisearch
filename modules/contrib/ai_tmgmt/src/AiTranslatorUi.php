<?php

namespace Drupal\ai_tmgmt;

use Drupal\Core\Form\FormStateInterface;
use Drupal\tmgmt\TranslatorPluginUiBase;

/**
 * AI translator UI.
 */
class AiTranslatorUi extends TranslatorPluginUiBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);

    // Ignore service loading issues to avoid making it harder to extend and
    // accept changes from TMGMT module.
    /** @var \Drupal\ai\AiProviderPluginManager $provider_manager */
    // @phpstan-ignore-next-line
    $provider_manager = \Drupal::service('ai.provider');
    $chat_models = $provider_manager->getSimpleProviderModelOptions('chat');
    /** @var \Drupal\ai\Utility\TokenizerInterface $tokenizer */
    // @phpstan-ignore-next-line
    $tokenizer = \Drupal::service('ai.tokenizer');

    /** @var \Drupal\tmgmt\TranslatorInterface $translator */
    $translator = $form_state->getFormObject()->getEntity();
    $settings = $translator->getSettings();
    $form['model_selection_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Translation configuration'),
      '#required' => TRUE,
      '#options' => [
        'ai_translate' => $this->t('Use the configuration from the "AI Translate" module (recommended)'),
        'ai_tmgmt' => $this->t('Use the configuration from this module (basic)'),
      ],
      '#default_value' => $settings['model_selection_type'] ?? '',
      '#description' => $this->t('This module provides a single basic prompt for all languages. The "AI Translate" module however allows you to configure the prompt per language as well as use different models per language (since some models are better trained on specific languages than others). Please enable the "AI Translate" sub-module of the AI core module.'),
    ];
    $form['chat_model'] = [
      '#type' => 'select',
      '#title' => $this->t('Chat translator model'),
      '#required' => TRUE,
      '#options' => $chat_models,
      '#default_value' => $settings['chat_model'] ?? '',
      '#description' => $this->t('Choose a chat model that is known to operate well with the languages you typically use.'),
      '#states' => [
        'visible' => [
          ':input[name="settings[model_selection_type]"]' => ['value' => 'ai_tmgmt'],
        ],
      ],
    ];
    $form['tokenizer_model'] = [
      '#type' => 'select',
      '#title' => $this->t('Tokenizer counting model'),
      '#required' => TRUE,
      '#options' => $tokenizer->getSupportedModels(),
      '#default_value' => $settings['tokenizer_model'] ?? '',
      '#description' => $this->t('Typically you should choose the same model as the translator if available for the most accurate counting. If unavailable, GPT-3.5 is a good generic count for accurate counting. This is to ensure you do not exceed limits of your model.'),
    ];
    $form['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced'),
    ];
    $form['advanced']['prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Prompt'),
      '#default_value' => !empty($settings['advanced']['prompt']) ? $settings['advanced']['prompt'] : "Translate from %source% into %target% language",
      '#description' => $this->t('Prompt of the system role. \n\nYou can adjust tone of the translated text and/or adjust style of the text. Supported tokens: <strong>%source%</strong> - source language (translate from), <strong>%target%</strong> - target language (translate to)'),
      '#placeholder' => $this->t('Translate from %source% into %target% language'),
      '#required' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="settings[model_selection_type]"]' => ['value' => 'ai_tmgmt'],
        ],
      ],
    ];
    $form['advanced']['max_tokens'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum length'),
      '#description' => $this->t('The maximum number of tokens to generate. Requests can use up to 16,384 or 32,768 tokens shared between prompt and completion. The exact limit varies by model. (One token is roughly 4 characters for normal English text)'),
      '#min' => 200,
      '#default_value' => !empty($settings['advanced']['max_tokens']) ? $settings['advanced']['max_tokens'] : 4096,
    ];
    $form += parent::addConnectButton();
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    if (
      $form_state->getValue('settings')['model_selection_type'] === 'ai_translate'
      // @codingStandardsIgnoreStart
      // @phpstan-ignore-next-line
      && !\Drupal::hasService('ai_translate.text_translator')
      // @codingStandardsIgnoreEnd
    ) {
      $form_state->setErrorByName('settings][model_selection_type', $this->t('Please enable the "ai_translate" sub-module of the "ai" module.'));
    }
    parent::validateConfigurationForm($form, $form_state);
  }

}
