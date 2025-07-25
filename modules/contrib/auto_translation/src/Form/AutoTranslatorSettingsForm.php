<?php

namespace Drupal\auto_translation\Form;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Xss;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form with auto_translation on how to use cron.
 */
class AutoTranslatorSettingsForm extends ConfigFormBase {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The state keyvalue collection.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Module handler service object.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory, TypedConfigManagerInterface $typedConfigManager, AccountInterface $current_user, StateInterface $state, EntityTypeManagerInterface $entity_type_manager, ModuleHandlerInterface $moduleHandler) {
    parent::__construct($config_factory, $typedConfigManager);
    $this->currentUser = $current_user;
    $this->state = $state;
    $this->entityTypeManager = $entity_type_manager;
    $this->moduleHandler = $moduleHandler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $form = new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('current_user'),
      $container->get('state'),
      $container->get('entity_type.manager'),
      $container->get('module_handler')
    );
    $form->setMessenger($container->get('messenger'));
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'auto_translation';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->configFactory->get('auto_translation.settings');
    $node_options = [];
    $media_options = [];
    $media_options_default = [];
    $node_options_default = [];
    $blocks_options = [];
    $blocks_options_default = [];
    $taxonomy_options = [];
    $taxonomy_options_default = [];
    $webform_options = [];
    $webform_options_default = [];

    $form['#attached']['library'][] = 'auto_translation/auto_translation.admin';

    $nodes_types = $this->entityTypeManager
      ->getStorage('node_type')
      ->loadMultiple();
    foreach ($nodes_types as $type) {
      $node_options[$type->id()] = Html::escape($type->label()) . ' (' . $this->t('Node') . ')';
      $node_options_default[$type->id()] = $type->id();
    }

    if ($this->moduleHandler->moduleExists('media')) {
      $media_types = $this->entityTypeManager
        ->getStorage('media_type')
        ->loadMultiple();
      if ($media_types) {
        foreach ($media_types as $type) {
          if ($type->id()) {
            $media_options[$type->id()] = Html::escape($type->label()) . ' (' . $this->t('Media') . ')';
            $media_options_default[$type->id()] = $type->id();
          }
        }
        $node_options_default = array_merge($media_options_default, $node_options_default);
        $node_options = array_merge($media_options, $node_options);
      }
    }
    if ($this->moduleHandler->moduleExists('block_content')) {
      $block_types = $this->entityTypeManager
        ->getStorage('block_content_type')
        ->loadMultiple();
      if ($block_types) {
        foreach ($block_types as $type) {
          if ($type->id()) {
            $blocks_options[$type->id()] = Html::escape($type->label()) . ' (' . $this->t('Block') . ')';
            $blocks_options_default[$type->id()] = $type->id();
          }
        }
        $node_options_default = array_merge($blocks_options_default, $node_options_default);
        $node_options = array_merge($blocks_options, $node_options);
      }
    }

    if ($this->moduleHandler->moduleExists('taxonomy')) {
      $taxonomy_types = $this->entityTypeManager
        ->getStorage('taxonomy_vocabulary')
        ->loadMultiple();
      if ($taxonomy_types) {
        foreach ($taxonomy_types as $type) {
          if ($type->id()) {
            $taxonomy_options[$type->id()] = Html::escape($type->label()) . ' (' . $this->t('Taxonomy') . ')';
            $taxonomy_options_default[$type->id()] = $type->id();
          }
        }
        $node_options_default = array_merge($taxonomy_options_default, $node_options_default);
        $node_options = array_merge($taxonomy_options, $node_options);
      }
    }
    if ($this->moduleHandler->moduleExists('webform')) {
      $webform_types = $this->entityTypeManager
        ->getStorage('webform')
        ->loadMultiple();
      if ($webform_types) {
        foreach ($webform_types as $type) {
          if ($type->id()) {
            $webform_options[$type->id()] = Html::escape($type->label()) . ' (' . $this->t('Webform') . ')';
            $webform_options_default[$type->id()] = $type->id();
          }
        }
        $node_options_default = array_merge($webform_options_default, $node_options_default);
        $node_options = array_merge($webform_options, $node_options);
      }
    }

    if (!empty($config->get('auto_translation_content_types'))) {
      $enabled_content_type = $config->get('auto_translation_content_types');
    }
    else {
      $enabled_content_type = $node_options_default;
    }

    $form['configuration_nodes'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Auto Translation - configuration'),
      '#open' => TRUE,
    ];

    $form['configuration_nodes']['providers_list'] = [
      '#type' => 'details',
      '#title' => $this->t('Auto Translation - Providers configuration'),
      '#open' => TRUE,
    ];

    $form['configuration_nodes']['content_types_list'] = [
      '#type' => 'details',
      '#title' => $this->t('Auto Translation - Content types configuration'),
      '#open' => TRUE,
    ];

    $form['configuration_nodes']['intro'] = [
      '#type' => 'item',
      '#markup' => $this->t('You can select content types where enable the auto translation, by default is enabled on all content types with Google Translate browser Free API.'),
      '#weight' => -10,
    ];

    $provider_options = [
      'google' => $this->t('Google Translate API'),
      'libretranslate' => $this->t('Libre Translate API'),
      'deepl' => $this->t('DeepL API'),
    ];
    if ($this->moduleHandler->moduleExists('ai') && $this->moduleHandler->moduleExists('ai_translate')) {
      $provider_options['drupal_ai'] = $this->t('Drupal AI');
      if (!\Drupal::service('ai.provider')->hasProvidersForOperationType('chat', TRUE) && !\Drupal::service('ai.provider')->hasProvidersForOperationType('translate', TRUE)) {
        $url = UrlHelper::stripDangerousProtocols('/admin/config/ai/providers');
        $markupError = $this->t('To use Drupal AI to translate you need to configure a provider for the operation type "translate" or "chat" in the <a href=":url" target="_blank">Drupal AI module providers section</a>.', [':url' => $url]);
        \Drupal::messenger()->addWarning($markupError);
      }
    }
    else {
      $url = UrlHelper::stripDangerousProtocols('https://www.drupal.org/project/ai');
      $markupError = $this->t('To use Drupal AI provider you must install the <a href=":url" target="_blank">Drupal AI module</a> to enable AI translation module and configure it.', [':url' => $url]);
      \Drupal::messenger()->addWarning($markupError);
    }
    $form['configuration_nodes']['providers_list']['auto_translation_provider'] = [
      '#title' => $this->t('Translator Provider'),
      '#type' => 'select',
      '#description'   => $this->t('Select auto translator Provider'),
      '#options' => $provider_options,
      '#default_value' => $config->get('auto_translation_provider') ? $config->get('auto_translation_provider') : 'google',
      '#required'      => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="server_side_poc"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form['configuration_nodes']['content_types_list']['auto_translation_content_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Enabled Content Types'),
      '#description' => $this->t('Define what content types will be enabled for content auto translation.'),
      '#default_value' => $enabled_content_type,
      '#options' => $node_options,
    ];

    $form['configuration_nodes']['content_types_list']['auto_translation_excluded_fields'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Excluded fields'),
      '#description' => $this->t('Define what fields will be excluded from content auto translation, separated by comma.'),
      '#default_value' => $config->get('auto_translation_excluded_fields'),
      '#placeholder' => $this->t('field_name_1, field_name_2, field_name_3'),
    ];
    $form['configuration_nodes']['content_types_list']['auto_translation_bulk_publish'] = [
      '#type' => 'radios',
      '#title' => $this->t('Bulk Translations - Publish Mode'),
      '#description' => $this->t('Choose whether translated Node and Media entities in bulk should be published or saved as draft.'),
      '#options' => [
        'published' => $this->t('Published'),
        'draft' => $this->t('Draft'),
      ],
      '#default_value' => $config->get('auto_translation_bulk_publish') ?? 'draft',
    ];
    $form['configuration_nodes']['api_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Auto Translation - API configuration'),
      '#open' => TRUE,
      '#states' => [
        'visible' => [
          [
            ':input[name="auto_translation_provider"]' => ['value' => 'google'],
          ],
          'and',
          [
            ':input[name="auto_translation_provider"]' => ['value' => 'libretranslate'],
          ],
          'and',
          [
            ':input[name="auto_translation_provider"]' => ['value' => 'deepl'],
          ],
        ],
      ],
    ];
    $form['configuration_nodes']['api_settings']['auto_translation_api_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Server Side API'),
      '#description' => $this->t('Enable server side API for content auto translation, if unchecked Google Translate browser Free API will be used.'),
      '#default_value' => $config->get('auto_translation_api_enabled'),
      '#states' => [
        'visible' => [
          ':input[name="auto_translation_provider"]' => ['value' => 'google'],
        ],
      ],
    ];

    $form['configuration_nodes']['api_settings']['auto_translation_api_deepl_pro_mode'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use DeepL Pro API'),
      '#description' => $this->t('If checked, the DeepL Pro API will be used. If unchecked, the free version of DeepL will be used.'),
      '#default_value' => (bool) $config->get('auto_translation_api_deepl_pro_mode'),
      '#states' => [
        'visible' => [
          ':input[name="auto_translation_provider"]' => ['value' => 'deepl'],
        ],
      ],
    ];

    $form['configuration_nodes']['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Auto Translation - Advanced configuration'),
      '#open' => TRUE,
    ];
    $form['configuration_nodes']['advanced']['enable_debug'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Debugging'),
      '#description' => $this->t('Enable debugging for auto translation. This will log additional information to the watchdog.'),
      '#default_value' => $config->get('enable_debug') ?? FALSE,
    ];

    $translationUtility = \Drupal::service('auto_translation.utility');
    $encryptedApiKey = $config->get('auto_translation_api_key');

    if ($encryptedApiKey) {
      $apiKey = $translationUtility->decryptApiKey($encryptedApiKey);
    }
    else {
      $apiKey = '';
    }

    $form['configuration_nodes']['api_settings']['auto_translation_api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Key'),
      '#description' => $this->t('Enter your API key.'),
      '#default_value' => $apiKey,
      '#states' => [
        'visible' => [
          [':input[name="auto_translation_api_enabled"]' => ['checked' => TRUE]],
          'and',
          [
            ':input[name="auto_translation_provider"]' => ['value' => 'libretranslate'],
          ],
          'and',
          [
            ':input[name="auto_translation_provider"]' => ['value' => 'deepl'],
          ],
        ],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $apiKey = $form_state->getValue('auto_translation_api_key');
    if (($form_state->getValue('auto_translation_api_enabled') || $form_state->getValue('auto_translation_provider') === 'deepl' || $form_state->getValue('auto_translation_provider') === "libretranslate") && empty($apiKey)) {
      $form_state->setErrorByName('auto_translation_api_key', $this->t('API Key is required.'));
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Update the values as stored in configuration. This will be read when.
    $translationUtility = \Drupal::service('auto_translation.utility');
    // Sanitize API key input
    $plainApiKey = Html::escape($form_state->getValue('auto_translation_api_key'));
    $encryptedApiKey = $translationUtility->encryptApiKey($plainApiKey);

    // Sanitize excluded fields input (comma-separated list)
    $excludedFields = Html::escape($form_state->getValue('auto_translation_excluded_fields'));

    // Sanitize provider selection (should be one of the allowed values)
    $provider = Html::escape($form_state->getValue('auto_translation_provider'));

    $this->configFactory->getEditable('auto_translation.settings')
      ->set('interval', $form_state->getValue('auto_translation_interval'))
      ->set('auto_translation_content_types', $form_state->getValue('auto_translation_content_types'))
      ->set('auto_translation_api_enabled', (bool) $form_state->getValue('auto_translation_api_enabled'))
      ->set('auto_translation_api_key', $encryptedApiKey)
      ->set('auto_translation_api_deepl_pro_mode', (bool) $form_state->getValue('auto_translation_api_deepl_pro_mode'))
      ->set('auto_translation_excluded_fields', $excludedFields)
      ->set('auto_translation_provider', $provider)
      ->set('auto_translation_bulk_publish', Html::escape($form_state->getValue('auto_translation_bulk_publish')))
      ->set('enable_debug', (bool) $form_state->getValue('enable_debug'))
      ->save();

    $this->updateTranslationAction($form_state->getValue('auto_translation_bulk_publish'));

    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['auto_translation.settings'];
  }

  /**
   * Update the translation action based on the publish mode.
   */
  protected function updateTranslationAction($publish_mode) {
    // Sanitize the publish mode parameter
    $publish_mode = Html::escape($publish_mode);

    $storage = $this->entityTypeManager->getStorage('action');
    $action_id = 'system.action.auto_translation_bulk_auto_translate';

    // Try to load the existing action.
    $action = $storage->load($action_id);

    if ($action) {
      $action->delete();
    }

    // Create a new action if it doesn't exist.
    $action = $storage->create([
      'id' => $action_id,
      'label' => ($publish_mode === 'published') ? $this->t('Auto Translate node/s and Publish') : $this->t('Auto Translate node/s'),
      'type' => 'node',
      'plugin' => ($publish_mode === 'published') ? 'auto_translation_bulk_auto_translate_publish_action' : 'auto_translation_bulk_auto_translate_draft_action',
      'status' => TRUE,
      'dependencies' => [
        'enforced' => [
          'module' => [
            'auto_translation',
          ],
        ],
      ],
    ]);

    // Check if the media module is enabled and update the action accordingly.
    if ($this->moduleHandler->moduleExists('media')) {
      $media_action_id = 'media.action.auto_translation_bulk_auto_translate';
      $media_action = $storage->load($media_action_id);
      if ($media_action) {
        $media_action->delete();
      }

      // Create a new action if it doesn't exist.
      $media_action = $storage->create([
        'id' => $media_action_id,
        'label' => ($publish_mode === 'published') ? $this->t('Auto Translate media and Publish') : $this->t('Auto Translate media'),
        'type' => 'media',
        'plugin' => ($publish_mode === 'published') ? 'auto_translation_bulk_auto_translate_publish_action' : 'auto_translation_bulk_auto_translate_draft_action',
        'status' => TRUE,
        'dependencies' => [
          'enforced' => [
            'module' => [
              'auto_translation',
            ],
          ],
        ],
      ]);

      $media_action->save();
    }
    // Save the updated action.
    $action->save();
  }

}
