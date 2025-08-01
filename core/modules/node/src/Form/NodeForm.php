<?php

namespace Drupal\node\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Utility\Error;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form handler for the node edit forms.
 *
 * @internal
 */
class NodeForm extends ContentEntityForm {

  /**
   * The tempstore factory.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * The Current User object.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Constructs a NodeForm object.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
   *   The factory for the temp store object.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   */
  public function __construct(
    EntityRepositoryInterface $entity_repository,
    PrivateTempStoreFactory $temp_store_factory,
    EntityTypeBundleInfoInterface $entity_type_bundle_info,
    TimeInterface $time,
    AccountInterface $current_user,
    DateFormatterInterface $date_formatter,
  ) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
    $this->tempStoreFactory = $temp_store_factory;
    $this->currentUser = $current_user;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('tempstore.private'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('current_user'),
      $container->get('date.formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    // Try to restore from temp store, this must be done before calling
    // parent::form().
    $store = $this->tempStoreFactory->get('node_preview');

    // Because of the temp store integration, this is not cacheable.
    // @todo add the correct cache contexts in https://www.drupal.org/project/drupal/issues/3397987
    $form['#cache']['max-age'] = 0;

    // Attempt to load from preview when the uuid is present unless we are
    // rebuilding the form.
    $request_uuid = \Drupal::request()->query->get('uuid');
    if (!$form_state->isRebuilding() && $request_uuid && $preview = $store->get($request_uuid)) {
      /** @var \Drupal\Core\Form\FormStateInterface $preview */

      $form_state->setStorage($preview->getStorage());
      $form_state->setUserInput($preview->getUserInput());

      // Rebuild the form.
      $form_state->setRebuild();

      // The combination of having user input and rebuilding the form means
      // that it will attempt to cache the form state which will fail if it is
      // a GET request.
      $form_state->setRequestMethod('POST');

      $this->entity = $preview->getFormObject()->getEntity();
      $this->entity->in_preview = NULL;

      $form_state->set('has_been_previewed', TRUE);
    }

    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->entity;

    if ($this->operation == 'edit') {
      $form['#title'] = $this->t('<em>Edit @type</em> @title', [
        '@type' => $node->getBundleEntity()->label(),
        '@title' => $node->label(),
      ]);
    }

    // Changed must be sent to the client, for later overwrite error checking.
    $form['changed'] = [
      '#type' => 'hidden',
      '#default_value' => $node->getChangedTime(),
    ];

    $form = parent::form($form, $form_state);

    $form['advanced']['#attributes']['class'][] = 'entity-meta';

    $form['meta'] = [
      '#type' => 'details',
      '#group' => 'advanced',
      '#weight' => -10,
      '#title' => $this->t('Status'),
      '#attributes' => ['class' => ['entity-meta__header']],
      '#tree' => TRUE,
      '#access' => $this->currentUser->hasPermission('administer nodes'),
    ];
    $form['meta']['published'] = [
      '#type' => 'item',
      '#markup' => $node->isPublished() ? $this->t('Published') : $this->t('Not published'),
      '#access' => !$node->isNew(),
      '#wrapper_attributes' => ['class' => ['entity-meta__title']],
    ];
    $form['meta']['changed'] = [
      '#type' => 'item',
      '#title' => $this->t('Last saved'),
      '#markup' => !$node->isNew() ? $this->dateFormatter->format($node->getChangedTime(), 'short') : $this->t('Not saved yet'),
      '#wrapper_attributes' => ['class' => ['entity-meta__last-saved']],
    ];
    $form['meta']['author'] = [
      '#type' => 'item',
      '#title' => $this->t('Author'),
      '#markup' => $node->getOwner()?->getDisplayName(),
      '#wrapper_attributes' => ['class' => ['entity-meta__author']],
    ];

    $form['status']['#group'] = 'footer';

    // Node author information for administrators.
    $form['author'] = [
      '#type' => 'details',
      '#title' => $this->t('Authoring information'),
      '#group' => 'advanced',
      '#attributes' => [
        'class' => ['node-form-author'],
      ],
      '#attached' => [
        'library' => ['node/drupal.node'],
      ],
      '#weight' => 90,
      '#optional' => TRUE,
    ];

    if (isset($form['uid'])) {
      $form['uid']['#group'] = 'author';
    }

    if (isset($form['created'])) {
      $form['created']['#group'] = 'author';
    }

    // Node options for administrators.
    $form['options'] = [
      '#type' => 'details',
      '#title' => $this->t('Promotion options'),
      '#group' => 'advanced',
      '#attributes' => [
        'class' => ['node-form-options'],
      ],
      '#attached' => [
        'library' => ['node/drupal.node'],
      ],
      '#weight' => 95,
      '#optional' => TRUE,
    ];

    if (isset($form['promote'])) {
      $form['promote']['#group'] = 'options';
    }

    if (isset($form['sticky'])) {
      $form['sticky']['#group'] = 'options';
    }

    $form['#attached']['library'][] = 'node/form';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $element = parent::actions($form, $form_state);
    $node = $this->entity;
    $preview_mode = $node->type->entity->getPreviewMode();

    $element['submit']['#access'] = $preview_mode != DRUPAL_REQUIRED || $form_state->get('has_been_previewed');

    $element['preview'] = [
      '#type' => 'submit',
      '#access' => $preview_mode != DRUPAL_DISABLED && ($node->access('create') || $node->access('update')),
      '#value' => $this->t('Preview'),
      '#weight' => 20,
      '#submit' => ['::submitForm', '::preview'],
    ];

    if (array_key_exists('delete', $element)) {
      $element['delete']['#weight'] = 100;
    }

    return $element;
  }

  /**
   * Form submission handler for the 'preview' action.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function preview(array $form, FormStateInterface $form_state) {
    $store = $this->tempStoreFactory->get('node_preview');
    $this->entity->in_preview = TRUE;
    $store->set($this->entity->uuid(), $form_state);

    $route_parameters = [
      'node_preview' => $this->entity->uuid(),
      'view_mode_id' => 'full',
    ];

    $options = [];
    $query = $this->getRequest()->query;
    if ($query->has('destination')) {
      $options['query']['destination'] = $query->get('destination');
      $query->remove('destination');
    }
    $form_state->setRedirect('entity.node.preview', $route_parameters, $options);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $node = $this->entity;
    $insert = $node->isNew();

    try {
      $node->save();
      $node_link = $node->toLink($this->t('View'))->toString();
      $context = ['@type' => $node->getType(), '%title' => $node->label(), 'link' => $node_link];
      $t_args = ['@type' => $node->getBundleEntity()->label(), '%title' => $node->access('view') ? $node->toLink()->toString() : $node->label()];

      if ($insert) {
        $this->logger('content')->info('@type: added %title.', $context);
        $this->messenger()->addStatus($this->t('@type %title has been created.', $t_args));
      }
      else {
        $this->logger('content')->info('@type: updated %title.', $context);
        $this->messenger()->addStatus($this->t('@type %title has been updated.', $t_args));
      }

      $form_state->setValue('nid', $node->id());
      $form_state->set('nid', $node->id());
      if ($node->access('view')) {
        $form_state->setRedirect(
          'entity.node.canonical',
          ['node' => $node->id()],
          ['language' => $node->language()]
        );
      }
      else {
        $form_state->setRedirect('<front>');
      }

      // Remove the preview entry from the temp store, if any.
      $store = $this->tempStoreFactory->get('node_preview');
      $store->delete($node->uuid());
    }
    catch (\Exception $e) {
      // In the unlikely case something went wrong on save, the node will be
      // rebuilt and node form redisplayed.
      $this->messenger()->addError($this->t('The content could not be saved. Contact the site administrator if the problem persists.'));
      // It's likely that this exception is an EntityStorageException in which
      // case we won't have the actual backtrace available. Attempt to get the
      // previous exception if available to include the backtrace.
      $e = $e->getPrevious() ?: $e;
      \Drupal::logger('node')->error('%type saving node form: @message in %function (line %line of %file) @backtrace_string.', Error::decodeException($e));
      $form_state->setRebuild();
    }
  }

}
