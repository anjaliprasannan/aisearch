<?php

declare(strict_types=1);

namespace Drupal\FunctionalTests\Rest;

use Drupal\Tests\rest\Functional\AnonResourceTestTrait;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Base Field Override Json Anon.
 */
#[Group('rest')]
class BaseFieldOverrideJsonAnonTest extends BaseFieldOverrideResourceTestBase {

  use AnonResourceTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $format = 'json';

  /**
   * {@inheritdoc}
   */
  protected static $mimeType = 'application/json';

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

}
