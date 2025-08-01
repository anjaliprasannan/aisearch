<?php

namespace Drupal\user\Plugin\views\argument_validator;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;
use Drupal\views\Plugin\views\argument\ArgumentPluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\argument_validator\Entity;

/**
 * Validate whether an argument is a valid user.
 *
 * This supports either numeric arguments (UID) or strings (username) and
 * converts either one into the user's UID.  This validator also sets the
 * argument's title to the username.
 */
class User extends Entity {

  /**
   * The user storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $userStorage;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, ?EntityTypeBundleInfoInterface $entity_type_bundle_info = NULL) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $entity_type_bundle_info);

    $this->userStorage = $entity_type_manager->getStorage('user');
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['restrict_roles'] = ['default' => FALSE];
    $options['roles'] = ['default' => []];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    $sanitized_id = ArgumentPluginBase::encodeValidatorId($this->definition['id']);

    $form['restrict_roles'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Restrict user based on role'),
      '#default_value' => $this->options['restrict_roles'],
    ];

    $roles = Role::loadMultiple();
    unset($roles[RoleInterface::ANONYMOUS_ID]);
    $roles = array_map(fn(RoleInterface $role) => Html::escape($role->label()), $roles);
    $form['roles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Restrict to the selected roles'),
      '#options' => $roles,
      '#default_value' => $this->options['roles'],
      '#description' => $this->t('If no roles are selected, users from any role will be allowed.'),
      '#states' => [
        'visible' => [
          ':input[name="options[validate][options][' . $sanitized_id . '][restrict_roles]"]' => ['checked' => TRUE],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitOptionsForm(&$form, FormStateInterface $form_state, &$options = []) {
    // Filter trash out of the options so we don't store giant unnecessary
    // arrays.
    $options['roles'] = array_filter($options['roles']);
  }

  /**
   * {@inheritdoc}
   */
  protected function validateEntity(EntityInterface $entity) {
    /** @var \Drupal\user\UserInterface $entity */
    $role_check_success = TRUE;
    // See if we're filtering users based on roles.
    if (!empty($this->options['restrict_roles']) && !empty($this->options['roles'])) {
      $roles = $this->options['roles'];
      if (!(bool) array_intersect($entity->getRoles(), $roles)) {
        $role_check_success = FALSE;
      }
    }

    return $role_check_success && parent::validateEntity($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = parent::calculateDependencies();

    foreach ($this->entityTypeManager->getStorage('user_role')->loadMultiple(array_keys($this->options['roles'])) as $role) {
      $dependencies[$role->getConfigDependencyKey()][] = $role->getConfigDependencyName();
    }

    return $dependencies;
  }

}
