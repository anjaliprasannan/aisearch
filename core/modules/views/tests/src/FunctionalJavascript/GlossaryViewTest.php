<?php

declare(strict_types=1);

namespace Drupal\Tests\views\FunctionalJavascript;

use Drupal\Core\Url;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\views\Tests\ViewTestData;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the basic AJAX functionality of the Glossary View.
 */
#[Group('node')]
class GlossaryViewTest extends WebDriverTestBase {

  use ContentTypeCreationTrait;
  use NodeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'language',
    'node',
    'views',
    'views_test_config',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'starterkit_theme';

  /**
   * @var array
   * The test Views to enable.
   */
  public static $testViews = ['test_glossary'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    ViewTestData::createTestViews(static::class, ['views_test_config']);

    // Create a Content type and some test nodes with titles that start with
    // different letters.
    $this->createContentType(['type' => 'page']);

    $titles = [
      'Page One',
      'Page Two',
      'Another page',
    ];
    foreach ($titles as $title) {
      $this->createNode([
        'title' => $title,
        'language' => 'en',
      ]);
      $this->createNode([
        'title' => $title,
        'language' => 'nl',
      ]);
    }

    // Create a user privileged enough to use exposed filters and view content.
    $user = $this->drupalCreateUser([
      'administer site configuration',
      'access content',
      'access content overview',
    ]);
    $this->drupalLogin($user);
  }

  /**
   * Tests the AJAX callbacks for the glossary view.
   */
  public function testGlossaryDefault(): void {
    // Visit the default Glossary page.
    $url = Url::fromRoute('view.test_glossary.page_1');
    $this->drupalGet($url);

    $session = $this->getSession();
    $web_assert = $this->assertSession();

    $page = $session->getPage();
    $rows = $page->findAll('css', '.view-test-glossary tr');
    // We expect 2 rows plus the header row.
    $this->assertCount(3, $rows);
    // Click on the P link, this should show 4 rows plus the header row.
    $page->clickLink('P');
    $web_assert->assertWaitOnAjaxRequest();
    $rows = $page->findAll('css', '.view-test-glossary tr');
    $this->assertCount(5, $rows);
  }

  /**
   * Tests that the glossary also works on a language prefixed URL.
   */
  public function testGlossaryLanguagePrefix(): void {
    ConfigurableLanguage::createFromLangcode('nl')->save();

    $config = $this->config('language.negotiation');
    $config->set('url.prefixes', ['en' => 'en', 'nl' => 'nl'])
      ->save();

    \Drupal::service('kernel')->rebuildContainer();

    $url = Url::fromRoute('view.test_glossary.page_1');
    $this->drupalGet($url);

    $session = $this->getSession();
    $web_assert = $this->assertSession();

    $page = $session->getPage();

    $rows = $page->findAll('css', '.view-test-glossary tr');
    // We expect 2 rows plus the header row.
    $this->assertCount(3, $rows);
    // Click on the P link, this should show 4 rows plus the header row.
    $page->clickLink('P');
    $web_assert->assertWaitOnAjaxRequest();

    $rows = $page->findAll('css', '.view-test-glossary tr');
    $this->assertCount(5, $rows);
  }

}
