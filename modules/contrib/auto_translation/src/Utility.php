<?php

namespace Drupal\auto_translation;

use Google\Cloud\Translate\V2\TranslateClient;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\Markup;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Xss;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Utility class for auto_translation module functions.
 *
 * @package Drupal\auto_translation
 */
class Utility {

  use StringTranslationTrait;
  use LoggerChannelTrait;

  /**
   * The config object.
   *
   * @var Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The http client object.
   *
   * @var GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The message interface object.
   *
   * @var Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The language interface object.
   *
   * @var Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The module handler object.
   *
   * @var Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheBackend;

  /**
   * The logger channel factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Constructs a new Utility object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The cache backend service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    ClientInterface $http_client,
    MessengerInterface $messenger,
    LanguageManagerInterface $language_manager,
    ModuleHandlerInterface $module_handler,
    CacheBackendInterface $cache_backend,
    LoggerChannelFactoryInterface $logger_factory
  ) {
    $this->configFactory = $config_factory;
    $this->httpClient = $http_client;
    $this->messenger = $messenger;
    $this->languageManager = $language_manager;
    $this->moduleHandler = $module_handler;
    $this->cacheBackend = $cache_backend;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * Dependency injection via create().
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('http_client'),
      $container->get('messenger'),
      $container->get('language_manager'),
      $container->get('module_handler'),
      $container->get('cache.default'),
      $container->get('logger.factory')
    );
  }

  /**
   * Translates the given text from the source language to the target language.
   *
   * @param string $text
   *   The text to be translated.
   * @param string $s_lang
   *   The source language code.
   * @param string $t_lang
   *   The target language code.
   *
   * @return string|null
   *   The translated text or NULL if translation fails.
   */
  public function translate($text, $s_lang, $t_lang) {
    $config = $this->configFactory->get('auto_translation.settings');
    $provider = $config->get('auto_translation_provider') ?? 'google';
    $api_enabled = $config->get('auto_translation_api_enabled') ?? NULL;
    $translation = NULL;

    // Check if text contains HTML
    $hasHtml = strip_tags($text) !== $text;

    // For HTML content, pass unescaped text to provider methods
    // They will handle HTML processing internally via translateHtmlContent
    if ($hasHtml) {
      $processedText = $text;
    } else {
      // For plain text, escape as needed for security
      if ($provider === 'google' && !$api_enabled) {
        // Don't escape for Google browser call - it handles plain text well
        $processedText = $text;
      } else {
        $processedText = Html::escape($text);
      }
    }

    switch ($provider) {
      case 'google':
        $translation = $api_enabled ? $this->translateApiServerCall($processedText, $s_lang, $t_lang) : $this->translateApiBrowserCall($processedText, $s_lang, $t_lang);
        break;

      case 'libretranslate':
        $translation = $this->libreTranslateApiCall($processedText, $s_lang, $t_lang);
        break;

      case 'deepl':
        $translation = $this->deeplTranslateApiCall($processedText, $s_lang, $t_lang);
        break;

      case 'drupal_ai':
        if ($this->moduleHandler->moduleExists('ai') && $this->moduleHandler->moduleExists('ai_translate')) {
          $translation = $this->drupalAiTranslateApiCall($processedText, $s_lang, $t_lang);
        }
        else {
          $this->messenger->addError($this->t('AI translation module is not installed.'));
        }
        break;
    }

    return $translation;
  }

  /**
   * Translates the given text using the API deepl translate server.
   *
   * @param string $text
   *   The text to be translated.
   * @param string $s_lang
   *   The source language of the text.
   * @param string $t_lang
   *   The target language for the translation.
   *
   * @return string
   *   The translated text.
   */
  public function deeplTranslateApiCall($text, $s_lang, $t_lang) {
    $config = $this->configFactory->get('auto_translation.settings');
    $translation = NULL;
    $logger = $this->loggerFactory->get('auto_translation');
    $enable_debug = $config->get('enable_debug');

    // Check if text contains HTML
    $hasHtml = strip_tags($text) !== $text;

    if ($enable_debug) {
      $logger->info('DeepL API Call - HTML content detected: @has_html', [
        '@has_html' => $hasHtml ? 'YES' : 'NO',
      ]);
    }

    if ($hasHtml) {
      // For HTML content, use translateHtmlContent with deepl provider
      if ($enable_debug) {
        $logger->info('DeepL API Call - Using translateHtmlContent for HTML processing');
      }
      $translation = $this->translateHtmlContent($text, $s_lang, $t_lang, 'deepl');
    } else {
      // For plain text, use the direct API call
      if ($enable_debug) {
        $logger->info('DeepL API Call - Using direct API for plain text');
      }
      $deeplMode = $config->get('auto_translation_api_deepl_pro_mode') === false ? 'api-free' : 'api';
      $endpoint = sprintf('https://%s.deepl.com/v2/translate', $deeplMode);
      $encryptedApiKey = $config->get('auto_translation_api_key');
      $apiKey = $this->decryptApiKey($encryptedApiKey);

      $options = [
        'headers' => [
          'Authorization' => 'DeepL-Auth-Key ' . $apiKey,
          'Content-Type' => 'application/json',
        ],
        'json' => [
          'text' => [$text],
          'source_lang' => $s_lang,
          'target_lang' => $t_lang,
        ],
        'verify' => FALSE,
      ];

      try {
        $response = $this->httpClient->post($endpoint, $options);
        $result = Json::decode($response->getBody()->getContents());
        $translation = htmlspecialchars_decode($result['translations'][0]['text']) ?? NULL;
      }
      catch (RequestException $e) {
        $this->getLogger('auto_translation')->error('Translation API error: @error', ['@error' => $e->getMessage()]);
        $this->messenger->addError($this->t('Translation API error: @error', ['@error' => $e->getMessage()]));
      }
    }

    return $translation;
  }

  /**
   * Translates the given text using the API libre translate server.
   *
   * @param string $text
   *   The text to be translated.
   * @param string $s_lang
   *   The source language of the text.
   * @param string $t_lang
   *   The target language for the translation.
   *
   * @return string
   *   The translated text.
   */
  public function libreTranslateApiCall($text, $s_lang, $t_lang) {
    $config = $this->configFactory->get('auto_translation.settings');
    $translation = NULL;
    $logger = $this->loggerFactory->get('auto_translation');
    $enable_debug = $config->get('enable_debug');

    // Check if text contains HTML
    $hasHtml = strip_tags($text) !== $text;

    if ($enable_debug) {
      $logger->info('LibreTranslate API Call - HTML content detected: @has_html', [
        '@has_html' => $hasHtml ? 'YES' : 'NO',
      ]);
    }

    if ($hasHtml) {
      // For HTML content, use translateHtmlContent with libretranslate provider
      if ($enable_debug) {
        $logger->info('LibreTranslate API Call - Using translateHtmlContent for HTML processing');
      }
      $translation = $this->translateHtmlContent($text, $s_lang, $t_lang, 'libretranslate');
    } else {
      // For plain text, use the direct API call
      if ($enable_debug) {
        $logger->info('LibreTranslate API Call - Using direct API for plain text');
      }
      $endpoint = 'https://libretranslate.com/translate';
      $encryptedApiKey = $config->get('auto_translation_api_key');
      $apiKey = $this->decryptApiKey($encryptedApiKey);

      $options = [
        'headers' => ['Content-Type' => 'application/json'],
        'json' => [
          'q' => $text,
          'source' => $s_lang,
          'target' => $t_lang,
          'format' => 'text',
          'api_key' => $apiKey,
        ],
        'verify' => FALSE,
      ];

      try {
        $response = $this->httpClient->post($endpoint, $options);
        $result = Json::decode($response->getBody()->getContents());
        $translation = $result['translatedText'] ?? NULL;
      }
      catch (RequestException $e) {
        $this->getLogger('auto_translation')->error('Translation API error: @error', ['@error' => $e->getMessage()]);
        $this->messenger->addError($this->t('Translation API error: @error', ['@error' => $e->getMessage()]));
      }
    }

    return $translation;
  }

