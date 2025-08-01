<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\File;

use Drupal\Core\File\Exception\FileException;
use Drupal\Core\File\Exception\FileNotExistsException;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystem;
use Drupal\Core\Site\Settings;

/**
 * Tests the unmanaged file move function.
 *
 * @group File
 */
class FileMoveTest extends FileTestBase {

  /**
   * Move a normal file.
   */
  public function testNormal(): void {
    // Create a file for testing.
    $uri = $this->createUri();

    // Moving to a new name.
    $desired_filepath = 'public://' . $this->randomMachineName();
    /** @var \Drupal\Core\File\FileSystemInterface $file_system */
    $file_system = \Drupal::service('file_system');
    $new_filepath = $file_system->move($uri, $desired_filepath, FileExists::Error);
    $this->assertNotFalse($new_filepath, 'Move was successful.');
    $this->assertEquals($desired_filepath, $new_filepath, 'Returned expected filepath.');
    $this->assertFileExists($new_filepath);
    $this->assertFileDoesNotExist($uri);
    $this->assertFilePermissions($new_filepath, Settings::get('file_chmod_file', FileSystem::CHMOD_FILE));

    // Moving with rename.
    $desired_filepath = 'public://' . $this->randomMachineName();
    $this->assertFileExists($new_filepath);
    $this->assertNotFalse(file_put_contents($desired_filepath, ' '), 'Created a file so a rename will have to happen.');
    $newer_filepath = $file_system->move($new_filepath, $desired_filepath, FileExists::Rename);
    $this->assertNotFalse($newer_filepath, 'Move was successful.');
    $this->assertNotEquals($desired_filepath, $newer_filepath, 'Returned expected filepath.');
    $this->assertFileExists($newer_filepath);
    $this->assertFileDoesNotExist($new_filepath);
    $this->assertFilePermissions($newer_filepath, Settings::get('file_chmod_file', FileSystem::CHMOD_FILE));

    // @todo Test moving to a directory (rather than full directory/file path)
    // @todo Test creating and moving normal files (rather than streams)
  }

  /**
   * Try to move a missing file.
   */
  public function testMissing(): void {
    // Move non-existent file.
    $this->expectException(FileNotExistsException::class);
    \Drupal::service('file_system')->move($this->randomMachineName(), $this->randomMachineName());
  }

  /**
   * Try to move a file onto itself.
   */
  public function testOverwriteSelf(): void {
    // Create a file for testing.
    $uri = $this->createUri();

    // Move the file onto itself without renaming shouldn't make changes.
    /** @var \Drupal\Core\File\FileSystemInterface $file_system */
    $file_system = \Drupal::service('file_system');
    $this->expectException(FileException::class);
    $new_filepath = $file_system->move($uri, $uri, FileExists::Replace);
    $this->assertFalse($new_filepath, 'Moving onto itself without renaming fails.');
    $this->assertFileExists($uri);

    // Move the file onto itself with renaming will result in a new filename.
    $new_filepath = $file_system->move($uri, $uri, FileExists::Rename);
    $this->assertNotFalse($new_filepath, 'Moving onto itself with renaming works.');
    $this->assertFileDoesNotExist($uri);
    $this->assertFileExists($new_filepath);
  }

}
