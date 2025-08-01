<?php

declare(strict_types=1);

namespace Drupal\FunctionalJavascriptTests\Dialog;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the JavaScript functionality of the dialog position.
 */
#[Group('dialog')]
class DialogPositionTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['block'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests if the dialog UI works properly with block layout page.
   */
  public function testDialogOpenAndClose(): void {
    $admin_user = $this->drupalCreateUser(['administer blocks']);
    $this->drupalLogin($admin_user);
    $this->drupalGet('admin/structure/block');
    $session = $this->getSession();
    $assert_session = $this->assertSession();
    $page = $session->getPage();

    // Open the dialog using the place block link.
    $placeBlockLink = $page->findLink('Place block');
    $this->assertTrue($placeBlockLink->isVisible(), 'Place block button exists.');
    $placeBlockLink->click();
    $assert_session->assertWaitOnAjaxRequest();
    $dialog = $page->find('css', '.ui-dialog');
    $this->assertTrue($dialog->isVisible(), 'Dialog is opened after clicking the Place block button.');

    // Close the dialog again.
    $closeButton = $page->find('css', '.ui-dialog-titlebar-close');
    $closeButton->click();
    $dialog = $page->find('css', '.ui-dialog');
    $this->assertNull($dialog, 'Dialog is closed after clicking the close button.');

    // Resize the window. The test should pass after waiting for JavaScript to
    // finish as no Javascript errors should have been triggered. If there were
    // javascript errors the test will fail on that.
    $session->resizeWindow(625, 625);
    usleep(5000);
  }

}
