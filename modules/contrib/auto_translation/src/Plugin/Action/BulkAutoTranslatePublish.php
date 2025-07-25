<?php

namespace Drupal\auto_translation\Plugin\Action;

use Drupal\Core\Action\ActionBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Auto translate and publish action.
 *
 * @Action(
 *   id = "auto_translation_bulk_auto_translate_publish_action",
 *   label = @Translation("Auto Translate and Publish"),
 *   type = ""
 * )
 */
class BulkAutoTranslatePublish extends ActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    if ($entity instanceof ContentEntityInterface) {
      // Get the original language of the entity.
      $d_lang = $entity->language()->getId();
      $languages = \Drupal::service('language_manager')->getLanguages();
      $form = [];
      $action_value = "publish_action";
      $action = $action_value;
      $chunk = [];

      foreach ($languages as $lang) {
        if ($entity->hasTranslation($lang->getId())) {
          continue;
        }

        if ($lang->getId() !== $d_lang) {
          $t_lang = $lang->getId();
          $chunk[] = $t_lang;
          // Translate entity.
          \Drupal::service('auto_translation.utility')
            ->formTranslate($form, $form, $entity, $t_lang, $d_lang, $action, $chunk);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $auto_translation_service = \Drupal::service('auto_translation.utility');
    if ($auto_translation_service->hasPermission() === FALSE) {
      // If the user does not have the permission, return FALSE.
      $permission = 'auto translation translate content';
      \Drupal::logger('auto_translation')->error($this->t('The user does not have the permission "@permission", needed to translate content with Auto Translate module.', ['@permission' => $permission]));
      \Drupal::messenger()->addError($this->t('The user does not have the permission "@permission", needed to translate content with Auto Translate module.', ['@permission' => $permission]));
      return FALSE;
    }
    return $object instanceof ContentEntityInterface && $object->access('update', $account, $return_as_object);
  }

}
