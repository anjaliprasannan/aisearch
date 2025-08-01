<?php

/**
 * @file
 */

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Updater\UpdaterException;
use Drupal\Core\Url;

/**
 * Updates existing projects when invoked by authorize.php.
 *
 * Callback for system_authorized_init() in
 * update_manager_update_ready_form_submit().
 *
 * @param \Drupal\Core\FileTransfer\FileTransfer $filetransfer
 *   The FileTransfer object created by authorize.php for use during this
 *   operation.
 * @param array $projects
 *   A nested array of projects to install into the live webroot, keyed by
 *   project name. Each subarray contains the following keys:
 *   - project: The canonical project short name.
 *   - updater_name: The name of the Drupal\Core\Updater\Updater class to use
 *     for this project.
 *   - local_url: The locally installed location of new code to update with.
 *
 * @return \Symfony\Component\HttpFoundation\Response|null
 *   The result of processing the batch that updates the projects. If this is
 *   an instance of \Symfony\Component\HttpFoundation\Response the calling code
 *   should use that response for the current page request.
 */
function update_authorize_run_update($filetransfer, $projects) {
  @trigger_error(__FUNCTION__ . '() is deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. There is no replacement. Use composer to manage the code for your site. See https://www.drupal.org/node/3512364', E_USER_DEPRECATED);

  $batch_builder = (new BatchBuilder())
    ->setFile(\Drupal::service('extension.list.module')->getPath('update') . '/update.authorize.inc')
    ->setInitMessage(t('Preparing to update your site'))
    ->setFinishCallback('update_authorize_update_batch_finished');

  foreach ($projects as $project_info) {
    $batch_builder->addOperation('update_authorize_batch_copy_project', [
      $project_info['project'],
      $project_info['updater_name'],
      $project_info['local_url'],
      $filetransfer,
    ]);
  }

  batch_set($batch_builder->toArray());

  // Since authorize.php has its own method for setting the page title, set it
  // manually here rather than passing it in to batch_set() as would normally
  // be done.
  \Drupal::request()->getSession()->set('authorize_page_title', t('Downloading updates'));

  // Invoke the batch via authorize.php.
  return system_authorized_batch_process();
}

/**
 * Implements callback_batch_operation().
 *
 * Copies project to its proper place when authorized to do so.
 *
 * @param string $project
 *   The canonical short name of the project being installed.
 * @param string $updater_name
 *   The name of the Drupal\Core\Updater\Updater class to use for installing
 *   this project.
 * @param string $local_url
 *   The URL to the locally installed temp directory where the project has
 *   already been downloaded and extracted into.
 * @param \Drupal\Core\FileTransfer\FileTransfer $filetransfer
 *   The FileTransfer object to use for performing this operation.
 * @param array $context
 *   Reference to an array used for Batch API storage.
 */
function update_authorize_batch_copy_project($project, $updater_name, $local_url, $filetransfer, &$context): void {
  @trigger_error(__FUNCTION__ . '() is deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. There is no replacement. Use composer to manage the code for your site. See https://www.drupal.org/node/3512364', E_USER_DEPRECATED);

  // Initialize some variables in the Batch API $context array.
  if (!isset($context['results']['log'])) {
    $context['results']['log'] = [];
  }
  if (!isset($context['results']['log'][$project])) {
    $context['results']['log'][$project] = [];
  }

  if (!isset($context['results']['tasks'])) {
    $context['results']['tasks'] = [];
  }

  // The batch API uses a session, and since all the arguments are serialized
  // and unserialized between requests, although the FileTransfer object itself
  // will be reconstructed, the connection pointer itself will be lost. However,
  // the FileTransfer object will still have the connection variable, even
  // though the connection itself is now gone. So, although it's ugly, we have
  // to unset the connection variable at this point so that the FileTransfer
  // object will re-initiate the actual connection.
  // @phpstan-ignore property.deprecatedClass
  unset($filetransfer->connection);

  if (!empty($context['results']['log'][$project]['#abort'])) {
    $context['finished'] = 1;
    return;
  }

  // @phpstan-ignore getDeprecatedService.deprecated
  $updater = new $updater_name($local_url, \Drupal::getContainer()->get('update.root'));

  try {
    if ($updater->isInstalled()) {
      // This is an update.
      $tasks = $updater->update($filetransfer);
    }
    else {
      $tasks = $updater->install($filetransfer);
    }
  }
  catch (UpdaterException $e) {
    _update_batch_create_message($context['results']['log'][$project], t('Error adding / updating'), FALSE);
    _update_batch_create_message($context['results']['log'][$project], $e->getMessage(), FALSE);
    $context['results']['log'][$project]['#abort'] = TRUE;
    return;
  }

  _update_batch_create_message($context['results']['log'][$project], t('Added / updated %project_name successfully', ['%project_name' => $project]));

  if (!empty($tasks)) {
    $context['results']['tasks'] += $tasks;
  }

  // This particular operation is now complete, even though the batch might
  // have other operations to perform.
  $context['finished'] = 1;
}

