<?php

declare(strict_types=1);

namespace Drupal\Tests\announcements_feed\FunctionalJavascript;

use Drupal\announce_feed_test\AnnounceTestHttpClientMiddleware;
use Drupal\Tests\system\FunctionalJavascript\OffCanvasTestBase;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Test the access announcement according to json feed changes.
 */
#[Group('announcements_feed')]
class AlertsJsonFeedTest extends OffCanvasTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'toolbar',
    'announcements_feed',
    'announce_feed_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A user with permission to access toolbar and access announcements.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $user;

  /**
   * {@inheritdoc}
   */
  public function setUp():void {
    if ($this->name() === 'testAnnounceFeedUpdatedAndRemoved') {
      $this->markTestSkipped('Skipped due to major version-specific logic. See https://www.drupal.org/project/drupal/issues/3359322');
    }

    parent::setUp();

    $this->user = $this->drupalCreateUser(
      [
        'access toolbar',
        'access announcements',
      ]
    );

    AnnounceTestHttpClientMiddleware::setAnnounceTestEndpoint('/announce-feed-json/community-feeds');
  }

  /**
   * Check the status of the announcements when the feed is updated and removed.
   */
  public function testAnnounceFeedUpdatedAndRemoved(): void {
    $this->drupalLogin($this->user);
    $this->drupalGet('<front>');
    $this->clickLink('Announcements');
    $this->waitForOffCanvasToOpen();
    $page_html = $this->getSession()->getPage()->getHtml();
    $this->assertStringNotContainsString('Only 10 - Drupal 106 is available and this feed is Updated', $page_html);

    // Change the feed url and reset temp storage.
    AnnounceTestHttpClientMiddleware::setAnnounceTestEndpoint('/announce-feed-json/updated');

    $this->drupalGet('<front>');
    $this->clickLink('Announcements');
    $this->waitForOffCanvasToOpen();
    $page_html = $this->getSession()->getPage()->getHtml();
    $this->assertStringContainsString('Only 10 - Drupal 106 is available and this feed is Updated', $page_html);
    $this->drupalLogout();

    // Change the feed url and reset temp storage.
    AnnounceTestHttpClientMiddleware::setAnnounceTestEndpoint('/announce-feed-json/removed');
    $this->drupalLogin($this->user);
    $this->drupalGet('<front>');
    $this->clickLink('Announcements');
    $this->waitForOffCanvasToOpen();
    $page_html = $this->getSession()->getPage()->getHtml();
    $this->assertStringNotContainsString('Only 10 - Drupal 106 is available and this feed is Updated', $page_html);
  }

  /**
   * Check with an empty JSON feed.
   */
  public function testAnnounceFeedEmpty(): void {
    // Change the feed url and reset temp storage.
    AnnounceTestHttpClientMiddleware::setAnnounceTestEndpoint('/announce-feed-json/empty');

    $this->drupalLogin($this->user);
    $this->drupalGet('<front>');

    // Removed items should not display in the announcement model.
    $this->clickLink('Announcements');
    $this->waitForOffCanvasToOpen();
    $this->assertStringContainsString('No announcements available', $this->getSession()->getPage()->getHtml());
  }

}
