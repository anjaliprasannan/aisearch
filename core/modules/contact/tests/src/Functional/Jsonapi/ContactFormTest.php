<?php

declare(strict_types=1);

namespace Drupal\Tests\contact\Functional\Jsonapi;

use Drupal\jsonapi\JsonApiSpec;
use Drupal\contact\Entity\ContactForm;
use Drupal\Core\Url;
use Drupal\Tests\jsonapi\Functional\ConfigEntityResourceTestBase;

/**
 * JSON:API integration test for the "ContactForm" config entity type.
 *
 * @group contact
 */
class ContactFormTest extends ConfigEntityResourceTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['contact'];

  /**
   * {@inheritdoc}
   */
  protected static $entityTypeId = 'contact_form';

  /**
   * {@inheritdoc}
   */
  protected static $resourceTypeName = 'contact_form--contact_form';

  /**
   * {@inheritdoc}
   *
   * @var \Drupal\contact\ContactFormInterface
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUpAuthorization($method): void {
    $this->grantPermissionsToTestedRole(['access site-wide contact form']);
  }

  /**
   * {@inheritdoc}
   */
  protected function createEntity() {
    $contact_form = ContactForm::create([
      'id' => 'llama',
      'label' => 'Llama',
      'message' => 'Let us know what you think about llamas',
      'reply' => 'Llamas are indeed awesome!',
      'recipients' => [
        'llama@example.com',
        'contact@example.com',
      ],
    ]);
    $contact_form->save();

    return $contact_form;
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedDocument(): array {
    $self_url = Url::fromUri('base:/jsonapi/contact_form/contact_form/' . $this->entity->uuid())->setAbsolute()->toString(TRUE)->getGeneratedUrl();
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
        'self' => ['href' => $self_url],
      ],
      'data' => [
        'id' => $this->entity->uuid(),
        'type' => 'contact_form--contact_form',
        'links' => [
          'self' => ['href' => $self_url],
        ],
        'attributes' => [
          'dependencies' => [],
          'label' => 'Llama',
          'langcode' => 'en',
          'message' => 'Let us know what you think about llamas',
          'recipients' => [
            'llama@example.com',
            'contact@example.com',
          ],
          'redirect' => NULL,
          'reply' => 'Llamas are indeed awesome!',
          'status' => TRUE,
          'weight' => 0,
          'drupal_internal__id' => 'llama',
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getPostDocument(): array {
    // @todo Update in https://www.drupal.org/node/2300677.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedUnauthorizedAccessMessage($method): string {
    return "The 'access site-wide contact form' permission is required.";
  }

}