/**
 * Batch callback: Performs actions when the authorized update batch is done.
 *
 * This processes the results and stashes them into SESSION such that
 * authorize.php will render a report. Also responsible for putting the site
 * back online and clearing the update status storage after a successful update.
 *
 * @param bool $success
 *   TRUE if the batch operation was successful; FALSE if there were errors.
 * @param array $results
 *   An associative array of results from the batch operation.
 */
function update_authorize_update_batch_finished($success, $results): void {
  @trigger_error(__FUNCTION__ . '() is deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. There is no replacement. Use composer to manage the code for your site. See https://www.drupal.org/node/3512364', E_USER_DEPRECATED);

  foreach ($results['log'] as $messages) {
    if (!empty($messages['#abort'])) {
      $success = FALSE;
    }
  }
  $offline = \Drupal::state()->get('system.maintenance_mode');
  $session = \Drupal::request()->getSession();
  // Unset the variable since it is no longer needed.
  $maintenance_mode = $session->remove('maintenance_mode');
  if ($success) {
    // Now that the update completed, we need to clear the available update data
    // and recompute our status, so prevent show bogus results.
    _update_authorize_clear_update_status();

    // Take the site out of maintenance mode if it was previously that way.
    if ($offline && $maintenance_mode === FALSE) {
      \Drupal::state()->set('system.maintenance_mode', FALSE);
      $page_message = [
        'message' => t('Update was completed successfully. Your site has been taken out of maintenance mode.'),
        'type' => 'status',
      ];
    }
    else {
      $page_message = [
        'message' => t('Update was completed successfully.'),
        'type' => 'status',
      ];
    }
  }
  elseif (!$offline) {
    $page_message = [
      'message' => t('Update failed! See the log below for more information.'),
      'type' => 'error',
    ];
  }
  else {
    $page_message = [
      'message' => t('Update failed! See the log below for more information. Your site is still in maintenance mode.'),
      'type' => 'error',
    ];
  }
  // Since we're doing an update of existing code, always add a task for
  // running update.php.
  $url = Url::fromRoute('system.db_update');
  $results['tasks'][] = t('Your modules have been downloaded and updated.');
  $results['tasks'][] = [
    '#type' => 'link',
    '#url' => $url,
    '#title' => t('Run database updates'),
    // Since this is being called outside of the primary front controller,
    // the base_url needs to be set explicitly to ensure that links are
    // relative to the site root.
    // @todo Simplify with https://www.drupal.org/node/2548095
    '#options' => [
      'absolute' => TRUE,
      'base_url' => $GLOBALS['base_url'],
    ],
    '#access' => $url->access(\Drupal::currentUser()),
  ];

  // Set all these values into the SESSION so authorize.php can display them.
  $session->set('authorize_results', [
    'success' => $success,
    'page_message' => $page_message,
    'messages' => $results['log'],
    'tasks' => $results['tasks'],
  ]);
  $session->set('authorize_page_title', t('Update manager'));
}

/**
 * Creates a structure of log messages.
 *
 * @param array $project_results
 *   An associative array of results from the batch operation.
 * @param string $message
 *   A string containing a log message.
 * @param bool $success
 *   (optional) TRUE if the operation the message is about was a success, FALSE
 *   if there were errors. Defaults to TRUE.
 */
function _update_batch_create_message(&$project_results, $message, $success = TRUE): void {
  @trigger_error(__FUNCTION__ . '() is deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. There is no replacement. Use composer to manage the code for your site. See https://www.drupal.org/node/3512364', E_USER_DEPRECATED);

  $project_results[] = ['message' => $message, 'success' => $success];
}

/**
 * Clears available update status data.
 *
 * Since this function is run at such a low bootstrap level, the Update Manager
 * module is not loaded. So, we can't just call update_storage_clear(). However,
 * the key-value backend is available, so we just call that.
 *
 * Note that we do not want to delete  items related to currently pending fetch
 * attempts.
 *
 * @see update_authorize_update_batch_finished()
 * @see update_storage_clear()
 */
function _update_authorize_clear_update_status(): void {
  @trigger_error(__FUNCTION__ . '() is deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. There is no replacement. Use composer to manage the code for your site. See https://www.drupal.org/node/3512364', E_USER_DEPRECATED);

  \Drupal::keyValueExpirable('update')->deleteAll();
  \Drupal::keyValueExpirable('update_available_release')->deleteAll();
}
