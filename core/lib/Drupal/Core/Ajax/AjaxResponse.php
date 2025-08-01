<?php

namespace Drupal\Core\Ajax;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\AttachmentsInterface;
use Drupal\Core\Render\AttachmentsTrait;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * JSON response object for AJAX requests.
 *
 * @ingroup ajax
 */
class AjaxResponse extends JsonResponse implements AttachmentsInterface {

  use AttachmentsTrait;

  /**
   * The array of ajax commands.
   *
   * @var array
   */
  protected $commands = [];

  /**
   * Add an AJAX command to the response.
   *
   * @param \Drupal\Core\Ajax\CommandInterface $command
   *   An AJAX command object implementing CommandInterface.
   * @param bool $prepend
   *   A boolean which determines whether the new command should be executed
   *   before previously added commands. Defaults to FALSE.
   *
   * @return $this
   *   The current AjaxResponse.
   */
  public function addCommand(CommandInterface $command, $prepend = FALSE) {
    if ($prepend) {
      array_unshift($this->commands, $command->render());
    }
    else {
      $this->commands[] = $command->render();
    }
    if ($command instanceof CommandWithAttachedAssetsInterface) {
      $assets = $command->getAttachedAssets();
      $attachments = [
        'library' => $assets->getLibraries(),
        'drupalSettings' => $assets->getSettings(),
      ];
      $attachments = BubbleableMetadata::mergeAttachments($this->getAttachments(), $attachments);
      $this->setAttachments($attachments);
    }

    return $this;
  }

  /**
   * Merges other ajax response with this one.
   *
   * Adds commands and merges attachments from the other ajax response.
   *
   * @param \Drupal\Core\Ajax\AjaxResponse $other
   *   An AJAX response to merge.
   *
   * @return $this
   *   Returns this after merging.
   */
  public function mergeWith(AjaxResponse $other): AjaxResponse {
    $this->commands = array_merge($this->getCommands(), $other->getCommands());
    $this->attachments = BubbleableMetadata::mergeAttachments($this->getAttachments(), $other->getAttachments());
    return $this;
  }

  /**
   * Gets all AJAX commands.
   *
   * @return array
   *   Returns render arrays for all previously added commands.
   */
  public function &getCommands() {
    return $this->commands;
  }

}
