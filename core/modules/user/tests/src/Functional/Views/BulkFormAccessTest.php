<?php

declare(strict_types=1);

namespace Drupal\Tests\user\Functional\Views;

use Drupal\user\Entity\User;

/**
 * Tests if entity access is respected on a user bulk form.
 *
 * @group user
 * @see \Drupal\user\Plugin\views\field\UserBulkForm
 * @see \Drupal\user\Tests\Views\BulkFormTest
 */
class BulkFormAccessTest extends UserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['user_access_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['test_user_bulk_form'];

  /**
   * Tests if users that may not be edited, can not be edited in bulk.
   */
  public function testUserEditAccess(): void {
    // Create an authenticated user.
    $no_edit_user = $this->drupalCreateUser([], 'no_edit');
    // Ensure this account is not blocked.
    $this->assertFalse($no_edit_user->isBlocked(), 'The user is not blocked.');

    // Log in as user admin.
    $admin_user = $this->drupalCreateUser(['administer users']);
    $this->drupalLogin($admin_user);

    // Ensure that the account "no_edit" can not be edited.
    $this->drupalGet('user/' . $no_edit_user->id() . '/edit');
    $this->assertFalse($no_edit_user->access('update', $admin_user));
    $this->assertSession()->statusCodeEquals(403);

    // Test blocking the account "no_edit".
    $edit = [
      'user_bulk_form[' . ($no_edit_user->id() - 1) . ']' => TRUE,
      'action' => 'user_block_user_action',
    ];
    $this->drupalGet('test-user-bulk-form');
    $this->submitForm($edit, 'Apply to selected items');
    $this->assertSession()->statusCodeEquals(200);

    $this->assertSession()->pageTextContains("No access to execute Block the selected user(s) on the User {$no_edit_user->label()}.");

    // Re-load the account "no_edit" and ensure it is not blocked.
    $no_edit_user = User::load($no_edit_user->id());
    $this->assertFalse($no_edit_user->isBlocked(), 'The user is not blocked.');

    // Create a normal user which can be edited by the admin user.
    $normal_user = $this->drupalCreateUser();
    $this->assertTrue($normal_user->access('update', $admin_user));

    $edit = [
      'user_bulk_form[' . ($normal_user->id() - 1) . ']' => TRUE,
      'action' => 'user_block_user_action',
    ];
    $this->drupalGet('test-user-bulk-form');
    $this->submitForm($edit, 'Apply to selected items');

    $normal_user = User::load($normal_user->id());
    $this->assertTrue($normal_user->isBlocked(), 'The user is blocked.');

    // Log in as user without the 'administer users' permission.
    $this->drupalLogin($this->drupalCreateUser());

    $edit = [
      'user_bulk_form[' . ($normal_user->id() - 1) . ']' => TRUE,
      'action' => 'user_unblock_user_action',
    ];
    $this->drupalGet('test-user-bulk-form');
    $this->submitForm($edit, 'Apply to selected items');

    // Re-load the normal user and ensure it is still blocked.
    $normal_user = User::load($normal_user->id());
    $this->assertTrue($normal_user->isBlocked(), 'The user is still blocked.');
  }

  /**
   * Tests if users that may not be deleted, can not be deleted in bulk.
   */
  public function testUserDeleteAccess(): void {
    // Create two authenticated users.
    $account = $this->drupalCreateUser([], 'no_delete');
    $account2 = $this->drupalCreateUser([], 'may_delete');

    // Log in as user admin.
    $this->drupalLogin($this->drupalCreateUser(['administer users']));

    // Ensure that the account "no_delete" can not be deleted.
    $this->drupalGet('user/' . $account->id() . '/cancel');
    $this->assertSession()->statusCodeEquals(403);
    // Ensure that the account "may_delete" *can* be deleted.
    $this->drupalGet('user/' . $account2->id() . '/cancel');
    $this->assertSession()->statusCodeEquals(200);

    // Test deleting the accounts "no_delete" and "may_delete".
    $edit = [
      'user_bulk_form[' . ($account->id() - 1) . ']' => TRUE,
      'user_bulk_form[' . ($account2->id() - 1) . ']' => TRUE,
      'action' => 'user_cancel_user_action',
    ];
    $this->drupalGet('test-user-bulk-form');
    $this->submitForm($edit, 'Apply to selected items');
    $edit = [
      'user_cancel_method' => 'user_cancel_delete',
    ];
    $this->submitForm($edit, 'Confirm');

    // Ensure the account "no_delete" still exists.
    $account = User::load($account->id());
    $this->assertNotNull($account, 'The user "no_delete" is not deleted.');
    // Ensure the account "may_delete" no longer exists.
    $account = User::load($account2->id());
    $this->assertNull($account, 'The user "may_delete" is deleted.');
  }

}