  /**
   * Translates the given text using the API Drupal AI translate server.
   *
   * @param string $text
   *   The text to be translated.
   * @param string $s_lang
   *   The source language of the text.
   * @param string $t_lang
   *   The target language for the translation.
   *
   * @return string
   *   The translated text.
   */
  public function drupalAiTranslateApiCall($text, $s_lang, $t_lang) {
    $logger = $this->getLogger('auto_translation');
    $translation = NULL;

    // Load debug configuration
    $config = $this->configFactory->get('auto_translation.settings');
    $enable_debug = $config->get('enable_debug');

    if (!$this->moduleHandler->moduleExists('ai') && !$this->moduleHandler->moduleExists('ai_translate')) {
      $logger->error('Auto translation error: AI Module not installed please install Drupal AI module');
      return [
        '#type' => 'markup',
        '#markup' => $this->t('AI Module not installed please install Drupal AI and Drupal AI Translate modules'),
      ];
    }
    if (!\Drupal::service('ai.provider')->hasProvidersForOperationType('chat', TRUE) && !\Drupal::service('ai.provider')->hasProvidersForOperationType('translate', TRUE)) {
      $markupError = $this->t('To use Drupal AI to translate you need to configure a provider for the operation type "translate" or "chat" in the <a href=":url" target="_blank">Drupal AI module providers section</a>.', [':url' => '/admin/config/ai/providers']);
      return [
        '#type' => 'markup',
        '#markup' => $markupError,
      ];
    }

    // Check if text contains HTML
    $hasHtml = strip_tags($text) !== $text;

    if ($enable_debug) {
      $logger->info('Drupal AI API Call - HTML content detected: @has_html', [
        '@has_html' => $hasHtml ? 'YES' : 'NO',
      ]);
    }

    if ($hasHtml) {
      // For HTML content, use translateHtmlContent with drupal_ai provider
      if ($enable_debug) {
        $logger->info('Drupal AI API Call - Using translateHtmlContent for HTML processing');
      }
      $translation = $this->translateHtmlContent($text, $s_lang, $t_lang, 'drupal_ai');
    } else {
      // For plain text, use the direct API call
      if ($enable_debug) {
        $logger->info('Drupal AI API Call - Using direct AI service for plain text');
      }
      $container = $this->getContainer();
      $languageManager = $container->get('language_manager');
      $langFrom = $languageManager->getLanguage($s_lang);
      $langTo = $languageManager->getLanguage($t_lang);

      try {
        $translatedText = \Drupal::service('ai_translate.text_translator')->translateContent($text, $langTo, $langFrom);
        return $translatedText;
      }
      catch (RequestException $exception) {
        $logger->error('Auto translation error: @error', ['@error' => json_encode($exception->getMessage())]);
        $this->getMessages($exception->getMessage());
        return $exception;
      }
    }

    return $translation;
  }

  /**
   * Translates the given text using the API server.
   *
   * @param string $text
   *   The text to be translated.
   * @param string $s_lang
   *   The source language of the text.
   * @param string $t_lang
   *   The target language for the translation.
   *
   * @return string
   *   The translated text.
   */

  /**
   * Calls the Google API to translate text using server-side key.
   */
  public function translateApiServerCall($text, $s_lang, $t_lang) {
    $config = $this->configFactory->get('auto_translation.settings');
    $translation = NULL;
    $logger = $this->loggerFactory->get('auto_translation');
    $enable_debug = $config->get('enable_debug');

    // Check if text contains HTML
    $hasHtml = strip_tags($text) !== $text;

    if ($enable_debug) {
      $logger->info('Google Server API Call - HTML content detected: @has_html', [
        '@has_html' => $hasHtml ? 'YES' : 'NO',
      ]);
    }

    if ($hasHtml) {
      // For HTML content, use translateHtmlContent with google provider
      if ($enable_debug) {
        $logger->info('Google Server API Call - Using translateHtmlContent for HTML processing');
      }
      $translation = $this->translateHtmlContent($text, $s_lang, $t_lang, 'google');
    } else {
      // For plain text, use the direct API call
      if ($enable_debug) {
        $logger->info('Google Server API Call - Using direct server API for plain text');
      }
      $encryptedApiKey = $config->get('auto_translation_api_key');
      $apiKey = $this->decryptApiKey($encryptedApiKey);
      // Create a new TranslateClient object.
      $client = new TranslateClient([
        'key' => $apiKey,
      ]);

      try {
        $result = $client->translate($text, ['source' => $s_lang, 'target' => $t_lang]);
        $translation = htmlspecialchars_decode($result['text']);
      }
      catch (RequestException $e) {
        $this->getLogger('auto_translation')->error('Auto translation error: @error', ['@error' => $e->getMessage()]);
        $this->messenger->addError($this->t('Translation API error: @error', ['@error' => $e->getMessage()]));
      }
    }

    return $translation;
  }

  /**
   * Translates the given text using the API browser call.
   *
   * @param string $text
   *   The text to be translated.
   * @param string $s_lang
   *   The source language of the text.
   * @param string $t_lang
   *   The target language for the translation.
   *
   * @return string
   *   The translated text.
   */
  public function translateApiBrowserCall($text, $s_lang, $t_lang) {
    $translation = NULL;
    $logger = $this->loggerFactory->get('auto_translation');

    // Check if text contains HTML
    $hasHtml = strip_tags($text) !== $text;

    $logger->info('Google Browser API Call - HTML content detected: @has_html', [
      '@has_html' => $hasHtml ? 'YES' : 'NO',
    ]);

    if ($hasHtml) {
      // For HTML content, extract text segments and translate them separately
      $logger->info('Google Browser API Call - Using translateHtmlContent for HTML processing');
      $translation = $this->translateHtmlContent($text, $s_lang, $t_lang, 'google');
    } else {
      // For plain text, use the direct API call
      $logger->info('Google Browser API Call - Using direct browser API for plain text');
      $endpoint = 'https://translate.googleapis.com/translate_a/single?client=gtx&sl=' . $s_lang . '&tl=' . $t_lang . '&dt=t&q=' . rawurlencode($text);
      $options = ['verify' => FALSE];

      try {
        $response = $this->httpClient->get($endpoint, $options);
        $data = Json::decode($response->getBody()->getContents());

        $translation = '';
        foreach ($data[0] as $segment) {
          $translation .= htmlspecialchars_decode($segment[0]) ?? '';
        }
      }
      catch (RequestException $e) {
        $this->getLogger('auto_translation')->error('Translation API error: @error', ['@error' => $e->getMessage()]);
        $this->messenger->addError($this->t('Translation API error: @error', ['@error' => $e->getMessage()]));
      }
    }

    return $translation;
  }

  /**
   * Custom function to return saved resources.
   */
  public function getEnabledContentTypes() {
    $config = $this->config();
    $enabledContentTypes = $config->get('auto_translation_content_types') ? $config->get('auto_translation_content_types') : NULL;
    return $enabledContentTypes;
  }

  /**
   * Retrieves the excluded fields.
   *
   * @return array
   *   The excluded fields.
   */
  public function getExcludedFields() {
    $config = $this->config();
    $excludedFields = [
      '0',
      '1',
      '#access',
      'behavior_settings',
      'boolean',
      'changed',
      'code',
      'content_translation_outdated',
      'content_translation_source',
      'content_translation_status',
      'created',
      'datetime',
      'default_langcode',
      'draft',
      'language',
      'langcode',
      'moderation_state',
      'parent_field_name',
      'parent_type',
      'path',
      'promote',
      'published',
      'ready for review',
      'revision_timestamp',
      'revision_uid',
      'status',
      'sticky',
      'uid',
      'und',
      'unpublished',
      'uuid',
      NULL,
    ];
    $excludedFieldsSettings = $config->get('auto_translation_excluded_fields') ? $config->get('auto_translation_excluded_fields') : NULL;
    if ($excludedFieldsSettings) {
      $excludedFieldsSettings = explode(",", $excludedFieldsSettings);
      $excludedFields = array_merge($excludedFields, $excludedFieldsSettings);
    }
    return $excludedFields;
  }

