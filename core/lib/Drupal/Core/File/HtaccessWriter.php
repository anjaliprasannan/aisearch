<?php

namespace Drupal\Core\File;

use Drupal\Component\FileSecurity\FileSecurity;
use Drupal\Core\Site\Settings;
use Drupal\Core\StreamWrapper\PrivateStream;
use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Provides functions to manage Apache .htaccess files.
 */
class HtaccessWriter implements HtaccessWriterInterface {

  /**
   * Htaccess constructor.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $streamWrapperManager
   *   The stream wrapper manager.
   * @param \Drupal\Core\Site\Settings|null $settings
   *   The settings.
   */
  public function __construct(
    protected LoggerInterface $logger,
    protected StreamWrapperManagerInterface $streamWrapperManager,
    protected ?Settings $settings = NULL,
  ) {
    if (!$settings) {
      @trigger_error('Calling ' . __METHOD__ . '() without the $settings argument is deprecated in drupal:11.2.0 and will be required in drupal:12.0.0. See https://www.drupal.org/node/3249817', E_USER_DEPRECATED);
      $this->settings = \Drupal::service('settings');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function ensure() {
    if (!$this->settings->get('auto_create_htaccess', TRUE)) {
      return;
    }
    try {
      foreach ($this->defaultProtectedDirs() as $protected_dir) {
        $this->write($protected_dir->getPath(), $protected_dir->isPrivate());
      }

      $staging = Settings::get('config_sync_directory', FALSE);
      if ($staging) {
        // Note that we log an error here if we can't write the .htaccess file.
        // This can occur if the staging directory is read-only. If it is then
        // it is the user's responsibility to create the .htaccess file.
        $this->write($staging, TRUE);
      }
    }
    catch (\Exception $e) {
      $this->logger->error($e->getMessage());
    }
  }

  /**
   * Creates a .htaccess file in the given directory.
   *
   * @param string $directory
   *   The directory.
   * @param bool $deny_public_access
   *   (Optional) FALSE indicates that $directory should be a web-accessible
   *   directory. Defaults to TRUE which indicates a private directory.
   * @param bool $force_overwrite
   *   (Optional) Set to TRUE to attempt to overwrite the existing .htaccess
   *   file if one is already present. Defaults to FALSE.
   *
   * @internal
   *
   * @return bool
   *   TRUE if the .htaccess file was saved, already exists or auto-creation is
   *   disabled, FALSE otherwise.
   *
   * @see \Drupal\Component\FileSecurity\FileSecurity::writeHtaccess()
   */
  public function write($directory, $deny_public_access = TRUE, $force_overwrite = FALSE) {
    if (!$this->settings->get('auto_create_htaccess', TRUE)) {
      return TRUE;
    }
    if (StreamWrapperManager::getScheme($directory)) {
      $directory = $this->streamWrapperManager->normalizeUri($directory);
    }
    else {
      $directory = rtrim($directory, '/\\');
    }

    if (FileSecurity::writeHtaccess($directory, $deny_public_access, $force_overwrite)) {
      return TRUE;
    }

    $this->logger->error("Security warning: Couldn't write .htaccess file. Create a .htaccess file in your %directory directory which contains the following lines: <pre><code>@htaccess</code></pre>", [
      '%directory' => $directory,
      '@htaccess' => FileSecurity::htaccessLines($deny_public_access),
    ]);
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultProtectedDirs() {
    $protected_dirs[] = new ProtectedDirectory('Public files directory', 'public://');
    if (PrivateStream::basePath()) {
      $protected_dirs[] = new ProtectedDirectory('Private files directory', 'private://', TRUE);
    }
    $protected_dirs[] = new ProtectedDirectory('Temporary files directory', 'temporary://');

    // The assets path may be the same as the public file path, if so don't try
    // to write the same .htaccess twice.
    $public_path = Settings::get('file_public_path', 'sites/default/files');
    $assets_path = Settings::get('file_assets_path', $public_path);
    if ($assets_path !== $public_path) {
      $protected_dirs[] = new ProtectedDirectory('Optimized assets directory', $assets_path);
    }
    return $protected_dirs;
  }

}
