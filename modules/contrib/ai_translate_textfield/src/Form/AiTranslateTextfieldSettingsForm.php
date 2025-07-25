<?php

namespace Drupal\ai_translate_textfield\Form;

use Drupal\ai\AiProviderPluginManager;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Settings for the ai_translate_textfield module.
 */
class AiTranslateTextfieldSettingsForm extends ConfigFormBase {

  /**
   * The AI Provider service.
   */
  protected ?AiProviderPluginManager $aiProviderManager;

  /**
   * The language manager.
   */
  protected ?LanguageManagerInterface $languageManager;

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->aiProviderManager = $container->get('ai.provider');
    $instance->languageManager = $container->get('language_manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ai_translate_textfield_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['ai_translate_textfield.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $config = $this->config('ai_translate_textfield.settings');
    $providers = [];
    $provider_options = [];
    $models = [];
    $selected_provider = $form_state->getValue('provider') ?? $config->get('provider');
    $available_models = [];
    foreach ($this->aiProviderManager->getProvidersForOperationType('translate_text') as $id => $definition) {
      $providers[$id] = $this->aiProviderManager->createInstance($id);
      $provider_options[$id] = $definition['label'];
      $available_models = $models[$id] = $providers[$id]->getConfiguredModels('translate_text', []);
    }

    $form['options'] = [
      '#title' => $this->t('Generic options'),
      '#type' => 'fieldset',
    ];

    $form['options']['provider'] = [
      '#type' => 'select',
      '#required' => TRUE,
      '#title' => $this->t('Translator service provider'),
      '#description' => $this->t('If you see no options here, it means that no providers are installed/configured. See <a href="@config_url">the configuration page</a> for providers. A known working provider for text translation operation is <a href="@deepl_provider_url">DeepL Provider</a>.', [
        '@config_url' => Url::fromRoute('ai.admin_providers')->toString(),
        '@deepl_provider_url' => 'https://www.drupal.org/project/ai_provider_deepl',
      ]),
      '#options' => $provider_options,
      '#default_value' => $selected_provider,
      '#empty_option' => $this->t('- Select -'),
      '#ajax' => [
        'callback' => '::loadModels',
        'wrapper' => 'model-wrapper',
      ],
      '#limit_validation_errors' => [],
    ];

    $form['options']['model_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'model-wrapper',
      ],
    ];
    $form['options']['model_wrapper']['model'] = [
      '#type' => 'select',
      '#title' => $this->t("Translator service provider's model"),
      '#empty_option' => $this->t('- Select -'),
      '#options' => $models[$selected_provider] ?? $available_models,
      '#default_value' => $config->get('model'),
      '#states' => [
        'visible' => [
          ':input[name="provider"]' => ['!value' => ''],
        ],
      ],
    ];

    $form['options']['button_text'] = [
      '#type' => 'textfield',
      '#default_value' => $config->get('button_text'),
      '#title' => $this->t('Button text'),
      '#description' => $this->t('The text shown on the translate buttons added to the form elements.'),
    ];

    $form['options']['warning_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Warning modal enabled'),
      '#description' => $this->t('If this is enabled, a warning dialog with following texts is popped up before sending the data to the translator backend.'),
      '#default_value' => $config->get('warning_enabled'),
    ];

    $form['options']['warning_modal'] = [
      '#title' => $this->t('Warning modal dialog'),
      '#type' => 'fieldset',
      '#states' => [
        'visible' => [
          ':input[name="warning_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['options']['warning_modal']['dialog_title'] = [
      '#type' => 'textfield',
      '#default_value' => $config->get('dialog_title'),
      '#title' => $this->t('The title text of the dialog'),
    ];

    $form['options']['warning_modal']['dialog_content'] = [
      '#type' => 'text_format',
      '#default_value' => $config->get('dialog_content'),
      '#title' => $this->t('The text content of the dialog'),
      '#default_value' => $config->get('dialog_content')['value'] ?? '',
      '#format' => $config->get('dialog_content')['format'] ?? filter_default_format(),
    ];

    $form['options']['warning_modal']['dialog_ok_button'] = [
      '#type' => 'textfield',
      '#default_value' => $config->get('dialog_ok_button'),
      '#title' => $this->t('Ok button text'),
      '#description' => $this->t('The text shown on the "Ok" button which makes the text to be sent to the translator.'),
    ];

    $form['options']['warning_modal']['dialog_cancel_button'] = [
      '#type' => 'textfield',
      '#default_value' => $config->get('dialog_cancel_button'),
      '#title' => $this->t('Cancel button text'),
      '#description' => $this->t('The text shown on the "Cancel" button which closes the modal dialog.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('ai_translate_textfield.settings')
      ->set('provider', $form_state->getValue('provider'))
      ->set('model', $form_state->getValue('model'))
      ->set('button_text', $form_state->getValue('button_text'))
      ->set('strip_tags', $form_state->getValue('strip_tags'))
      ->set('warning_enabled', $form_state->getValue('warning_enabled'))
      ->set('dialog_title', $form_state->getValue('dialog_title'))
      ->set('dialog_content', [
        'value' => $form_state->getValue('dialog_content')['value'],
        'format' => $form_state->getValue('dialog_content')['format'],
      ])
      ->set('dialog_ok_button', $form_state->getValue('dialog_ok_button'))
      ->set('dialog_cancel_button', $form_state->getValue('dialog_cancel_button'))
      ->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * Ajax callback to load models.
   */
  public function loadModels(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $provider_id = $form_state->getValue($trigger['#name']);
    if (empty($provider_id)) {
      return $form['options']['model_wrapper'];
    }
    $models = $this->aiProviderManager->createInstance($provider_id)->getConfiguredModels('translate_text', []);
    $form['options']['model_wrapper']['model']['#options'] = $models;
    return $form['options']['model_wrapper'];
  }

}
