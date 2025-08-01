<?php

namespace Drupal\workspaces\Negotiator;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Site\Settings;
use Drupal\workspaces\WorkspaceInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Defines the query parameter workspace negotiator.
 */
class QueryParameterWorkspaceNegotiator extends SessionWorkspaceNegotiator {

  /**
   * Whether the negotiated workspace should be persisted.
   */
  protected bool $persist = TRUE;

  /**
   * {@inheritdoc}
   */
  public function applies(Request $request) {
    return is_string($request->query->get('workspace'))
      && is_string($request->query->get('token'))
      && parent::applies($request);
  }

  /**
   * {@inheritdoc}
   */
  public function getActiveWorkspaceId(Request $request): ?string {
    $this->persist = (bool) $request->query->get('persist', TRUE);

    $workspace_id = (string) $request->query->get('workspace');
    $token = (string) $request->query->get('token');
    $is_valid_token = hash_equals($this->getQueryToken($workspace_id), $token);

    // This negotiator receives a workspace ID from user input, so a minimal
    // validation is needed to ensure that we protect against fake input before
    // the workspace manager fully validates the negotiated workspace ID.
    // @see \Drupal\workspaces\WorkspaceManager::getActiveWorkspace()
    return $is_valid_token ? $workspace_id : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setActiveWorkspace(WorkspaceInterface $workspace) {
    if ($this->persist) {
      parent::setActiveWorkspace($workspace);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function unsetActiveWorkspace() {
    if ($this->persist) {
      parent::unsetActiveWorkspace();
    }
  }

  /**
   * Returns the query options used by this negotiator.
   *
   * @param string $workspace_id
   *   A workspace ID.
   *
   * @return array
   *   An array of query options that can be used for a \Drupal\Core\Url object.
   */
  public function getQueryOptions(string $workspace_id): array {
    return [
      'workspace' => $workspace_id,
      'token' => $this->getQueryToken($workspace_id),
    ];
  }

  /**
   * Calculates a token based on a workspace ID.
   *
   * @param string $workspace_id
   *   The workspace ID.
   *
   * @return string
   *   An 8 char token based on the given workspace ID.
   */
  protected function getQueryToken(string $workspace_id): string {
    // Return the first 8 characters.
    return substr(Crypt::hmacBase64($workspace_id, Settings::getHashSalt()), 0, 8);
  }

}
