<?php

namespace Drupal\content_translation;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityChangesDetectionTrait;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\user\Entity\User;
use Drupal\user\EntityOwnerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for content translation handlers.
 *
 * @ingroup entity_api
 */
class ContentTranslationHandler implements ContentTranslationHandlerInterface, EntityHandlerInterface {

  use EntityChangesDetectionTrait;
  use DependencySerializationTrait;
  use StringTranslationTrait;

  /**
   * The type of the entity being translated.
   *
   * @var string
   */
  protected $entityTypeId;

  /**
   * Installed field storage definitions for the entity type.
   *
   * Keyed by field name.
   *
   * @var \Drupal\Core\Field\FieldStorageDefinitionInterface[]
   */
  protected $fieldStorageDefinitions;

  /**
   * Initializes an instance of the content translation controller.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entityType
   *   The info array of the given entity type.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\content_translation\ContentTranslationManagerInterface $manager
   *   The content translation manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   The date formatter service.
   * @param \Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface $entity_last_installed_schema_repository
   *   The installed entity definition repository service.
   * @param \Drupal\Core\Routing\RedirectDestinationInterface $redirectDestination
   *   The request stack.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(
    protected EntityTypeInterface $entityType,
    protected LanguageManagerInterface $languageManager,
    protected ContentTranslationManagerInterface $manager,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AccountInterface $currentUser,
    protected MessengerInterface $messenger,
    protected DateFormatterInterface $dateFormatter,
    protected EntityLastInstalledSchemaRepositoryInterface $entity_last_installed_schema_repository,
    protected RedirectDestinationInterface $redirectDestination,
    protected TimeInterface $time,
  ) {
    $this->entityTypeId = $entityType->id();
    $this->fieldStorageDefinitions = $entity_last_installed_schema_repository->getLastInstalledFieldStorageDefinitions($this->entityTypeId);
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('language_manager'),
      $container->get('content_translation.manager'),
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('messenger'),
      $container->get('date.formatter'),
      $container->get('entity.last_installed_schema.repository'),
      $container->get('redirect.destination'),
      $container->get('datetime.time'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldDefinitions() {
    $definitions = [];

    $definitions['content_translation_source'] = BaseFieldDefinition::create('language')
      ->setLabel($this->t('Translation source'))
      ->setDescription($this->t('The source language from which this translation was created.'))
      ->setDefaultValue(LanguageInterface::LANGCODE_NOT_SPECIFIED)
      ->setInitialValue(LanguageInterface::LANGCODE_NOT_SPECIFIED)
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE);

    $definitions['content_translation_outdated'] = BaseFieldDefinition::create('boolean')
      ->setLabel($this->t('Translation outdated'))
      ->setDescription($this->t('A boolean indicating whether this translation needs to be updated.'))
      ->setDefaultValue(FALSE)
      ->setInitialValue(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE);

    if (!$this->hasAuthor()) {
      $definitions['content_translation_uid'] = BaseFieldDefinition::create('entity_reference')
        ->setLabel($this->t('Translation author'))
        ->setDescription($this->t('The author of this translation.'))
        ->setSetting('target_type', 'user')
        ->setSetting('handler', 'default')
        ->setRevisionable(TRUE)
        ->setDefaultValueCallback(static::class . '::getDefaultOwnerId')
        ->setTranslatable(TRUE);
    }

    if (!$this->hasPublishedStatus()) {
      $definitions['content_translation_status'] = BaseFieldDefinition::create('boolean')
        ->setLabel($this->t('Translation status'))
        ->setDescription($this->t('A boolean indicating whether the translation is visible to non-translators.'))
        ->setDefaultValue(TRUE)
        ->setInitialValue(TRUE)
        ->setRevisionable(TRUE)
        ->setTranslatable(TRUE);
    }

    if (!$this->hasCreatedTime()) {
      $definitions['content_translation_created'] = BaseFieldDefinition::create('created')
        ->setLabel($this->t('Translation created time'))
        ->setDescription($this->t('The Unix timestamp when the translation was created.'))
        ->setRevisionable(TRUE)
        ->setTranslatable(TRUE);
    }

    if (!$this->hasChangedTime()) {
      $definitions['content_translation_changed'] = BaseFieldDefinition::create('changed')
        ->setLabel($this->t('Translation changed time'))
        ->setDescription($this->t('The Unix timestamp when the translation was most recently saved.'))
        ->setRevisionable(TRUE)
        ->setTranslatable(TRUE);
    }

    return $definitions;
  }

  /**
   * Checks whether the entity type supports author natively.
   *
   * @return bool
   *   TRUE if metadata is natively supported, FALSE otherwise.
   */
  protected function hasAuthor() {
    // Check for field named uid, but only in case the entity implements the
    // EntityOwnerInterface. This helps to exclude cases, where the uid is
    // defined as field name, but is not meant to be an owner field; for
    // instance, the User entity.
    return $this->entityType->entityClassImplements(EntityOwnerInterface::class) && $this->checkFieldStorageDefinitionTranslatability('uid');
  }