  /**
   * Implements auto translation for an entity form.
   *
   * Automatically translates translatable fields and nested paragraph fields
   * when adding a translation for an entity.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return array
   *   The modified form array.
   */
  public function formTranslate(&$form = NULL, &$form_state = NULL, &$entity = NULL, &$t_lang = NULL, &$d_lang = NULL, &$action = NULL, &$chunk = NULL) {
    if ($this->hasPermission() === FALSE) {
      return;
    }
    $current_path = \Drupal::service('path.current')->getPath();
    $enabledContentTypes = $this->getEnabledContentTypes();

    // Retrieve the entity from the form state if not provided.
    if (!$entity && $form_state && $form_state->getFormObject()) {
      $form_object = $form_state->getFormObject();
      if ($form_object instanceof \Drupal\Core\Entity\EntityFormInterface) {
        $entity = $form_object->getEntity();
      }
    }
    if (!$entity || !$entity instanceof ContentEntityInterface) {
      $this->getLogger('auto_translation')->error($this->t('Translation error: Entity not found.'));
      return;
    }
    else {
      if (empty($form) && ($entity instanceof ContentEntityInterface)) {
        $batch = new BatchBuilder();
        // Set up the batch.
        $batch->setTitle($this->t('Processing entity auto translation in batch'))
          ->setInitMessage($this->t('Starting entity auto translation'))
          ->setProgressMessage($this->t('Processed @current out of @total.'))
          ->setErrorMessage($this->t('An error occurred during entity auto translation.'))
          ->setFinishCallback([get_class($this), 'batchFinishedCallback']);

        $entity_type = $entity->getEntityTypeId();
        if (!$entity->hasTranslation($t_lang)) {
          // Verify entity type of type 'node' or 'media'.
          if ($entity_type == 'node') {
            $title = $entity->get('title')->value;
            $t_title = $this->translate($title, $d_lang, $t_lang);
            $entity->addTranslation($t_lang, ['title' => $t_title]);
          }
          elseif ($entity_type == 'media') {
            $title = $entity->get('name')->value;
            $t_title = $this->translate($title, $d_lang, $t_lang);
            $entity->addTranslation($t_lang, ['name' => $t_title]);
          }
          else {
            // Handle other entity types if needed.
          }
        }
        else {
          // Prevent translation if it already exists.
          $this->getLogger('auto_translation')->notice($this->t('Translation error: Translation already exists.'));
          return;
        }
      }
    }

    if (!$entity instanceof ContentEntityInterface) {
      $this->getLogger('auto_translation')->error($this->t('Translation error: Entity is not a content entity.'));
      return;
    }

    $entity_type = $entity->getEntityTypeId();
    $bundle = $entity->bundle();
    $excludedFields = $this->getExcludedFields();
    $languageManager = \Drupal::service('language_manager');

    if ($enabledContentTypes && (strpos($current_path, 'translations/add') !== FALSE || ($entity && $t_lang && $d_lang)) && in_array($bundle, $enabledContentTypes)) {
      $fields = \Drupal::service('entity_field.manager')->getFieldDefinitions($entity_type, $bundle);
      $chunk_fields = array_chunk(range(1, 1000), count($fields));
      $t_lang = $t_lang ?: ($this->getTranslationLanguages($entity)['translated'] ?? $languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId());
      $d_lang = $d_lang ?: ($this->getTranslationLanguages($entity)['original'] ?? $languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId());
      // In batch mode, we work on the translation via batch operations.
      if (!$form && !$form_state) {
        // If the translation does not exist yet, add it.
        if (!$entity->hasTranslation($t_lang)) {
          // Verify entity type of type 'node' or 'media'.
          if ($entity_type == 'node') {
            $title = $entity->get('title')->value;
            $t_title = $this->translate($title, $d_lang, $t_lang);
            $entity->addTranslation($t_lang, ['title' => $t_title]);
          }
          elseif ($entity_type == 'media') {
            $title = $entity->get('name')->value;
            $t_title = $this->translate($title, $d_lang, $t_lang);
            $entity->addTranslation($t_lang, ['name' => $t_title]);
          }
          else {
            // Handle other entity types if needed.
          }
        }
        // Iterate over all fields to build batch operations.
        foreach ($fields as $field) {
          $field_name = $field->getName();
          $field_type = $field->getType();
          if (!in_array($field_name, $excludedFields)) {
            // Batch operation for simple translatable fields.
            if ($field->isTranslatable()) {
              $batch->addOperation(
                [self::class, 'batchTranslateField'],
                [$entity->id(), $entity_type, $field_name, $d_lang, $t_lang, $action]
              );
            }
            // Batch operation for paragraph fields.
            if ($this->isParagraphReference($field)) {
              if ($this->moduleHandler->moduleExists('paragraphs')) {
                $batch->addOperation(
                  [self::class, 'batchTranslateParagraphs'],
                  [$entity->id(), $entity_type, $field_name, $d_lang, $t_lang, $excludedFields, $action]
                );
              }
            }
          }
        }
        $batch->addOperation(
          [self::class, 'batchFinalizeEntity'],
          [$entity->id(), $entity_type, $t_lang, $action, $chunk_fields]
        );
        // Run the batch.
        $batch = $batch->toArray();

        batch_set($batch);
      }
      // Synchronous processing for form-based context.
      else {
        foreach ($fields as $field) {
          $field_name = $field->getName();
          $field_type = $field->getType();
          if ($field->isTranslatable() && !in_array($field_name, $excludedFields)) {
            if (isset($form[$field_name]['widget'])) {
              $this->translateField($entity, $form[$field_name]['widget'], $field_name, $field_type, $d_lang, $t_lang);
            }
            if (isset($form['name']) && !empty($form['name'])) {
              $this->translateField($entity, $form['name'], $field_name, $field_type, $d_lang, $t_lang);
            }
          }
          if ($this->isParagraphReference($field) && !in_array($field_name, $excludedFields)) {
            if ($this->moduleHandler->moduleExists('paragraphs')) {
              $this->translateParagraphs($entity, $form, $field_name, $d_lang, $t_lang, $excludedFields);
            }
          }
        }
        return $form;
      }
    }
  }

  /**
   * Batch callback: Translates a simple translatable field.
   *
   * @param int $entity_id
   *   The entity ID.
   * @param string $entity_type
   *   The entity type.
   * @param string $field_name
   *   The field name.
   * @param string $d_lang
   *   The default language.
   * @param string $t_lang
   *   The target language.
   * @param array $context
   *   The batch context.
   */
  public static function batchTranslateField($entity_id, $entity_type, $field_name, $d_lang, $t_lang, $action, &$context) {
    $entity_storage = \Drupal::entityTypeManager()->getStorage($entity_type);
    $entity = $entity_storage->load($entity_id);
    $auto_translate_service = \Drupal::service('auto_translation.utility');

    if (!$entity instanceof ContentEntityInterface) {
      $auto_translate_service->getLogger('auto_translation')->error($auto_translate_service->t('Translation error: Entity is not a content entity.'));
      return;
    }

    if (!$entity->hasTranslation($t_lang)) {
      // Create the translation if it does not exist.
      // Verify entity type of type 'node' or 'media'.
      if ($entity_type == 'node') {
        $title = $entity->get('title')->value;
        $t_title = $auto_translate_service->translate($title, $d_lang, $t_lang);
        $entity->addTranslation($t_lang, ['title' => $t_title]);
      }
      elseif ($entity_type == 'media') {
        $title = $entity->get('name')->value;
        $t_title = $auto_translate_service->translate($title, $d_lang, $t_lang);
        $entity->addTranslation($t_lang, ['name' => $t_title]);
      }
      else {
        // Handle other entity types if needed.
      }
      $entity->save();
    }
    $excludedFields = $auto_translate_service->getExcludedFields();
    $translated_entity = $entity->getTranslation($t_lang);
    if (!isset($context['results'][$t_lang]) && $entity->hasField('title') && $entity_type == 'node') {
      $t_title = $translated_entity->get('title')->value;
      $context['results'][$t_lang] = '(' . $auto_translate_service->t('ID') . ': ' . $entity_id . '; ' . $auto_translate_service->t('Target Language:') . ' ' . $t_lang . ') - ' . $t_title;
    }
    if (!isset($context['results'][$t_lang]) && $entity->hasField('name') && $entity_type == 'media') {
      $t_name = $translated_entity->get('name')->value;
      $context['results'][$t_lang] = '(' . $auto_translate_service->t('ID') . ': ' . $entity_id . '; ' . $auto_translate_service->t('Target Language:') . ' ' . $t_lang . ') - ' . $t_name;
    }
    // Retrieve the field value.
    // Translate the value (using your translation service).
    $field = $entity->get($field_name);
    if (!$field->getFieldDefinition()->isTranslatable() || in_array($field_name, $excludedFields) || str_starts_with($field->getFieldDefinition()->getType(), 'list_')) {
      return;
    }
    if ($field->getFieldDefinition()->isTranslatable()) {

      // Retrieve the field values.
      $field_values = $entity->get($field_name)->getValue();

      // Iterate over each item to translate it.
      foreach ($field_values as &$item) {
        if (!$field->getFieldDefinition()->isTranslatable() || in_array($field_name, $excludedFields) || str_starts_with($field->getFieldDefinition()->getType(), 'list_')) {
          continue;
        }

        switch ($field->getFieldDefinition()->getType()) {
          case 'link':
            if (!empty($item['title']) && is_string($item['title'])) {
              $item['title'] = $auto_translate_service->translate($item['title'], $d_lang, $t_lang);
            }
            $auto_translate_service->getLogger('auto_translation')->debug($auto_translate_service->t('Translating field:') . ' ' . $field_name . ' ' . $auto_translate_service->t('with value:') . ' ' . $item['title']);
            break;

          case 'image':
            if (!empty($item['alt']) && is_string($item['alt'])) {
              $item['alt'] = $auto_translate_service->translate($item['alt'], $d_lang, $t_lang);
              $auto_translate_service->getLogger('auto_translation')->debug($auto_translate_service->t('Translating field:') . ' ' . $field_name . ' ' . $auto_translate_service->t('with value:') . ' ' . $item['alt']);
            }
            if (!empty($item['title']) && is_string($item['title'])) {
              $item['title'] = $auto_translate_service->translate($item['title'], $d_lang, $t_lang);
              $auto_translate_service->getLogger('auto_translation')->debug($auto_translate_service->t('Translating field:') . ' ' . $field_name . ' ' . $auto_translate_service->t('with value:') . ' ' . $item['title']);
            }
            break;

          case 'file':
            if (!empty($item['description']) && is_string($item['description'])) {
              $item['description'] = $auto_translate_service->translate($item['description'], $d_lang, $t_lang);
              $auto_translate_service->getLogger('auto_translation')->debug($auto_translate_service->t('Translating field:') . ' ' . $field_name . ' ' . $auto_translate_service->t('with value:') . ' ' . $item['description']);
            }
            break;

          default:
            foreach ($item as $key => $value) {
              if (is_string($value) && !empty($value) && ($key === 'value' || $key === '#value' || $key === 'summary' || $key === 'summary_override' || $key === '#default_value')) {
                $item[$key] = $auto_translate_service->translate($value, $d_lang, $t_lang);
                $auto_translate_service->getLogger('auto_translation')->debug($auto_translate_service->t('Translating field:') . ' ' . $field_name . ' ' . $auto_translate_service->t('with value:') . ' ' . $item[$key]);
              }
              else {
                $item[$key] = $value;
              }
            }
            break;
        }
      }
      // Set the translated value in the translated paragraph.
      $translated_entity->set($field_name, $field_values);
      $translated_entity->save();
    }
    $context['message'] = $auto_translate_service->t('Translated field @field', ['@field' => $field_name]);
  }

