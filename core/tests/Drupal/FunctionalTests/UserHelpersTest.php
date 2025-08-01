<?php

declare(strict_types=1);

namespace Drupal\FunctionalTests;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests User related helper methods of BrowserTestBase.
 */
#[Group('browsertestbase')]
class UserHelpersTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests BrowserTestBase::drupalUserIsLoggedIn().
   */
  public function testDrupalUserIsLoggedIn(): void {
    $first_user = $this->drupalCreateUser();
    $second_user = $this->drupalCreateUser();

    // After logging in, the first user should be logged in, the second not.
    $this->drupalLogin($first_user);
    $this->assertTrue($this->drupalUserIsLoggedIn($first_user));
    $this->assertFalse($this->drupalUserIsLoggedIn($second_user));

    // Verify that logged in state is retained across pages.
    $this->drupalGet('');
    $this->assertTrue($this->drupalUserIsLoggedIn($first_user));
    $this->assertFalse($this->drupalUserIsLoggedIn($second_user));

    // After logging out, both users should be logged out.
    $this->drupalLogout();
    $this->assertFalse($this->drupalUserIsLoggedIn($first_user));
    $this->assertFalse($this->drupalUserIsLoggedIn($second_user));

    // After logging back in, the second user should still be logged out.
    $this->drupalLogin($first_user);
    $this->assertTrue($this->drupalUserIsLoggedIn($first_user));
    $this->assertFalse($this->drupalUserIsLoggedIn($second_user));

    // After logging in the second user, the first one should be logged out.
    $this->drupalLogin($second_user);
    $this->assertTrue($this->drupalUserIsLoggedIn($second_user));
    $this->assertFalse($this->drupalUserIsLoggedIn($first_user));

    // After logging out, both should be logged out.
    $this->drupalLogout();
    $this->assertFalse($this->drupalUserIsLoggedIn($first_user));
    $this->assertFalse($this->drupalUserIsLoggedIn($second_user));
  }

}