  /**
   * Checks whether the entity type supports published status natively.
   *
   * @return bool
   *   TRUE if metadata is natively supported, FALSE otherwise.
   */
  protected function hasPublishedStatus() {
    return $this->checkFieldStorageDefinitionTranslatability('status');
  }

  /**
   * Checks whether the entity type supports modification time natively.
   *
   * @return bool
   *   TRUE if metadata is natively supported, FALSE otherwise.
   */
  protected function hasChangedTime() {
    return $this->entityType->entityClassImplements(EntityChangedInterface::class) && $this->checkFieldStorageDefinitionTranslatability('changed');
  }

  /**
   * Checks whether the entity type supports creation time natively.
   *
   * @return bool
   *   TRUE if metadata is natively supported, FALSE otherwise.
   */
  protected function hasCreatedTime() {
    return $this->checkFieldStorageDefinitionTranslatability('created');
  }

  /**
   * Checks the field storage definition for translatability support.
   *
   * Checks whether the given field is defined in the field storage definitions
   * and if its definition specifies it as translatable.
   *
   * @param string $field_name
   *   The name of the field.
   *
   * @return bool
   *   TRUE if translatable field storage definition exists, FALSE otherwise.
   */
  protected function checkFieldStorageDefinitionTranslatability($field_name) {
    return array_key_exists($field_name, $this->fieldStorageDefinitions) && $this->fieldStorageDefinitions[$field_name]->isTranslatable();
  }