  /**
   * Batch callback: Translates paragraph fields.
   *
   * @param int $entity_id
   *   The entity ID.
   * @param string $entity_type
   *   The entity type.
   * @param string $field_name
   *   The field name.
   * @param string $d_lang
   *   The default language.
   * @param string $t_lang
   *   The target language.
   * @param array $excludedFields
   *   Array of field names to exclude.
   * @param array $context
   *   The batch context.
   */
  public static function batchTranslateParagraphs($entity_id, $entity_type, $field_name, $d_lang, $t_lang, $excludedFields, $action, &$context) {
    $auto_translate_service = \Drupal::service('auto_translation.utility');
    $entity_storage = \Drupal::entityTypeManager()->getStorage($entity_type);
    $entity = $entity_storage->load($entity_id);

    if (!$entity instanceof ContentEntityInterface) {
      $auto_translate_service->getLogger('auto_translation')->error($auto_translate_service->t('Translation error: Entity is not a content entity.'));
      return;
    }
    // Ensure translation exists.
    $translated_entity = $entity->getTranslation($t_lang);
    // Process each paragraph item in the field.
    $paragraphItems = $translated_entity->get($field_name);
    foreach ($paragraphItems as $paragraphItem) {
      if ($paragraphItem->entity instanceof \Drupal\paragraphs\ParagraphInterface) {
        // Process the translation of the paragraph recursively.
        self::batchProcessParagraphTranslation($paragraphItem->entity, $d_lang, $t_lang, $excludedFields);
      }
    }
    $context['message'] = $auto_translate_service->t('Translated paragraphs for field @field', ['@field' => $field_name]);
  }

  /**
   * Batch helper callback: Processes translation for a paragraph entity recursively.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraphEntity
   *   The paragraph entity.
   * @param string $d_lang
   *   The default language.
   * @param string $t_lang
   *   The target language.
   * @param array $excludedFields
   *   Array of field names to exclude.
   */
  public static function batchProcessParagraphTranslation($paragraphEntity, $d_lang, $t_lang, $excludedFields) {
    if (!$paragraphEntity instanceof \Drupal\paragraphs\ParagraphInterface) {
      return;
    }
    // Create or retrieve the translation.
    if (!$paragraphEntity->hasTranslation($t_lang)) {
      $translatedParagraph = $paragraphEntity->addTranslation($t_lang, []);
    }
    else {
      $translatedParagraph = $paragraphEntity->getTranslation($t_lang);
    }

    $auto_translate_service = \Drupal::service('auto_translation.utility');
    // Iterate over each field in the paragraph.
    foreach ($paragraphEntity->getFields() as $field_name => $field) {
      if (in_array($field_name, $excludedFields) || $field_name == 'default_langcode') {
        continue;
      }
      if ($auto_translate_service->isStaticParagraphReference($field)) {
        // Process nested paragraphs recursively.
        $nestedParagraphs = $paragraphEntity->get($field_name);
        foreach ($nestedParagraphs as $nestedParagraph) {
          if ($nestedParagraph->entity instanceof \Drupal\paragraphs\ParagraphInterface) {
            $auto_translate_service->batchProcessParagraphTranslation($nestedParagraph->entity, $d_lang, $t_lang, $excludedFields);
          }
        }
      }
      else {
        // Translate the field.
        if ($field->getFieldDefinition()->isTranslatable()) {

          // Retrieve the field values.
          $field_values = $paragraphEntity->get($field_name)->getValue();

          // Iterate over each item to translate it.
          foreach ($field_values as &$item) {
            if (!$field->getFieldDefinition()->isTranslatable() || in_array($field_name, $excludedFields) || str_starts_with($field->getFieldDefinition()->getType(), 'list_')) {
              continue;
            }

            switch ($field->getFieldDefinition()->getType()) {
              case 'link':
                if (!empty($item['title']) && is_string($item['title'])) {
                  $item['title'] = $auto_translate_service->translate($item['title'], $d_lang, $t_lang);
                }
                $auto_translate_service->getLogger('auto_translation')->debug($auto_translate_service->t('Translating paragraph field:') . ' ' . $field_name . ' ' . $auto_translate_service->t('with value:') . ' ' . $item['title']);
                break;

              case 'image':
                if (!empty($item['alt']) && is_string($item['alt'])) {
                  $item['alt'] = $auto_translate_service->translate($item['alt'], $d_lang, $t_lang);
                  $auto_translate_service->getLogger('auto_translation')->debug($auto_translate_service->t('Translating paragraph field:') . ' ' . $field_name . ' ' . $auto_translate_service->t('with value:') . ' ' . $item['alt']);
                }
                if (!empty($item['title']) && is_string($item['title'])) {
                  $item['title'] = $auto_translate_service->translate($item['title'], $d_lang, $t_lang);
                  $auto_translate_service->getLogger('auto_translation')->debug($auto_translate_service->t('Translating paragraph field:') . ' ' . $field_name . ' ' . $auto_translate_service->t('with value:') . ' ' . $item['title']);
                }
                break;

              case 'file':
                if (!empty($item['description']) && is_string($item['description'])) {
                  $item['description'] = $auto_translate_service->translate($item['description'], $d_lang, $t_lang);
                  $auto_translate_service->getLogger('auto_translation')->debug($auto_translate_service->t('Translating paragraph field:') . ' ' . $field_name . ' ' . $auto_translate_service->t('with value:') . ' ' . $item['description']);
                }
                break;

              default:
                foreach ($item as $key => $value) {
                  if (is_string($value) && !empty($value) && ($key === 'value' || $key === '#value' || $key === 'summary' || $key === 'summary_override' || $key === '#default_value')) {
                    $item[$key] = $auto_translate_service->translate($value, $d_lang, $t_lang);
                    $auto_translate_service->getLogger('auto_translation')->debug($auto_translate_service->t('Translating paragraph field:') . ' ' . $field_name . ' ' . $auto_translate_service->t('with value:') . ' ' . $item[$key]);
                  }
                  else {
                    $item[$key] = $value;
                  }
                }
                break;
            }
          }
          // Set the translated value in the translated paragraph.
          $translatedParagraph->set($field_name, $field_values);
          $context['message'] = $auto_translate_service->t('Translated paragraph field @field', ['@field' => $field_name]);
        }
      }
    }
    // Finalize the translation.
    $translatedParagraph->save();
  }

  /**
   * Batch callback: Finalizes the entity translation by setting moderation,
   * status, revision, and saving the entity.
   *
   * @param int $entity_id
   *   The entity ID.
   * @param string $entity_type
   *   The entity type.
   * @param string $t_lang
   *   The target language.
   * @param string $action
   *   The action identifier.
   * @param array $context
   *   The batch context.
   */
  public static function batchFinalizeEntity($entity_id, $entity_type, $t_lang, $action, $chunk, &$context) {
    $entity_storage = \Drupal::entityTypeManager()->getStorage($entity_type);
    $entity = $entity_storage->load($entity_id);
    $auto_translate_service = \Drupal::service('auto_translation.utility');

    if (!$entity instanceof ContentEntityInterface) {
      $auto_translate_service->getLogger('auto_translation')->error($auto_translate_service->t('Translation error: Entity is not a content entity.'));
      return;
    }

    $translated_entity = $entity->getTranslation($t_lang);

    if ($action && $action === "publish_action") {
      if ($translated_entity->hasField('moderation_state')) {
        $translated_entity->moderation_state->value = 'published';
        $translated_entity->moderation_state->langcode = $t_lang;
      }
      $translated_entity->set('status', 1);
    }
    else {
      if ($translated_entity->hasField('moderation_state')) {
        $translated_entity->moderation_state->value = 'draft';
        $translated_entity->moderation_state->langcode = $t_lang;
      }
      $translated_entity->set('status', 0);
    }
    $translated_entity->setNewRevision(TRUE);
    $translated_entity->setRevisionTranslationAffectedEnforced(TRUE);
    if ($translated_entity instanceof \Drupal\Core\Entity\RevisionLogInterface) {
      $translated_entity->setRevisionLogMessage($auto_translate_service->t('Translation added by Auto Translation module'));
    }
    $translated_entity->save();
    if ($entity_type == 'node') {
      $title = $translated_entity->get('title')->value;
    }
    elseif ($entity_type == 'media') {
      $title = $translated_entity->get('name')->value;
    }
    else {
      $title = '';
    }
    $auto_translate_service->getLogger('auto_translation')->debug($auto_translate_service->t('Saved translated entity:') . ' ' . $translated_entity->id() . ' ' . $auto_translate_service->t('with title:') . ' ' . $title);
    $context['message'] = $auto_translate_service->t('Finalized translation for entity @id', ['@id' => $entity_id]);
    $context['results']['entity_type'] = $entity_type;
  }

