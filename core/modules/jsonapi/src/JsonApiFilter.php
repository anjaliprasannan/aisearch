<?php

declare(strict_types=1);

namespace Drupal\jsonapi;

/**
 * JsonApi filter options.
 */
final class JsonApiFilter {

  /**
   * Array key for denoting type-based filtering access.
   *
   * Array key for denoting access to filter among all entities of a given type,
   * regardless of whether they are published or enabled, and regardless of
   * their owner.
   *
   * @see hook_jsonapi_entity_filter_access()
   * @see hook_jsonapi_ENTITY_TYPE_filter_access()
   */
  const AMONG_ALL = 'filter_among_all';

  /**
   * Array key for denoting type-based published-only filtering access.
   *
   * Array key for denoting access to filter among all published entities of a
   * given type, regardless of their owner.
   *
   * This is used when an entity type has a "published" entity key and there's a
   * query condition for the value of that equaling 1.
   *
   * @see hook_jsonapi_entity_filter_access()
   * @see hook_jsonapi_ENTITY_TYPE_filter_access()
   */
  const AMONG_PUBLISHED = 'filter_among_published';

  /**
   * Array key for denoting type-based enabled-only filtering access.
   *
   * Array key for denoting access to filter among all enabled entities of a
   * given type, regardless of their owner.
   *
   * This is used when an entity type has a "status" entity key and there's a
   * query condition for the value of that equaling 1.
   *
   * For the User entity type, which does not have a "status" entity key, the
   * "status" field is used.
   *
   * @see hook_jsonapi_entity_filter_access()
   * @see hook_jsonapi_ENTITY_TYPE_filter_access()
   */
  const AMONG_ENABLED = 'filter_among_enabled';

  /**
   * Array key for denoting type-based owned-only filtering access.
   *
   * Array key for denoting access to filter among all entities of a given type,
   * regardless of whether they are published or enabled, so long as they are
   * owned by the user for whom access is being checked.
   *
   * When filtering among User entities, this is used when access is being
   * checked for an authenticated user and there's a query condition
   * limiting the result set to just that user's entity object.
   *
   * When filtering among entities of another type, this is used when all of the
   * following conditions are met:
   * - Access is being checked for an authenticated user.
   * - The entity type has an "owner" entity key.
   * - There's a filter/query condition for the value equal to the user's ID.
   *
   * @see hook_jsonapi_entity_filter_access()
   * @see hook_jsonapi_ENTITY_TYPE_filter_access()
   */
  const AMONG_OWN = 'filter_among_own';

}
