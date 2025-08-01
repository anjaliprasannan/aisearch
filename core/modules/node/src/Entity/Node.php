<?php

namespace Drupal\node\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\EditorialContentEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\Form\DeleteMultiple;
use Drupal\node\Form\NodeDeleteForm;
use Drupal\node\Form\NodeForm;
use Drupal\node\NodeAccessControlHandler;
use Drupal\node\NodeInterface;
use Drupal\node\NodeListBuilder;
use Drupal\node\NodeStorage;
use Drupal\node\NodeStorageSchema;
use Drupal\node\NodeTranslationHandler;
use Drupal\node\NodeViewBuilder;
use Drupal\node\NodeViewsData;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the node entity class.
 */
#[ContentEntityType(
  id: 'node',
  label: new TranslatableMarkup('Content'),
  label_collection: new TranslatableMarkup('Content'),
  label_singular: new TranslatableMarkup('content item'),
  label_plural: new TranslatableMarkup('content items'),
  entity_keys: [
    'id' => 'nid',
    'revision' => 'vid',
    'bundle' => 'type',
    'label' => 'title',
    'langcode' => 'langcode',
    'uuid' => 'uuid',
    'status' => 'status',
    'published' => 'status',
    'uid' => 'uid',
    'owner' => 'uid',
  ],
  handlers: [
    'storage' => NodeStorage::class,
    'storage_schema' => NodeStorageSchema::class,
    'view_builder' => NodeViewBuilder::class,
    'access' => NodeAccessControlHandler::class,
    'views_data' => NodeViewsData::class,
    'form' => [
      'default' => NodeForm::class,
      'delete' => NodeDeleteForm::class,
      'edit' => NodeForm::class,
      'delete-multiple-confirm' => DeleteMultiple::class,
    ],
    'route_provider' => [
      'html' => NodeRouteProvider::class,
    ],
    'list_builder' => NodeListBuilder::class,
    'translation' => NodeTranslationHandler::class,
  ],
  links: [
    'canonical' => '/node/{node}',
    'delete-form' => '/node/{node}/delete',
    'delete-multiple-form' => '/admin/content/node/delete',
    'edit-form' => '/node/{node}/edit',
    'version-history' => '/node/{node}/revisions',
    'revision' => '/node/{node}/revisions/{node_revision}/view',
    'create' => '/node',
  ],
  collection_permission: 'access content overview',
  permission_granularity: 'bundle',
  bundle_entity_type: 'node_type',
  bundle_label: new TranslatableMarkup('Content type'),
  base_table: 'node',
  data_table: 'node_field_data',
  revision_table: 'node_revision',
  revision_data_table: 'node_field_revision',
  translatable: TRUE,
  show_revision_ui: TRUE,
  label_count: [
    'singular' => '@count content item',
    'plural' => '@count content items',
  ],
  field_ui_base_route: 'entity.node_type.edit_form',
  common_reference_target: TRUE,
  list_cache_contexts: ['user.node_grants:view'],
  revision_metadata_keys: [
    'revision_user' => 'revision_uid',
    'revision_created' => 'revision_timestamp',
    'revision_log_message' => 'revision_log',
  ],
)]
class Node extends EditorialContentEntityBase implements NodeInterface {

  use EntityOwnerTrait;

  /**
   * Whether the node is being previewed or not.
   *
   * The variable is set to public as it will give a considerable performance
   * improvement. See https://www.drupal.org/node/2498919.
   *
   * @var true|null
   *   TRUE if the node is being previewed and NULL if it is not.
   */
  // phpcs:ignore Drupal.NamingConventions.ValidVariableName.LowerCamelName, Drupal.Commenting.VariableComment.Missing
  public $in_preview = NULL;

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    foreach (array_keys($this->getTranslationLanguages()) as $langcode) {
      $translation = $this->getTranslation($langcode);

      // If no owner has been set explicitly, make the anonymous user the owner.
      if (!$translation->getOwner()) {
        $translation->setOwnerId(0);
      }
    }