  /**
   * Batch finished callback.
   *
   * @param bool $success
   *   TRUE if the batch finished successfully, FALSE otherwise.
   * @param array $results
   *   An array of results from the batch operations.
   * @param array $operations
   *   The operations that were run.
   */
  public static function batchFinishedCallback($success, $results, $operations) {
    if ($success) {
      if (!empty($results)) {
        $languages = \Drupal::service('language_manager')->getLanguages();
        \Drupal::messenger()->addMessage(t('Entity translation completed'));
        foreach ($results as $key => $result) {
          if (in_array($key, array_keys($languages))) {
            \Drupal::messenger()->addMessage($result);
          }
        }
      }
      else {
        \Drupal::messenger()->addMessage(t('No entities were translated.'));
      }
    }
    else {
      \Drupal::messenger()->addError(t('An error occurred during entity translation.'));
    }
    if ($results['entity_type'] == 'node') {
      $route = 'system.admin_content';
    }
    elseif ($results['entity_type'] == 'media') {
      $route = 'entity.media.collection';
    }
    else {
      $route = 'system.admin_content';
    }
    return new RedirectResponse(Url::fromRoute($route)->toString());
  }

  /**
   * Helper static function to determine if a field is a paragraph reference.
   *
   * This version is static and can be used in batch callbacks.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   The field.
   *
   * @return bool
   *   TRUE if the field is a paragraph reference, FALSE otherwise.
   */
  public static function isStaticParagraphReference($field) {
    // Adjust the condition based on your implementation.
    return ($field->getFieldDefinition()->getType() == 'entity_reference_revisions' ||
      ($field->getFieldDefinition()->getType() == 'entity_reference' && $field->getFieldDefinition()->getSetting('target_type') == 'paragraph'));
  }

  /**
   * Handles translation for simple fields, as well as 'link' and 'image' fields.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity or paragraph.
   * @param array &$widget
   *   The form widget array for the field.
   * @param string $field_name
   *   The name of the field to translate.
   * @param string $field_type
   *   The type of the field.
   * @param string $d_lang
   *   The default language code.
   * @param string $t_lang
   *   The target language code.
   */
  private function translateField($entity, &$widget, $field_name, $field_type, $d_lang, $t_lang) {
    foreach ($widget as $key => &$sub_widget) {
      if (is_numeric($key) && is_array($sub_widget)) {
        // List of potentially translatable keys.
        $translatable_keys = ['value', '#default_value', 'title'];

        // Set format if available.
        if (isset($sub_widget['#format'])) {
          $sub_widget['value']['#format'] = $entity->get($field_name)->format;
        }
        if (isset($sub_widget['#text_format'])) {
          $sub_widget['value']['#text_format'] = $entity->get($field_name)->format;
        }

        // Translate all the string fields.
        foreach ($translatable_keys as $field) {
          if (isset($sub_widget[$field])) {
            if (is_array($sub_widget[$field]) && isset($sub_widget[$field]['#default_value']) && is_string($sub_widget[$field]['#default_value']) && !empty($sub_widget[$field]['#default_value'])) {
              $sub_widget[$field]['#default_value'] = $this->translate($sub_widget[$field]['#default_value'], $d_lang, $t_lang);
            }
            elseif (is_string($sub_widget[$field]) && !empty($sub_widget[$field])) {
              $sub_widget[$field] = $this->translate($sub_widget[$field], $d_lang, $t_lang);
            }
          }
        }
      }
    }

    // Translate field media alt text, title text.
    if (isset($widget[0]["#default_value"]["alt"]) && !empty($widget[0]["#default_value"]["alt"])) {
      $widget[0]["#default_value"]["alt"] = $this->translate($widget[0]["#default_value"]["alt"], $d_lang, $t_lang);
    }
    if (isset($widget[0]["#default_value"]["title"]) && !empty($widget[0]["#default_value"]["title"])) {
      $widget[0]["#default_value"]["title"] = $this->translate($widget[0]["#default_value"]["title"], $d_lang, $t_lang);
    }
  }

  /**
   * Checks if a field is a reference to a paragraph.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field
   *   The field definition.
   *
   * @return bool
   *   TRUE if the field points to a paragraph, FALSE otherwise.
   */
  private function isParagraphReference($field) {
    return ($field->getSetting('target_type') === 'paragraph');
  }

  /**
   * Handles the translation of paragraph fields recursively.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The main entity.
   * @param array &$form
   *   The form array.
   * @param string $field_name
   *   The name of the field containing the paragraphs.
   * @param string $d_lang
   *   The default language code.
   * @param string $t_lang
   *   The target language code.
   * @param array $excludedFields
   *   Array of field names to exclude from translation.
   */
  private function translateParagraphs($entity, &$form, $field_name, $d_lang, $t_lang, $excludedFields) {
    $paragraphItems = $entity->get($field_name);
    foreach ($paragraphItems as $index => $paragraphItem) {
      if ($paragraphItem->entity instanceof \Drupal\paragraphs\ParagraphInterface) {
        if (isset($form[$field_name]['widget'][$index]['subform'])) {
          $this->processParagraphTranslation($paragraphItem->entity, $form[$field_name]['widget'][$index]['subform'], $d_lang, $t_lang, $excludedFields);
        }
      }
    }
  }

  /**
   * Processes the translation of a paragraph entity recursively.
   *
   * For each translatable field of the paragraph (and nested paragraphs),
   * the translation is performed, and at the end, the translated entity is saved.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraphEntity
   *   The paragraph entity.
   * @param array &$form
   *   The subform array related to the paragraph.
   * @param string $d_lang
   *   The default language code.
   * @param string $t_lang
   *   The target language code.
   * @param array $excludedFields
   *   Array of field names to exclude.
   */
  private function processParagraphTranslation($paragraphEntity, &$form, $d_lang, $t_lang, $excludedFields) {
    if (!$paragraphEntity instanceof \Drupal\paragraphs\ParagraphInterface) {
      return;
    }

    // Iterate over each field of the paragraph.
    foreach ($paragraphEntity->getFields() as $field_name => $field) {
      // If the field is translatable and not among the excluded ones, proceed.
      if ($field->getFieldDefinition()->isTranslatable() && !in_array($field_name, $excludedFields)) {
        if (isset($form[$field_name]['widget'])) {
          $this->translateField($paragraphEntity, $form[$field_name]['widget'], $field_name, $field->getFieldDefinition()->getType(), $d_lang, $t_lang);
        }
      }
      // If the field is a reference to paragraphs, handle it recursively.
      if ($this->isParagraphReference($field)) {
        $nestedItems = $paragraphEntity->get($field_name);
        foreach ($nestedItems as $idx => $nestedItem) {
          if ($nestedItem->entity instanceof \Drupal\paragraphs\ParagraphInterface) {
            if (isset($form[$field_name]['widget'][$idx]['subform'])) {
              $this->processParagraphTranslation($nestedItem->entity, $form[$field_name]['widget'][$idx]['subform'], $d_lang, $t_lang, $excludedFields);
            }
          }
        }
      }
    }
  }

  /**
   * Custom get string between function.
   */
  public function getStringBetween($string, $start, $end) {
    $string = ' ' . $string;
    $ini = strpos($string, $start);
    if ($ini == 0) {
      return '';
    }
    $ini += strlen($start);
    $len = strpos($string, $end, $ini) - $ini;
    return substr($string, $ini, $len);
  }

  /**
   * Retrieves the container.
   *
   * @return mixed
   *   The container.
   */
  public static function getContainer() {
    return \Drupal::getContainer();
  }

  /**
   * Retrieves the configuration settings.
   *
   * @return object
   *   The configuration settings.
   */
  public static function config() {
    return static::getContainer()
      ->get('config.factory')
      ->get('auto_translation.settings');
  }