  /**
   * {@inheritdoc}
   */
  public function retranslate(EntityInterface $entity, $langcode = NULL) {
    $updated_langcode = !empty($langcode) ? $langcode : $entity->language()->getId();
    foreach ($entity->getTranslationLanguages() as $langcode => $language) {
      $this->manager->getTranslationMetadata($entity->getTranslation($langcode))
        ->setOutdated($langcode != $updated_langcode);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getTranslationAccess(EntityInterface $entity, $op) {
    // @todo Move this logic into a translation access control handler checking also
    //   the translation language and the given account.
    $entity_type = $entity->getEntityType();
    $translate_permission = TRUE;
    // If no permission granularity is defined this entity type does not need an
    // explicit translate permission.
    if (!$this->currentUser->hasPermission('translate any entity') && $permission_granularity = $entity_type->getPermissionGranularity()) {
      $translate_permission = $this->currentUser->hasPermission($permission_granularity == 'bundle' ? "translate {$entity->bundle()} {$entity->getEntityTypeId()}" : "translate {$entity->getEntityTypeId()}");
    }
    $access = AccessResult::allowedIf(($translate_permission && $this->currentUser->hasPermission("$op content translations")))->cachePerPermissions();
    if (!$access->isAllowed()) {
      return AccessResult::allowedIfHasPermission($this->currentUser, 'translate editable entities')->andIf($entity->access('update', $this->currentUser, TRUE));
    }
    return $access;
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceLangcode(FormStateInterface $form_state) {
    if ($source = $form_state->get(['content_translation', 'source'])) {
      return $source->getId();
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function entityFormAlter(array &$form, FormStateInterface $form_state, EntityInterface $entity) {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */

    $metadata = $this->manager->getTranslationMetadata($entity);
    $form_object = $form_state->getFormObject();
    $form_langcode = $form_object->getFormLangcode($form_state);
    $entity_langcode = $entity->getUntranslated()->language()->getId();

    $new_translation = $entity->isNewTranslation();
    $translations = $entity->getTranslationLanguages();
    if ($new_translation) {
      // Make sure a new translation does not appear as existing yet.
      unset($translations[$form_langcode]);
    }
    $is_translation = $new_translation || ($entity->language()->getId() != $entity_langcode);
    $has_translations = count($translations) > 1;

    // Adjust page title to specify the current language being edited, if we
    // have at least one translation.
    $languages = $this->languageManager->getLanguages();
    if (isset($languages[$form_langcode]) && ($has_translations || $new_translation)) {
      $title = $this->entityFormTitle($entity);
      // When editing the original values display just the entity label.
      if ($is_translation) {
        $t_args = ['%language' => $languages[$form_langcode]->getName(), '%title' => $entity->label(), '@title' => $title];
        $title = $new_translation ? $this->t('Create %language translation of %title', $t_args) : $this->t('@title [%language translation]', $t_args);
      }
      $form['#title'] = $title;
    }

    // Display source language selector only if we are creating a new
    // translation and there are at least two translations available.
    if ($has_translations && $new_translation) {
      $source_langcode = $metadata->getSource();
      $form['source_langcode'] = [
        '#type' => 'details',
        '#title' => $this->t('Source language: @language', ['@language' => $languages[$source_langcode]->getName()]),
        '#tree' => TRUE,
        '#weight' => -100,
        '#multilingual' => TRUE,
        'source' => [
          '#title' => $this->t('Select source language'),
          '#title_display' => 'invisible',
          '#type' => 'select',
          '#default_value' => $source_langcode,
          '#options' => [],
        ],
        'submit' => [
          '#type' => 'submit',
          '#value' => $this->t('Change'),
          '#submit' => [[$this, 'entityFormSourceChange']],
        ],
      ];
      foreach ($this->languageManager->getLanguages() as $language) {
        if (isset($translations[$language->getId()])) {
          $form['source_langcode']['source']['#options'][$language->getId()] = $language->getName();
        }
      }
    }

    // Locate the language widget.
    $langcode_key = $this->entityType->getKey('langcode');
    if (isset($form[$langcode_key])) {
      $language_widget = &$form[$langcode_key];
    }

    // If we are editing the source entity, limit the list of languages so that
    // it is not possible to switch to a language for which a translation
    // already exists. Note that this will only work if the widget is structured
    // like \Drupal\Core\Field\Plugin\Field\FieldWidget\LanguageSelectWidget.
    if (isset($language_widget['widget'][0]['value']) && !$is_translation && $has_translations) {
      $language_select = &$language_widget['widget'][0]['value'];
      if ($language_select['#type'] == 'language_select') {
        $options = [];
        foreach ($this->languageManager->getLanguages() as $language) {
          // Show the current language, and the languages for which no
          // translation already exists.
          if (empty($translations[$language->getId()]) || $language->getId() == $entity_langcode) {
            $options[$language->getId()] = $language->getName();
          }
        }
        $language_select['#options'] = $options;
      }
    }
    if ($is_translation) {
      if (isset($language_widget)) {
        $language_widget['widget']['#access'] = FALSE;
      }

      // Replace the delete button with the delete translation one.
      if (!$new_translation) {
        $weight = 100;
        foreach (['delete', 'submit'] as $key) {
          if (isset($form['actions'][$key]['weight'])) {
            $weight = $form['actions'][$key]['weight'];
            break;
          }
        }
        /** @var \Drupal\Core\Access\AccessResultInterface $delete_access */
        $delete_access = \Drupal::service('content_translation.delete_access')->checkAccess($entity);
        $access = $delete_access->isAllowed() && (
          $this->getTranslationAccess($entity, 'delete')->isAllowed() ||
          ($entity->access('delete') && $this->entityType->hasLinkTemplate('delete-form'))
        );
        $form['actions']['delete_translation'] = [
          '#type' => 'link',
          '#title' => $this->t('Delete translation'),
          '#access' => $access,
          '#weight' => $weight,
          '#url' => $this->entityFormDeleteTranslationUrl($entity, $form_langcode),
          '#attributes' => [
            'class' => ['button', 'button--danger'],
          ],
        ];
      }

      // Always remove the delete button on translation forms.
      unset($form['actions']['delete']);
    }

    // We need to display the translation tab only when there is at least one
    // translation available or a new one is about to be created.
    if ($new_translation || $has_translations) {
      $form['content_translation'] = [
        '#type' => 'details',
        '#title' => $this->t('Translation'),
        '#tree' => TRUE,
        '#weight' => 10,
        '#access' => $this->getTranslationAccess($entity, $new_translation ? 'create' : 'update')->isAllowed(),
        '#multilingual' => TRUE,
      ];

      if (isset($form['advanced'])) {
        $form['content_translation'] += [
          '#group' => 'advanced',
          '#weight' => 100,
          '#attributes' => [
            'class' => ['entity-translation-options'],
          ],
        ];
      }

      // A new translation is enabled by default.
      $status = $new_translation || $metadata->isPublished();
      // If there is only one published translation we cannot unpublish it,
      // since there would be nothing left to display.
      $enabled = TRUE;
      if ($status) {
        $published = 0;
        foreach ($entity->getTranslationLanguages() as $langcode => $language) {
          $published += $this->manager->getTranslationMetadata($entity->getTranslation($langcode))
            ->isPublished();
        }
        $enabled = $published > 1;
      }
      $description = $enabled ?
        $this->t('An unpublished translation will not be visible without translation permissions.') :
        $this->t('Only this translation is published. You must publish at least one more translation to unpublish this one.');

      $form['content_translation']['status'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('This translation is published'),
        '#default_value' => $status,
        '#description' => $description,
        '#disabled' => !$enabled,
      ];

      $translate = !$new_translation && $metadata->isOutdated();
      $outdated_access = !ContentTranslationManager::isPendingRevisionSupportEnabled($entity->getEntityTypeId(), $entity->bundle());
      if (!$outdated_access) {
        $form['content_translation']['outdated'] = [
          '#markup' => $this->t('Translations cannot be flagged as outdated when content is moderated.'),
        ];
      }
      elseif (!$translate) {
        $form['content_translation']['retranslate'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Flag other translations as outdated'),
          '#default_value' => FALSE,
          '#description' => $this->t('If you made a significant change, which means the other translations should be updated, you can flag all translations of this content as outdated. This will not change any other property of them, like whether they are published or not.'),
          '#access' => $outdated_access,
        ];
      }
      else {
        $form['content_translation']['outdated'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('This translation needs to be updated'),
          '#default_value' => $translate,
          '#description' => $this->t('When this option is checked, this translation needs to be updated. Uncheck when the translation is up to date again.'),
          '#access' => $outdated_access,
        ];
        $form['content_translation']['#open'] = TRUE;
      }

      // Default to the anonymous user.
      $uid = 0;
      if ($new_translation) {
        $uid = $this->currentUser->id();
      }
      elseif (($account = $metadata->getAuthor()) && $account->id()) {
        $uid = $account->id();
      }
      $form['content_translation']['uid'] = [
        '#type' => 'entity_autocomplete',
        '#title' => $this->t('Authored by'),
        '#target_type' => 'user',
        '#default_value' => User::load($uid),
        // Validation is done by static::entityFormValidate().
        '#validate_reference' => FALSE,
        '#maxlength' => 1024,
        '#description' => $this->t('Leave blank for %anonymous.', ['%anonymous' => \Drupal::config('user.settings')->get('anonymous')]),
      ];

      $date = $new_translation ? $this->time->getRequestTime() : $metadata->getCreatedTime();
      $form['content_translation']['created'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Authored on'),
        '#maxlength' => 25,
        '#description' => $this->t('Leave blank to use the time of form submission.'),
        '#default_value' => $new_translation || !$date ? '' : $this->dateFormatter->format($date, 'custom', 'Y-m-d H:i:s O'),
      ];

      $form['#process'][] = [$this, 'entityFormSharedElements'];
    }

    // Process the submitted values before they are stored.
    $form['#entity_builders'][] = [$this, 'entityFormEntityBuild'];

    // Handle entity validation.
    $form['#validate'][] = [$this, 'entityFormValidate'];

    // Handle entity deletion.
    if (isset($form['actions']['delete'])) {
      $form['actions']['delete']['#submit'][] = [$this, 'entityFormDelete'];
    }

    // Handle entity form submission before the entity has been saved.
    foreach (Element::children($form['actions']) as $action) {
      if (isset($form['actions'][$action]['#type']) && $form['actions'][$action]['#type'] == 'submit') {
        array_unshift($form['actions'][$action]['#submit'], [$this, 'entityFormSubmit']);
      }
    }
  }

  /**
   * Process callback: determines which elements get clue in the form.
   *
   * @see \Drupal\content_translation\ContentTranslationHandler::entityFormAlter()
   */
  public function entityFormSharedElements($element, FormStateInterface $form_state, $form) {
    static $ignored_types;

    // @todo Find a more reliable way to determine if a form element concerns a
    //   multilingual value.
    if (!isset($ignored_types)) {
      $ignored_types = array_flip(['actions', 'value', 'hidden', 'vertical_tabs', 'token', 'details', 'link']);
    }

    /** @var \Drupal\Core\Entity\ContentEntityForm $form_object */
    $form_object = $form_state->getFormObject();
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $form_object->getEntity();
    $display_translatability_clue = !$entity->isDefaultTranslationAffectedOnly();
    $hide_untranslatable_fields = $entity->isDefaultTranslationAffectedOnly() && !$entity->isDefaultTranslation();
    $translation_form = $form_state->get(['content_translation', 'translation_form']);
    $display_warning = FALSE;

    // We use field definitions to identify untranslatable field widgets to be
    // hidden. Fields that are not involved in translation changes checks should
    // not be affected by this logic (the "revision_log" field, for instance).
    $field_definitions = array_diff_key($entity->getFieldDefinitions(), array_flip($this->getFieldsToSkipFromTranslationChangesCheck($entity)));

    foreach (Element::children($element) as $key) {
      if (!isset($element[$key]['#type'])) {
        $this->entityFormSharedElements($element[$key], $form_state, $form);
      }
      else {
        // Ignore non-widget form elements.
        if (isset($ignored_types[$element[$key]['#type']])) {
          continue;
        }
        // Elements are considered to be non multilingual by default.
        if (empty($element[$key]['#multilingual'])) {
          // If we are displaying a multilingual entity form we need to provide
          // translatability clues, otherwise the non-multilingual form elements
          // should be hidden.
          if (!$translation_form) {
            if ($display_translatability_clue) {
              $this->addTranslatabilityClue($element[$key]);
            }
            // Hide widgets for untranslatable fields.
            if ($hide_untranslatable_fields && isset($field_definitions[$key])) {
              $element[$key]['#access'] = FALSE;
              $display_warning = TRUE;
            }
          }
          else {
            $element[$key]['#access'] = FALSE;
          }
        }
      }
    }

    if ($display_warning) {
      $url = $entity->getUntranslated()->toUrl('edit-form')->toString();
      $message['warning'][] = $this->t('Fields that apply to all languages are hidden to avoid conflicting changes. <a href=":url">Edit them on the original language form</a>.', [':url' => $url]);
      // Explicitly renders this warning message. This prevents repetition on
      // AJAX operations or form submission. Other messages will be rendered in
      // the default location.
      // @see \Drupal\Core\Render\Element\StatusMessages.
      $element['hidden_fields_warning_message'] = [
        '#theme' => 'status_messages',
        '#message_list' => $message,
        '#weight' => -100,
        '#status_headings' => [
          'warning' => $this->t('Warning message'),
        ],
      ];
    }

    return $element;
  }

  /**
   * Adds a clue about the form element translatability.
   *
   * If the given element does not have a #title attribute, the function is
   * recursively applied to child elements.
   *
   * @param array $element
   *   A form element array.
   */
  protected function addTranslatabilityClue(&$element) {
    static $suffix, $fapi_title_elements;

    // Elements which can have a #title attribute according to FAPI Reference.
    if (!isset($suffix)) {
      $suffix = ' <span class="translation-entity-all-languages">(' . $this->t('all languages') . ')</span>';
      $fapi_title_elements = array_flip(['checkbox', 'checkboxes', 'date', 'details', 'fieldset', 'file', 'item', 'password', 'password_confirm', 'radio', 'radios', 'select', 'text_format', 'textarea', 'textfield', 'weight']);
    }

    // Update #title attribute for all elements that are allowed to have a
    // #title attribute according to the Form API Reference. The reason for this
    // check is because some elements have a #title attribute even though it is
    // not rendered; for instance, field containers.
    if (isset($element['#type']) && isset($fapi_title_elements[$element['#type']]) && isset($element['#title'])) {
      $element['#title'] .= $suffix;
    }
    // If the current element does not have a (valid) title, try child elements.
    elseif ($children = Element::children($element)) {
      foreach ($children as $delta) {
        $this->addTranslatabilityClue($element[$delta]);
      }
    }
    // If there are no children, fall back to the current #title attribute if it
    // exists.
    elseif (isset($element['#title'])) {
      $element['#title'] .= $suffix;
    }
  }

  /**
   * Entity builder method.
   *
   * @param string $entity_type
   *   The type of the entity.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity whose form is being built.
   * @param array $form
   *   A nested array form elements comprising the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @see \Drupal\content_translation\ContentTranslationHandler::entityFormAlter()
   */
  public function entityFormEntityBuild($entity_type, EntityInterface $entity, array $form, FormStateInterface $form_state) {
    $form_object = $form_state->getFormObject();
    $form_langcode = $form_object->getFormLangcode($form_state);
    $values = &$form_state->getValue('content_translation', []);

    $metadata = $this->manager->getTranslationMetadata($entity);
    $metadata->setAuthor(!empty($values['uid']) ? User::load($values['uid']) : User::load(0));
    $metadata->setPublished(!empty($values['status']));
    $metadata->setCreatedTime(!empty($values['created']) ? strtotime($values['created']) : $this->time->getRequestTime());

    $metadata->setOutdated(!empty($values['outdated']));
    if (!empty($values['retranslate'])) {
      $this->retranslate($entity, $form_langcode);
    }
  }

  /**
   * Form validation handler for ContentTranslationHandler::entityFormAlter().
   *
   * Validates the submitted content translation metadata.
   */
  public function entityFormValidate($form, FormStateInterface $form_state) {
    if (!$form_state->isValueEmpty('content_translation')) {
      $translation = $form_state->getValue('content_translation');
      // Validate the "authored by" field.
      if (!empty($translation['uid']) && !($account = User::load($translation['uid']))) {
        $form_state->setErrorByName('content_translation][uid', $this->t('The translation authoring username %name does not exist.', ['%name' => $account->getAccountName()]));
      }
      // Validate the "authored on" field.
      if (!empty($translation['created']) && strtotime($translation['created']) === FALSE) {
        $form_state->setErrorByName('content_translation][created', $this->t('You have to specify a valid translation authoring date.'));
      }
    }
  }

  /**
   * Form submission handler for ContentTranslationHandler::entityFormAlter().
   *
   * Updates metadata fields, which should be updated only after the validation
   * has run and before the entity is saved.
   */
  public function entityFormSubmit($form, FormStateInterface $form_state) {
    /** @var \Drupal\Core\Entity\ContentEntityFormInterface $form_object */
    $form_object = $form_state->getFormObject();
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $form_object->getEntity();

    // ContentEntityForm::submit will update the changed timestamp on submit
    // after the entity has been validated, so that it does not break the
    // EntityChanged constraint validator. The content translation metadata
    // field for the changed timestamp  does not have such a constraint defined
    // at the moment, but it is correct to update its value in a submission
    // handler as well and have the same logic like in the Form API.
    if ($entity->hasField('content_translation_changed')) {
      $metadata = $this->manager->getTranslationMetadata($entity);
      $metadata->setChangedTime($this->time->getRequestTime());
    }
  }

  /**
   * Form submission handler for ContentTranslationHandler::entityFormAlter().
   *
   * Takes care of the source language change.
   */
  public function entityFormSourceChange($form, FormStateInterface $form_state) {
    $form_object = $form_state->getFormObject();
    $entity = $form_object->getEntity();
    $source = $form_state->getValue(['source_langcode', 'source']);

    $entity_type_id = $entity->getEntityTypeId();
    $form_state->setRedirect("entity.$entity_type_id.content_translation_add", [
      $entity_type_id => $entity->id(),
      'source' => $source,
      'target' => $form_object->getFormLangcode($form_state),
    ]);
    $languages = $this->languageManager->getLanguages();
    $this->messenger->addStatus($this->t('Source language set to: %language', ['%language' => $languages[$source]->getName()]));
  }

  /**
   * Form submission handler for ContentTranslationHandler::entityFormAlter().
   *
   * Takes care of entity deletion.
   */
  public function entityFormDelete($form, FormStateInterface $form_state) {
    $form_object = $form_state->getFormObject();
    $entity = $form_object->getEntity();
    if (count($entity->getTranslationLanguages()) > 1) {
      $this->messenger->addWarning($this->t('This will delete all the translations of %label.', ['%label' => $entity->label() ?? $entity->id()]));
    }
  }

  /**
   * Form submission handler for ContentTranslationHandler::entityFormAlter().
   *
   * Get the entity delete form route url.
   */
  protected function entityFormDeleteTranslationUrl(EntityInterface $entity, $form_langcode) {
    $entity_type_id = $entity->getEntityTypeId();
    $options = [];
    $options['query']['destination'] = $this->redirectDestination->get();

    if ($entity->access('delete') && $this->entityType->hasLinkTemplate('delete-form')) {
      return $entity->toUrl('delete-form', $options);
    }

    return Url::fromRoute("entity.$entity_type_id.content_translation_delete", [
      $entity_type_id => $entity->id(),
      'language' => $form_langcode,
    ], $options);
  }

  /**
   * Returns the title to be used for the entity form page.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity whose form is being altered.
   *
   * @return string|null
   *   The label of the entity, or NULL if there is no label defined.
   */
  protected function entityFormTitle(EntityInterface $entity) {
    return $entity->label();
  }

  /**
   * Default value callback for the owner base field definition.
   *
   * @return int
   *   The user ID.
   */
  public static function getDefaultOwnerId() {
    return \Drupal::currentUser()->id();
  }

}