    // If no revision author has been set explicitly, make the node owner the
    // revision author.
    if (!$this->getRevisionUser()) {
      $this->setRevisionUserId($this->getOwnerId());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function preSaveRevision(EntityStorageInterface $storage, \stdClass $record) {
    parent::preSaveRevision($storage, $record);

    if (!$this->isNewRevision() && $this->getOriginal() && (!isset($record->revision_log) || $record->revision_log === '')) {
      // If we are updating an existing node without adding a new revision, we
      // need to make sure $entity->revision_log is reset whenever it is empty.
      // Therefore, this code allows us to avoid clobbering an existing log
      // entry with an empty one.
      $record->revision_log = $this->getOriginal()->revision_log->value;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    if ($update && \Drupal::moduleHandler()->moduleExists('search')) {
      // Remove deleted translations from the search index.
      foreach ($this->translations as $langcode => $translation) {
        if ($translation['status'] === static::TRANSLATION_REMOVED) {
          \Drupal::service('search.index')->clear('node_search', $this->id(), $langcode);
        }
      }
    }

    parent::postSave($storage, $update);

    // Update the node access table for this node, but only if it is the
    // default revision. There's no need to delete existing records if the node
    // is new.
    if ($this->isDefaultRevision()) {
      /** @var \Drupal\node\NodeAccessControlHandlerInterface $access_control_handler */
      $access_control_handler = \Drupal::entityTypeManager()->getAccessControlHandler('node');
      $grants = $access_control_handler->acquireGrants($this);
      \Drupal::service('node.grant_storage')->write($this, $grants, NULL, $update);
    }

    // Reindex the node when it is updated. The node is automatically indexed
    // when it is added, simply by being added to the node table.
    if ($update) {
      node_reindex_node_search($this->id());
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function preDelete(EntityStorageInterface $storage, array $entities) {
    parent::preDelete($storage, $entities);

    // Ensure that all nodes deleted are removed from the search index.
    if (\Drupal::hasService('search.index')) {
      /** @var \Drupal\search\SearchIndexInterface $search_index */
      $search_index = \Drupal::service('search.index');
      foreach ($entities as $entity) {
        $search_index->clear('node_search', $entity->nid->value);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function postDelete(EntityStorageInterface $storage, array $nodes) {
    parent::postDelete($storage, $nodes);
    \Drupal::service('node.grant_storage')->deleteNodeRecords(array_keys($nodes));
  }

  /**
   * {@inheritdoc}
   */
  public function getType() {
    return $this->bundle();
  }

  /**
   * {@inheritdoc}
   */
  public function access($operation = 'view', ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    // This override exists to set the operation to the default value "view".
    return parent::access($operation, $account, $return_as_object);
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    return $this->get('title')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setTitle($title) {
    $this->set('title', $title);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isPromoted() {
    return (bool) $this->get('promote')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setPromoted($promoted) {
    $this->set('promote', $promoted ? NodeInterface::PROMOTED : NodeInterface::NOT_PROMOTED);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isSticky() {
    return (bool) $this->get('sticky')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setSticky($sticky) {
    $this->set('sticky', $sticky ? NodeInterface::STICKY : NodeInterface::NOT_STICKY);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title'))
      ->setRequired(TRUE)
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -5,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['uid']
      ->setLabel(t('Authored by'))
      ->setDescription(t('The username of the content author.'))
      ->setRevisionable(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'author',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 5,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['status']
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'settings' => [
          'display_label' => TRUE,
        ],
        'weight' => 120,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Authored on'))
      ->setDescription(t('The date and time that the content was created.'))
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'timestamp',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'datetime_timestamp',
        'weight' => 10,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the node was last edited.'))
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE);

    $fields['promote'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Promoted to front page'))
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDefaultValue(FALSE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'settings' => [
          'display_label' => TRUE,
        ],
        'weight' => 15,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['sticky'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Sticky at top of lists'))
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDefaultValue(FALSE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'settings' => [
          'display_label' => TRUE,
        ],
        'weight' => 16,
      ])
      ->setDisplayConfigurable('form', TRUE);

    return $fields;
  }

}