  /**
   * Retrieves the source and target languages for an entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The translated entity.
   *
   * @return array
   *   An array with 'original' and 'translated' containing the respective languages.
   */
  private function getTranslationLanguages(ContentEntityInterface $entity) {
    $language_manager = \Drupal::service('language_manager');
    $route_match = \Drupal::routeMatch();

    // Try to get the languages directly from the URL.
    $original_language = $route_match->getParameter('source') ?? NULL;
    $translated_language = $route_match->getParameter('target') ?? NULL;

    // If we find the languages in the URL, use them directly and in the correct order.
    if (!empty($original_language) && !empty($translated_language)) {
      return [
      // The first language from the URL.
        'original' => (string) $original_language->getId(),
      // The second language from the URL.
        'translated' => (string) $translated_language->getId(),
      ];
    }

    // If the entity is not translatable, use only the current language.
    if (!$entity->getEntityType()->isTranslatable()) {
      return [
        'original' => $entity->language()->getId(),
        'translated' => $language_manager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId(),
      ];
    }

    // Find the original language of the entity.
    $default_language = $entity->getUntranslated()->language()->getId();

    // Current language = the language we are translating into.
    $current_language = $language_manager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId();

    return [
    // The base language of the entity.
      'original' => $default_language,
    // The language we are translating into.
      'translated' => $current_language,
    ];
  }

  /**
   * Retrieves the specified form.
   *
   * @param object $messages
   *   The json of the message to retrieve.
   *
   * @return mixed
   *   The service object if found, null otherwise.
   */
  public static function getMessages($messages) {
    $auto_translation_service = \Drupal::service('auto_translation.utility');
    return static::getContainer()
      ->get('messenger')->addMessage($auto_translation_service->t('Auto translation error: @error', [
        '@error' => Markup::create(htmlentities(json_encode($messages))),
      ]), MessengerInterface::TYPE_ERROR);
  }

  /**
   * Returns the path of the module.
   *
   * @return string
   *   The path of the module.
   */
  public static function getModulePath() {
    return static::getContainer()
      ->get('extension.list.module')
      ->getPath('auto_translation');
  }

  /**
   * Retrieves the specified module by name.
   *
   * @param string $module_name
   *   The name of the module to retrieve.
   *
   * @return mixed|null
   *   The module object if found, null otherwise.
   */
  public static function getModule($module_name) {
    return static::getContainer()
      ->get('extension.list.module')
      ->get($module_name);
  }

