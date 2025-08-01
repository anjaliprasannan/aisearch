<?php

declare(strict_types=1);

namespace Drupal\Tests\jsonapi\Functional;

use Drupal\jsonapi\JsonApiSpec;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Url;
use Drupal\jsonapi\Normalizer\HttpExceptionNormalizer;
use Drupal\jsonapi\Normalizer\Value\CacheableNormalization;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\jsonapi\Traits\CommonCollectionFilterAccessTestPatternsTrait;
use Drupal\Tests\WaitTerminateTestTrait;
use Drupal\user\Entity\User;
use GuzzleHttp\RequestOptions;

/**
 * JSON:API integration test for the "Node" content entity type.
 *
 * @group jsonapi
 */
class NodeTest extends ResourceTestBase {

  use CommonCollectionFilterAccessTestPatternsTrait;
  use WaitTerminateTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'path'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $entityTypeId = 'node';

  /**
   * {@inheritdoc}
   */
  protected static $resourceTypeName = 'node--camelids';

  /**
   * {@inheritdoc}
   */
  protected static $resourceTypeIsVersionable = TRUE;

  /**
   * {@inheritdoc}
   */
  protected static $newRevisionsShouldBeAutomatic = TRUE;

  /**
   * {@inheritdoc}
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  protected static $patchProtectedFieldNames = [
    'revision_timestamp' => NULL,
    'created' => "The 'administer nodes' permission is required.",
    'changed' => NULL,
    'promote' => "The 'administer nodes' permission is required.",
    'sticky' => "The 'administer nodes' permission is required.",
    'path' => "The following permissions are required: 'create url aliases' OR 'administer url aliases'.",
    'revision_uid' => NULL,
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUpAuthorization($method): void {
    switch ($method) {
      case 'GET':
        $this->grantPermissionsToTestedRole(['access content']);
        break;

      case 'POST':
        $this->grantPermissionsToTestedRole(['access content', 'create camelids content']);
        break;

      case 'PATCH':
        // Do not grant the 'create url aliases' permission to test the case
        // when the path field is protected/not accessible, see
        // \Drupal\Tests\rest\Functional\EntityResource\Term\TermResourceTestBase
        // for a positive test.
        $this->grantPermissionsToTestedRole(['access content', 'edit any camelids content']);
        break;

      case 'DELETE':
        $this->grantPermissionsToTestedRole(['access content', 'delete any camelids content']);
        break;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function setUpRevisionAuthorization($method): void {
    parent::setUpRevisionAuthorization($method);
    $this->grantPermissionsToTestedRole(['view all revisions']);
  }

  /**
   * {@inheritdoc}
   */
  protected function createEntity() {
    if (!NodeType::load('camelids')) {
      // Create a "Camelids" node type.
      NodeType::create([
        'name' => 'Camelids',
        'type' => 'camelids',
      ])->save();
    }

    // Create a "Llama" node.
    $node = Node::create(['type' => 'camelids']);
    $node->setTitle('Llama')
      ->setOwnerId($this->account->id())
      ->setPublished()
      ->setCreatedTime(123456789)
      ->setChangedTime(123456789)
      ->setRevisionCreationTime(123456789)
      ->set('path', '/llama')
      ->save();

    return $node;
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedDocument(): array {
    $author = User::load($this->entity->getOwnerId());
    $base_url = Url::fromUri('base:/jsonapi/node/camelids/' . $this->entity->uuid())->setAbsolute();
    $self_url = clone $base_url;
    $version_identifier = 'id:' . $this->entity->getRevisionId();
    $self_url = $self_url->setOption('query', ['resourceVersion' => $version_identifier]);
    $version_query_string = '?resourceVersion=' . urlencode($version_identifier);
    return [
      'jsonapi' => [
        'meta' => [
          'links' => [
            'self' => ['href' => JsonApiSpec::SUPPORTED_SPECIFICATION_PERMALINK],
          ],
        ],
        'version' => JsonApiSpec::SUPPORTED_SPECIFICATION_VERSION,
      ],
      'links' => [
        'self' => ['href' => $base_url->toString()],
      ],
      'data' => [
        'id' => $this->entity->uuid(),
        'type' => 'node--camelids',
        'links' => [
          'self' => ['href' => $self_url->toString()],
        ],
        'attributes' => [
          'created' => '1973-11-29T21:33:09+00:00',
          'changed' => (new \DateTime())->setTimestamp($this->entity->getChangedTime())->setTimezone(new \DateTimeZone('UTC'))->format(\DateTime::RFC3339),
          'default_langcode' => TRUE,
          'langcode' => 'en',
          'path' => [
            'alias' => '/llama',
            'pid' => 1,
            'langcode' => 'en',
          ],
          'promote' => FALSE,
          'revision_timestamp' => '1973-11-29T21:33:09+00:00',
          // @todo Attempt to remove this in https://www.drupal.org/project/drupal/issues/2933518.
          'revision_translation_affected' => TRUE,
          'status' => TRUE,
          'sticky' => FALSE,
          'title' => 'Llama',
          'drupal_internal__nid' => 1,
          'drupal_internal__vid' => 1,
        ],
        'relationships' => [
          'node_type' => [
            'data' => [
              'id' => NodeType::load('camelids')->uuid(),
              'meta' => [
                'drupal_internal__target_id' => 'camelids',
              ],
              'type' => 'node_type--node_type',
            ],
            'links' => [
              'related' => [
                'href' => $base_url->toString() . '/node_type' . $version_query_string,
              ],
              'self' => [
                'href' => $base_url->toString() . '/relationships/node_type' . $version_query_string,
              ],
            ],
          ],
          'uid' => [
            'data' => [
              'id' => $author->uuid(),
              'meta' => [
                'drupal_internal__target_id' => (int) $author->id(),
              ],
              'type' => 'user--user',
            ],
            'links' => [
              'related' => [
                'href' => $base_url->toString() . '/uid' . $version_query_string,
              ],
              'self' => [
                'href' => $base_url->toString() . '/relationships/uid' . $version_query_string,
              ],
            ],
          ],
          'revision_uid' => [
            'data' => [
              'id' => $author->uuid(),
              'meta' => [
                'drupal_internal__target_id' => (int) $author->id(),
              ],
              'type' => 'user--user',
            ],
            'links' => [
              'related' => [
                'href' => $base_url->toString() . '/revision_uid' . $version_query_string,
              ],
              'self' => [
                'href' => $base_url->toString() . '/relationships/revision_uid' . $version_query_string,
              ],
            ],
          ],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getPostDocument(): array {
    return [
      'data' => [
        'type' => 'node--camelids',
        'attributes' => [
          'title' => 'Drama llama',
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedUnauthorizedAccessMessage($method): string {
    switch ($method) {
      case 'GET':
      case 'POST':
      case 'PATCH':
      case 'DELETE':
        return "The 'access content' permission is required.";
    }
    return '';
  }

  /**
   * Tests PATCHing a node's path with and without 'create url aliases'.
   *
   * For a positive test, see the similar test coverage for Term.
   *
   * @see \Drupal\Tests\jsonapi\Functional\TermTest::testPatchPath()
   * @see \Drupal\Tests\rest\Functional\EntityResource\Term\TermResourceTestBase::testPatchPath()
   */
  public function testPatchPath(): void {
    $this->setUpAuthorization('GET');
    $this->setUpAuthorization('PATCH');
    $this->config('jsonapi.settings')->set('read_only', FALSE)->save(TRUE);

    // @todo Remove line below in favor of commented line in https://www.drupal.org/project/drupal/issues/2878463.
    $url = Url::fromRoute(sprintf('jsonapi.%s.individual', static::$resourceTypeName), ['entity' => $this->entity->uuid()]);
    // $url = $this->entity->toUrl('jsonapi');

    // GET node's current normalization.
    $response = $this->request('GET', $url, $this->getAuthenticationRequestOptions());
    $normalization = $this->getDocumentFromResponse($response);

    // Change node's path alias.
    $normalization['data']['attributes']['path']['alias'] .= 's-rule-the-world';

    // Create node PATCH request.
    $request_options = $this->getAuthenticationRequestOptions();
    $request_options[RequestOptions::HEADERS]['Content-Type'] = 'application/vnd.api+json';
    $request_options[RequestOptions::BODY] = Json::encode($normalization);

    // PATCH request: 403 when creating URL aliases unauthorized.
    $response = $this->request('PATCH', $url, $request_options);
    $this->assertResourceErrorResponse(403, "The current user is not allowed to PATCH the selected field (path). The following permissions are required: 'create url aliases' OR 'administer url aliases'.", $url, $response, '/data/attributes/path');

    // Grant permission to create URL aliases.
    $this->grantPermissionsToTestedRole(['create url aliases']);

    // Repeat PATCH request: 200.
    $response = $this->request('PATCH', $url, $request_options);
    $updated_normalization = $this->getDocumentFromResponse($response);
    $this->assertResourceResponse(200, FALSE, $response);
    $this->assertSame($normalization['data']['attributes']['path']['alias'], $updated_normalization['data']['attributes']['path']['alias']);
  }

  /**
   * {@inheritdoc}
   */
  public function testGetIndividual(): void {
    // Cacheable normalizations are written after the response is flushed to
    // the client. We use WaitTerminateTestTrait to wait for Drupal to perform
    // its termination work before continuing.
    $this->setWaitForTerminate();

    parent::testGetIndividual();

    $this->assertCacheableNormalizations();
    // Unpublish node.
    $this->entity->setUnpublished()->save();

    // @todo Remove line below in favor of commented line in https://www.drupal.org/project/drupal/issues/2878463.
    $url = Url::fromRoute(sprintf('jsonapi.%s.individual', static::$resourceTypeName), ['entity' => $this->entity->uuid()]);
    // $url = $this->entity->toUrl('jsonapi');
    $request_options = $this->getAuthenticationRequestOptions();

    // 403 when accessing own unpublished node.
    $response = $this->request('GET', $url, $request_options);
    $this->assertResourceErrorResponse(
      403,
      'The current user is not allowed to GET the selected resource.',
      $url,
      $response,
      '/data',
      ['4xx-response', 'http_response', 'node:1'],
      ['url.query_args', 'url.site', 'user.permissions'],
      'UNCACHEABLE (request policy)',
      TRUE
    );

    // 200 after granting permission.
    $this->grantPermissionsToTestedRole(['view own unpublished content']);
    $response = $this->request('GET', $url, $request_options);
    $this->assertResourceResponse(200, FALSE, $response, $this->getExpectedCacheTags(), $this->getExpectedCacheContexts(), 'UNCACHEABLE (request policy)', TRUE);
  }

  /**
   * Asserts that normalizations are cached in an incremental way.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *
   * @internal
   */
  protected function assertCacheableNormalizations(): void {
    // Save the entity to invalidate caches.
    $this->entity->save();
    $uuid = $this->entity->uuid();
    $language = $this->entity->language()->getId();
    $cache = \Drupal::service('variation_cache.jsonapi_normalizations')->get(['node--camelids', $uuid, $language], new CacheableMetadata());
    // After saving the entity the normalization should not be cached.
    $this->assertFalse($cache);
    // @todo Remove line below in favor of commented line in https://www.drupal.org/project/drupal/issues/2878463.
    $url = Url::fromRoute(sprintf('jsonapi.%s.individual', static::$resourceTypeName), ['entity' => $uuid]);
    // $url = $this->entity->toUrl('jsonapi');
    $request_options = $this->getAuthenticationRequestOptions();
    $request_options[RequestOptions::QUERY] = ['fields' => ['node--camelids' => 'title']];
    $this->request('GET', $url, $request_options);
    // Ensure the normalization cache is being incrementally built. After
    // requesting the title, only the title is in the cache.
    $this->assertNormalizedFieldsAreCached(['title']);
    $request_options[RequestOptions::QUERY] = ['fields' => ['node--camelids' => 'field_rest_test']];
    $this->request('GET', $url, $request_options);
    // After requesting an additional field, then that field is in the cache and
    // the old one is still there.
    $this->assertNormalizedFieldsAreCached(['title', 'field_rest_test']);
  }

  /**
   * Checks that the provided field names are the only fields in the cache.
   *
   * The normalization cache should only have these fields, which build up
   * across responses.
   *
   * @param string[] $field_names
   *   The field names.
   *
   * @internal
   */
  protected function assertNormalizedFieldsAreCached(array $field_names): void {
    $variation_cache = \Drupal::service('variation_cache.jsonapi_normalizations');

    // Because we warm caches in different requests, we do not properly populate
    // the internal properties of our variation cache. Reset it.
    $variation_cache->reset();

    $cache = $variation_cache->get(['node--camelids', $this->entity->uuid(), $this->entity->language()->getId()], new CacheableMetadata());
    $cached_fields = $cache->data['fields'];
    $this->assertSameSize($field_names, $cached_fields);
    array_walk($field_names, function ($field_name) use ($cached_fields) {
      $this->assertInstanceOf(
        CacheableNormalization::class,
        $cached_fields[$field_name]
      );
    });
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedCacheContexts(?array $sparse_fieldset = NULL) {
    // \Drupal\Tests\jsonapi\Functional\ResourceTestBase::testRevisions()
    // loads different revisions via query parameters, we do our best
    // here to react to those directly, or indirectly.
    $cache_contexts = parent::getExpectedCacheContexts($sparse_fieldset);

    // This is bubbled up by
    // \Drupal\node\NodeAccessControlHandler::checkAccess() directly.
    if ($this->entity->isPublished()) {
      return $cache_contexts;
    }
    if (!\Drupal::currentUser()->isAuthenticated()) {
      return Cache::mergeContexts($cache_contexts, ['user.roles:authenticated']);
    }
    if (\Drupal::currentUser()->hasPermission('view own unpublished content')) {
      return Cache::mergeContexts($cache_contexts, ['user']);
    }
    return $cache_contexts;
  }

  /**
   * {@inheritdoc}
   */
  protected static function getIncludePermissions(): array {
    return [
      'uid.node_type' => ['administer users'],
      'uid.roles' => ['administer permissions'],
    ];
  }

  /**
   * Creating relationships to missing resources should be 404 per JSON:API 1.1.
   *
   * @see https://github.com/json-api/json-api/issues/1033
   */
  public function testPostNonExistingAuthor(): void {
    $this->setUpAuthorization('POST');
    $this->config('jsonapi.settings')->set('read_only', FALSE)->save(TRUE);
    $this->grantPermissionsToTestedRole(['administer nodes']);

    $random_uuid = \Drupal::service('uuid')->generate();
    $doc = $this->getPostDocument();
    $doc['data']['relationships']['uid']['data'] = [
      'type' => 'user--user',
      'id' => $random_uuid,
    ];

    // Create node POST request.
    $url = Url::fromRoute(sprintf('jsonapi.%s.collection.post', static::$resourceTypeName));
    $request_options = $this->getAuthenticationRequestOptions();
    $request_options[RequestOptions::HEADERS]['Accept'] = 'application/vnd.api+json';
    $request_options[RequestOptions::HEADERS]['Content-Type'] = 'application/vnd.api+json';
    $request_options[RequestOptions::BODY] = Json::encode($doc);

    // POST request: 404 when adding relationships to non-existing resources.
    $response = $this->request('POST', $url, $request_options);
    $expected_document = [
      'errors' => [
        0 => [
          'status' => '404',
          'title' => 'Not Found',
          'detail' => "The resource identified by `user--user:$random_uuid` (given as a relationship item) could not be found.",
          'links' => [
            'info' => ['href' => HttpExceptionNormalizer::getInfoUrl(404)],
            'via' => ['href' => $url->setAbsolute()->toString()],
          ],
        ],
      ],
      'jsonapi' => static::$jsonApiMember,
    ];
    $this->assertResourceResponse(404, $expected_document, $response);
  }

  /**
   * {@inheritdoc}
   */
  public function testCollectionFilterAccess(): void {
    $label_field_name = 'title';
    $this->doTestCollectionFilterAccessForPublishableEntities($label_field_name, 'access content', 'bypass node access');

    $collection_url = Url::fromRoute('jsonapi.entity_test--bar.collection');
    $collection_filter_url = $collection_url->setOption('query', ["filter[spotlight.$label_field_name]" => $this->entity->label()]);
    $request_options = [];
    $request_options[RequestOptions::HEADERS]['Accept'] = 'application/vnd.api+json';
    $request_options = NestedArray::mergeDeep($request_options, $this->getAuthenticationRequestOptions());

    $this->revokePermissionsFromTestedRole(['bypass node access']);

    // 0 results because the node is unpublished.
    $response = $this->request('GET', $collection_filter_url, $request_options);
    $doc = $this->getDocumentFromResponse($response);
    $this->assertCount(0, $doc['data']);

    $this->grantPermissionsToTestedRole(['view own unpublished content']);

    // 1 result because the current user is the owner of the unpublished node.
    $response = $this->request('GET', $collection_filter_url, $request_options);
    $doc = $this->getDocumentFromResponse($response);
    $this->assertCount(1, $doc['data']);

    $this->entity->setOwnerId(0)->save();

    // 0 results because the current user is no longer the owner.
    $response = $this->request('GET', $collection_filter_url, $request_options);
    $doc = $this->getDocumentFromResponse($response);
    $this->assertCount(0, $doc['data']);

    // Assert bubbling of cacheability from query alter hook.
    $this->assertTrue($this->container->get('module_installer')->install(['node_access_test'], TRUE), 'Installed modules.');
    node_access_rebuild();
    $this->rebuildAll();
    $response = $this->request('GET', $collection_filter_url, $request_options);
    $this->assertContains('user.node_grants:view', explode(' ', $response->getHeader('X-Drupal-Cache-Contexts')[0]));
  }

}