  /**
   * Encrypts the API key using AES-256-CBC.
   *
   * @param string $plainApiKey
   *   The API key in plaintext.
   *
   * @return string
   *   The encrypted API key (base64-encoded IV concatenated with the ciphertext).
   */
  public function encryptApiKey(?string $plainApiKey): string {
    if (!function_exists('openssl_cipher_iv_length') || !function_exists('openssl_encrypt') || empty($plainApiKey)) {
      // The OpenSSL extension is not available.
      if (!function_exists('openssl_cipher_iv_length') || !function_exists('openssl_encrypt')) {
        $this->getLogger('auto_translation')->warning('OpenSSL extension is not available.');
        $this->messenger->addWarning($this->t('The OpenSSL extension is not available. Please enable it to re-encrypt the API key.'));
      }
      return $plainApiKey;
    }
    // Define a "master key". You can define it in settings.php for enhanced security.
    // For example, use DRUPAL_HASH_SALT or a custom constant.
    $secret = $this->configFactory->getEditable('auto_translation.settings')->get('custom_secret');
    if (empty($secret)) {
      $config = $this->configFactory->getEditable('auto_translation.settings');
      // Generate a 32-character random string.
      $secret = bin2hex(random_bytes(16));
      $config->set('custom_secret', $secret)->save();
    }
    else {
      $secret = $this->configFactory->getEditable('auto_translation.settings')->get('custom_secret');
    }
    // Derive a 256-bit key (32 bytes) from the secret.
    $key = substr(hash('sha256', $secret, TRUE), 0, 32);

    // Get the initialization vector (IV) length for AES-256-CBC.
    $ivLength = openssl_cipher_iv_length('AES-256-CBC');
    // Generate a cryptographically secure IV.
    $iv = random_bytes($ivLength);

    // Encrypt the API key using AES-256-CBC. Using OPENSSL_RAW_DATA returns raw binary data.
    $ciphertext = openssl_encrypt($plainApiKey, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

    // To decrypt, you must also save the IV.
    // Here we concatenate the IV with the ciphertext and then base64-encode the result.
    return base64_encode($iv . $ciphertext);
  }

  /**
   * Decrypts an API key previously encrypted.
   *
   * @param string $encryptedApiKey
   *   The encrypted API key (base64-encoded string containing IV + ciphertext).
   *
   * @return string|false
   *   The decrypted API key in plaintext or FALSE if decryption fails.
   */
  public function decryptApiKey(?string $encryptedApiKey): string {
    if (!function_exists('openssl_cipher_iv_length') || !function_exists('openssl_decrypt') || empty($encryptedApiKey)) {
      // The OpenSSL extension is not available.
      if (!function_exists('openssl_cipher_iv_length') || !function_exists('openssl_decrypt')) {
        $this->getLogger('auto_translation')->warning('OpenSSL extension is not available.');
        $this->messenger->addWarning($this->t('The OpenSSL extension is not available. Please enable it to re-encrypt the API key.'));
      }
      return $encryptedApiKey;
    }
    $secret = $this->configFactory->getEditable('auto_translation.settings')->get('custom_secret');
    if (empty($secret)) {
      $config = $this->configFactory->getEditable('auto_translation.settings');
      // Generate a 32-character random string.
      $secret = bin2hex(random_bytes(16));
      $config->set('custom_secret', $secret)->save();
    }
    else {
      $secret = $this->configFactory->getEditable('auto_translation.settings')->get('custom_secret');
    }
    $key = substr(hash('sha256', $secret, TRUE), 0, 32);

    // Decode the base64-encoded string and split the IV from the ciphertext.
    $data = base64_decode($encryptedApiKey);
    $ivLength = openssl_cipher_iv_length('AES-256-CBC');
    $iv = substr($data, 0, $ivLength);
    $ciphertext = substr($data, $ivLength);

    return openssl_decrypt($ciphertext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
  }

  /**
   * Retrieves the user permissions.
   *
   * @return bool
   *   TRUE if the user has the permission, FALSE otherwise.
   */
  public function hasPermission() {
    $permission = 'auto translation translate content';
    $current_user = \Drupal::currentUser();
    $permissions = \Drupal::service('user.permissions')->getPermissions();
    if (isset($permissions[$permission])) {
      return $current_user->hasPermission($permission);
    }
    return FALSE;
  }

  /**
   * Translates HTML content while preserving structure and whitespace.
   *
   * Uses Drupal's Html utility class and implements proper caching and error handling.
   *
   * @param string $html
   *   The HTML content to translate.
   * @param string $s_lang
   *   Source language code.
   * @param string $t_lang
   *   Target language code.
   *
   * @return string
   *   The translated HTML content.
   */
  private function translateHtmlContent($html, $s_lang, $t_lang, $provider = 'google') {
    $logger = $this->loggerFactory->get('auto_translation');
    $config = $this->configFactory->get('auto_translation.settings');
    $enable_debug = $config->get('enable_debug');

    // Generate cache key for this translation request.
    $cache_key = 'auto_translation:html:' . md5($html . $s_lang . $t_lang . $provider);

    // Check cache first.
    $cached = $this->cacheBackend->get($cache_key);
    if ($cached && !empty($cached->data)) {

      // If debug logging is enabled, log the cache hit.
      if ($enable_debug) {
        $logger->debug('Cache hit for HTML translation from @s_lang to @t_lang', [
          '@s_lang' => $s_lang,
          '@t_lang' => $t_lang,
        ]);
      }
      return $cached->data;
    }

    try {
      // Use Drupal's Html utility for safer HTML processing.
      // Define standard allowed HTML tags for translation.
      $allowed_tags = [
        'a', 'em', 'strong', 'cite', 'blockquote', 'code', 'ul', 'ol', 'li',
        'dl', 'dt', 'dd', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'br',
        'span', 'div', 'img', 'table', 'thead', 'tbody', 'tr', 'td', 'th'
      ];
      $filtered_html = Xss::filter($html, $allowed_tags);

      // Create DOMDocument with better error handling.
      $dom = Html::load($filtered_html);

      if (!$dom) {
        $logger->error('Failed to parse HTML content for translation');
        return $html;
      }

      // Use XPath to find text nodes more efficiently.
      $xpath = new \DOMXPath($dom);
      $text_nodes = $xpath->query('//text()[normalize-space()]');

      if ($text_nodes->length === 0) {
        if($enable_debug) {
          $logger->debug('No translatable text nodes found in HTML content');
        }
        return $html;
      }

      // Collect text segments for batch translation.
      $text_segments = [];
      $node_mapping = [];

      foreach ($text_nodes as $text_node) {
        $original_text = $text_node->nodeValue;
        $trimmed_text = trim($original_text);

        // Skip empty, single character, or non-breaking space content.
        if (empty($trimmed_text) || strlen($trimmed_text) <= 1 || $trimmed_text === '&nbsp;') {
          continue;
        }

        // Skip if the text contains only numbers, punctuation, or symbols.
        if (preg_match('/^[\d\s\p{P}\p{S}]+$/u', $trimmed_text)) {
          continue;
        }

        $text_segments[] = [
          'original' => $original_text,
          'trimmed' => $trimmed_text,
          'leading_space' => $this->extractLeadingWhitespace($original_text),
          'trailing_space' => $this->extractTrailingWhitespace($original_text),
        ];
        $node_mapping[] = $text_node;
      }

      if (empty($text_segments)) {
        if($enable_debug) {
          $logger->debug('No valid text segments found for translation');
        }
        return $html;
      }

      // Translate text segments with error handling.
      $translated_segments = $this->translateTextSegments($text_segments, $s_lang, $t_lang, $provider);

      // Apply translations back to DOM nodes.
      $this->applyTranslatedSegments($node_mapping, $translated_segments);

      // Generate clean HTML output.
      $result = Html::serialize($dom);

      // Cache the result.
      $this->cacheBackend->set($cache_key, $result, time() + 3600, ['auto_translation']);

      if ($enable_debug) {
        $logger->debug('HTML content translated successfully from @s_lang to @t_lang', [
          '@s_lang' => $s_lang,
          '@t_lang' => $t_lang,
        ]);
      }

      return $result;

    } catch (\Exception $e) {
      $logger->error('Error translating HTML content: @error', ['@error' => $e->getMessage()]);

      // Return original content on error.
      return $html;
    }
  }

  /**
   * Extracts leading whitespace from a text string.
   *
   * @param string $text
   *   The text to analyze.
   *
   * @return string
   *   The leading whitespace characters.
   */
  private function extractLeadingWhitespace($text) {
    if (preg_match('/^(\s*)/', $text, $matches)) {
      return $matches[1];
    }
    return '';
  }

  /**
   * Extracts trailing whitespace from a text string.
   *
   * @param string $text
   *   The text to analyze.
   *
   * @return string
   *   The trailing whitespace characters.
   */
  private function extractTrailingWhitespace($text) {
    if (preg_match('/(\s*)$/', $text, $matches)) {
      return $matches[1];
    }
    return '';
  }

  /**
   * Translates an array of text segments efficiently.
   *
   * @param array $text_segments
   *   Array of text segments to translate.
   * @param string $s_lang
   *   Source language code.
   * @param string $t_lang
   *   Target language code.
   * @param string $provider
   *   The translation provider to use.
   *
   * @return array
   *   Array of translated segments.
   */
  private function translateTextSegments(array $text_segments, $s_lang, $t_lang, $provider = 'google') {
    $logger = $this->loggerFactory->get('auto_translation');
    $translated_segments = [];

    foreach ($text_segments as $index => $text_data) {
      $cache_key = 'auto_translation:text:' . md5($text_data['trimmed'] . $s_lang . $t_lang . $provider);

      // Check cache for individual text segments.
      $cached = $this->cacheBackend->get($cache_key);
      if ($cached && !empty($cached->data)) {
        $translated_segments[] = $text_data['leading_space'] . $cached->data . $text_data['trailing_space'];
        continue;
      }

      try {
        $translated = $this->callProviderTranslationApi($text_data['trimmed'], $s_lang, $t_lang, $provider);

        if ($translated) {
          // Cache the translation.
          $this->cacheBackend->set($cache_key, $translated, time() + 86400, ['auto_translation']);

          // Reconstruct with original whitespace.
          $reconstructed = $text_data['leading_space'] . $translated . $text_data['trailing_space'];
          $translated_segments[] = $reconstructed;
        } else {
          // Fallback to original text.
          $translated_segments[] = $text_data['original'];
          $logger->warning('Translation failed for text segment: @text', ['@text' => $text_data['trimmed']]);
        }
      } catch (\Exception $e) {
        $logger->error('Error translating text segment: @error', ['@error' => $e->getMessage()]);
        $translated_segments[] = $text_data['original'];
      }
    }

    return $translated_segments;
  }

  /**
   * Applies translated segments back to DOM nodes.
   *
   * @param array $node_mapping
   *   Array of DOM text nodes.
   * @param array $translated_segments
   *   Array of translated text segments.
   */
  private function applyTranslatedSegments(array $node_mapping, array $translated_segments) {
    $count = min(count($node_mapping), count($translated_segments));

    for ($i = 0; $i < $count; $i++) {
      if (isset($translated_segments[$i]) && $node_mapping[$i] instanceof \DOMText) {
        $node_mapping[$i]->nodeValue = $translated_segments[$i];
      }
    }
  }

  /**
   * Improved Google Translate API call with retry logic.
   *
   * @param string $text
   *   The text to translate.
   * @param string $s_lang
   *   The source language code.
   * @param string $t_lang
   *   The target language code.
   * @param int $retry_count
   *   Number of retries attempted.
   *
   * @return string|null
   *   The translated text or NULL on failure.
   */
  private function callGoogleTranslateApiWithRetry($text, $s_lang, $t_lang, $retry_count = 0) {
    $config = $this->configFactory->get('auto_translation.settings');
    $logger = $this->loggerFactory->get('auto_translation');
    $enable_debug = $config->get('enable_debug');
    $max_retries = 3;

    try {
      $endpoint = 'https://translate.googleapis.com/translate_a/single';
      $query_params = [
        'client' => 'gtx',
        'sl' => $s_lang,
        'tl' => $t_lang,
        'dt' => 't',
        'q' => $text,
      ];

      if ($enable_debug) {
        $logger->info('Google Browser API - Using endpoint: @endpoint with client=gtx', [
          '@endpoint' => $endpoint,
        ]);
      }

      $options = [
        'verify' => FALSE,
        'timeout' => 30,
        'headers' => [
          'User-Agent' => 'Mozilla/5.0 (compatible; DrupalAutoTranslation/1.0)',
        ],
        'query' => $query_params,
      ];

      $response = $this->httpClient->get($endpoint, $options);
      $data = Json::decode($response->getBody()->getContents());

      if (!isset($data[0]) || !is_array($data[0])) {
        throw new \Exception('Invalid response format from translation API');
      }

      $translation = '';
      foreach ($data[0] as $segment) {
        if (isset($segment[0])) {
          $translation .= $segment[0];
        }
      }

      if ($enable_debug) {
        $logger->info('Google Browser API - Translation successful for text: "@text"', [
          '@text' => substr($text, 0, 50) . (strlen($text) > 50 ? '...' : ''),
        ]);
      }

      // Ensure proper HTML entity decoding for Google Browser API
      return htmlspecialchars_decode($translation, ENT_QUOTES | ENT_HTML5);

    } catch (RequestException $e) {
      $logger->warning('Translation API request failed (attempt @retry): @error', [
        '@retry' => $retry_count + 1,
        '@error' => $e->getMessage(),
      ]);

      // Retry logic for temporary failures.
      if ($retry_count < $max_retries && $e->getCode() >= 500) {
        sleep(pow(2, $retry_count)); // Exponential backoff.
        return $this->callGoogleTranslateApiWithRetry($text, $s_lang, $t_lang, $retry_count + 1);
      }

      return null;
    } catch (\Exception $e) {
      $logger->error('Translation API error: @error', ['@error' => $e->getMessage()]);
      return null;
    }
  }

  /**
   * Makes a direct call to Google Translate API.
   *
   * @param string $text
   *   The text to translate.
   * @param string $s_lang
   *   The source language code.
   * @param string $t_lang
   *   The target language code.
   *
   * @return string|null
   *   The translated text or NULL on failure.
   */
  private function callGoogleTranslateApi($text, $s_lang, $t_lang) {
    $endpoint = 'https://translate.googleapis.com/translate_a/single?client=gtx&sl=' . $s_lang . '&tl=' . $t_lang . '&dt=t&q=' . rawurlencode($text);
    $options = ['verify' => FALSE];

    try {
      $response = $this->httpClient->get($endpoint, $options);
      $data = Json::decode($response->getBody()->getContents());

      $translation = '';
      if (isset($data[0]) && is_array($data[0])) {
        foreach ($data[0] as $segment) {
          if (isset($segment[0])) {
            $translation .= $segment[0];
          }
        }
      }

      return $translation;
    }
    catch (RequestException $e) {
      $this->getLogger('auto_translation')->error('Translation API error: @error', ['@error' => $e->getMessage()]);
      return null;
    }
  }

  /**
   * Makes a translation API call based on the specified provider.
   *
   * @param string $text
   *   The text to translate.
   * @param string $s_lang
   *   The source language code.
   * @param string $t_lang
   *   The target language code.
   * @param string $provider
   *   The translation provider to use.
   *
   * @return string|null
   *   The translated text or NULL on failure.
   */
  private function callProviderTranslationApi($text, $s_lang, $t_lang, $provider = 'google') {
    $config = $this->configFactory->get('auto_translation.settings');
    $logger = $this->loggerFactory->get('auto_translation');
    $enable_debug = $config->get('enable_debug');

    switch ($provider) {
      case 'google':
        $api_enabled = $config->get('auto_translation_api_enabled') ?? NULL;
        if ($api_enabled) {
          // Use server API call for Google when API key is configured
          if ($enable_debug) {
            $logger->info('Using Google Server API for translation from @s_lang to @t_lang', [
              '@s_lang' => $s_lang,
              '@t_lang' => $t_lang,
            ]);
          }
          return $this->callGoogleServerTranslateApi($text, $s_lang, $t_lang);
        } else {
          // Use browser API call for Google
          if ($enable_debug) {
            $logger->info('Using Google Browser API for translation from @s_lang to @t_lang', [
              '@s_lang' => $s_lang,
              '@t_lang' => $t_lang,
            ]);
          }
          return $this->callGoogleTranslateApiWithRetry($text, $s_lang, $t_lang);
        }

      case 'libretranslate':
        if ($enable_debug) {
          $logger->info('Using LibreTranslate API for translation from @s_lang to @t_lang', [
            '@s_lang' => $s_lang,
            '@t_lang' => $t_lang,
          ]);
        }
        return $this->callLibreTranslateApi($text, $s_lang, $t_lang);

      case 'deepl':
        if ($enable_debug) {
          $logger->info('Using DeepL API for translation from @s_lang to @t_lang', [
            '@s_lang' => $s_lang,
            '@t_lang' => $t_lang,
          ]);
        }
        return $this->callDeepLTranslateApi($text, $s_lang, $t_lang);

      case 'drupal_ai':
        if ($this->moduleHandler->moduleExists('ai') && $this->moduleHandler->moduleExists('ai_translate')) {
          if ($enable_debug) {
            $logger->info('Using Drupal AI API for translation from @s_lang to @t_lang', [
              '@s_lang' => $s_lang,
              '@t_lang' => $t_lang,
            ]);
          }
          return $this->callDrupalAiTranslateApi($text, $s_lang, $t_lang);
        } else {
          $logger->error('AI translation modules are not installed.');
          return null;
        }

      default:
        $this->getLogger('auto_translation')->error('Unknown translation provider: @provider', ['@provider' => $provider]);
        return null;
    }
  }

  /**
   * Makes a direct call to Google Translate Server API using API key.
   *
   * @param string $text
   *   The text to translate.
   * @param string $s_lang
   *   The source language code.
   * @param string $t_lang
   *   The target language code.
   *
   * @return string|null
   *   The translated text or NULL on failure.
   */
  private function callGoogleServerTranslateApi($text, $s_lang, $t_lang) {
    $config = $this->configFactory->get('auto_translation.settings');
    $encryptedApiKey = $config->get('auto_translation_api_key');
    $apiKey = $this->decryptApiKey($encryptedApiKey);
    $logger = $this->loggerFactory->get('auto_translation');
    $enable_debug = $config->get('enable_debug');

    if ($enable_debug) {
      $logger->info('Google Server API - Using official Google Cloud Translate API with server key');
    }

    try {
      $client = new TranslateClient(['key' => $apiKey]);
      $result = $client->translate($text, ['source' => $s_lang, 'target' => $t_lang]);

      if ($enable_debug) {
        $logger->info('Google Server API - Translation successful for text: "@text"', [
          '@text' => substr($text, 0, 50) . (strlen($text) > 50 ? '...' : ''),
        ]);
      }

      // Ensure proper HTML entity decoding for Google Server API
      return htmlspecialchars_decode($result['text'], ENT_QUOTES | ENT_HTML5);
    }
    catch (\Exception $e) {
      $logger->error('Google Server API - Translation failed: @error', ['@error' => $e->getMessage()]);
      return null;
    }
  }

  /**
   * Makes a direct call to LibreTranslate API.
   *
   * @param string $text
   *   The text to translate.
   * @param string $s_lang
   *   The source language code.
   * @param string $t_lang
   *   The target language code.
   *
   * @return string|null
   *   The translated text or NULL on failure.
   */
  private function callLibreTranslateApi($text, $s_lang, $t_lang) {
    $config = $this->configFactory->get('auto_translation.settings');
    $encryptedApiKey = $config->get('auto_translation_api_key');
    $apiKey = $this->decryptApiKey($encryptedApiKey);
    $endpoint = 'https://libretranslate.com/translate';
    $logger = $this->loggerFactory->get('auto_translation');
    $enable_debug = $config->get('enable_debug');

    if ($enable_debug) {
      $logger->info('LibreTranslate API - Using endpoint: @endpoint', ['@endpoint' => $endpoint]);
    }

    $options = [
      'headers' => ['Content-Type' => 'application/json'],
      'json' => [
        'q' => $text,
        'source' => $s_lang,
        'target' => $t_lang,
        'format' => 'text',
        'api_key' => $apiKey,
      ],
      'verify' => FALSE,
    ];

    try {
      $response = $this->httpClient->post($endpoint, $options);
      $result = Json::decode($response->getBody()->getContents());
      $translated = $result['translatedText'] ?? null;

      if ($enable_debug) {
        $logger->info('LibreTranslate API - Translation successful for text: "@text"', [
          '@text' => substr($text, 0, 50) . (strlen($text) > 50 ? '...' : ''),
        ]);
      }

      // Ensure proper HTML entity decoding for LibreTranslate
      return $translated ? htmlspecialchars_decode($translated, ENT_QUOTES | ENT_HTML5) : null;
    }
    catch (RequestException $e) {
      $logger->error('LibreTranslate API - Translation failed: @error', ['@error' => $e->getMessage()]);
      return null;
    }
  }

  /**
   * Makes a direct call to DeepL Translate API.
   *
   * @param string $text
   *   The text to translate.
   * @param string $s_lang
   *   The source language code.
   * @param string $t_lang
   *   The target language code.
   *
   * @return string|null
   *   The translated text or NULL on failure.
   */
  private function callDeepLTranslateApi($text, $s_lang, $t_lang) {
    $config = $this->configFactory->get('auto_translation.settings');
    $encryptedApiKey = $config->get('auto_translation_api_key');
    $apiKey = $this->decryptApiKey($encryptedApiKey);
    $deeplMode = $config->get('auto_translation_api_deepl_pro_mode') === false ? 'api-free' : 'api';
    $endpoint = sprintf('https://%s.deepl.com/v2/translate', $deeplMode);
    $logger = $this->loggerFactory->get('auto_translation');
    $enable_debug = $config->get('enable_debug');

    if ($enable_debug) {
      $logger->info('DeepL API - Using endpoint: @endpoint (mode: @mode)', [
        '@endpoint' => $endpoint,
        '@mode' => $deeplMode,
      ]);
    }

    $options = [
      'headers' => [
        'Authorization' => 'DeepL-Auth-Key ' . $apiKey,
        'Content-Type' => 'application/json',
      ],
      'json' => [
        'text' => [$text],
        'source_lang' => $s_lang,
        'target_lang' => $t_lang,
      ],
      'verify' => FALSE,
    ];

    try {
      $response = $this->httpClient->post($endpoint, $options);
      $result = Json::decode($response->getBody()->getContents());
      $translated = $result['translations'][0]['text'] ?? null;

      if ($enable_debug) {
        $logger->info('DeepL API - Translation successful for text: "@text"', [
          '@text' => substr($text, 0, 50) . (strlen($text) > 50 ? '...' : ''),
        ]);
      }

      // Ensure proper HTML entity decoding for DeepL
      return $translated ? htmlspecialchars_decode($translated, ENT_QUOTES | ENT_HTML5) : null;
    }
    catch (RequestException $e) {
      $logger->error('DeepL API - Translation failed: @error', ['@error' => $e->getMessage()]);
      return null;
    }
  }

  /**
   * Makes a direct call to Drupal AI Translate API.
   *
   * @param string $text
   *   The text to translate.
   * @param string $s_lang
   *   The source language code.
   * @param string $t_lang
   *   The target language code.
   *
   * @return string|null
   *   The translated text or NULL on failure.
   */
  private function callDrupalAiTranslateApi($text, $s_lang, $t_lang) {
    $config = $this->configFactory->get('auto_translation.settings');
    $logger = $this->loggerFactory->get('auto_translation');
    $enable_debug = $config->get('enable_debug');

    if ($enable_debug) {
      $logger->info('Drupal AI API - Using AI Translate service');
    }

    try {
      $container = $this->getContainer();
      $languageManager = $container->get('language_manager');
      $langFrom = $languageManager->getLanguage($s_lang);
      $langTo = $languageManager->getLanguage($t_lang);

      $translatedText = \Drupal::service('ai_translate.text_translator')->translateContent($text, $langTo, $langFrom);

      if ($enable_debug) {
        $logger->info('Drupal AI API - Translation successful for text: "@text"', [
          '@text' => substr($text, 0, 50) . (strlen($text) > 50 ? '...' : ''),
        ]);
      }

      // Ensure proper HTML entity decoding for Drupal AI
      return $translatedText ? htmlspecialchars_decode($translatedText, ENT_QUOTES | ENT_HTML5) : null;
    }
    catch (\Exception $e) {
      $logger->error('Drupal AI API - Translation failed: @error', ['@error' => $e->getMessage()]);
      return null;
    }
  }

}
