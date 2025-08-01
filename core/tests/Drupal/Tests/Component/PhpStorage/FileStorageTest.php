<?php

declare(strict_types=1);

namespace Drupal\Tests\Component\PhpStorage;

use Drupal\Component\PhpStorage\FileStorage;
use Drupal\Component\Utility\Random;
use Drupal\TestTools\Extension\DeprecationBridge\ExpectDeprecationTrait;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Drupal\Component\PhpStorage\FileStorage.
 */
#[CoversClass(FileStorage::class)]
#[Group('Drupal')]
#[Group('PhpStorage')]
class FileStorageTest extends PhpStorageTestBase {

  use ExpectDeprecationTrait;

  /**
   * Standard test settings to pass to storage instances.
   *
   * @var array
   */
  protected $standardSettings;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->standardSettings = [
      'directory' => $this->directory,
      'bin' => 'test',
    ];
  }

  /**
   * Tests basic load/save/delete operations.
   *
   * @legacy-covers ::load
   * @legacy-covers ::save
   * @legacy-covers ::exists
   * @legacy-covers ::delete
   */
  public function testCRUD(): void {
    $php = new FileStorage($this->standardSettings);
    $this->assertCRUD($php);
  }

  /**
   * @legacy-covers ::deleteAll
   */
  public function testDeleteAll(): void {
    // Random generator.
    $random_generator = new Random();

    // Write out some files.
    $php = new FileStorage($this->standardSettings);

    $name = $random_generator->name(8, TRUE) . '/' . $random_generator->name(8, TRUE) . '.php';

    // Find a global that doesn't exist.
    do {
      $random = 'test' . mt_rand(10000, 100000);
    } while (isset($GLOBALS[$random]));

    // Write out a PHP file and ensure it's successfully loaded.
    $code = "<?php\n\$GLOBALS['$random'] = TRUE;";
    $this->assertTrue($php->save($name, $code), 'Saved php file');
    $php->load($name);
    $this->assertTrue($GLOBALS[$random], 'File saved correctly with correct value');

    // Make sure directory exists prior to removal.
    $this->assertDirectoryExists($this->directory . '/test');

    $this->assertTrue($php->deleteAll(), 'Delete all reported success');
    $this->assertFalse($php->load($name));
    $this->assertDirectoryDoesNotExist($this->directory . '/test');

    // Should still return TRUE if directory has already been deleted.
    $this->assertTrue($php->deleteAll(), 'Delete all succeeds with nothing to delete');
    unset($GLOBALS[$random]);
  }

  /**
   * @legacy-covers ::createDirectory
   */
  public function testCreateDirectoryFailWarning(): void {
    $directory = new vfsStreamDirectory('permissionDenied', 0200);
    $storage = new FileStorage([
      'directory' => $directory->url(),
      'bin' => 'test',
    ]);
    $code = "<?php\n echo 'here';";

    // PHPUnit 10 cannot expect warnings, so we have to catch them ourselves.
    $messages = [];
    set_error_handler(function (int $errno, string $errstr) use (&$messages): void {
      $messages[] = [$errno, $errstr];
    });

    $storage->save('subdirectory/foo.php', $code);

    restore_error_handler();
    $this->assertCount(2, $messages);
    $this->assertSame(E_USER_WARNING, $messages[0][0]);
    $this->assertSame('mkdir(): Permission Denied', $messages[0][1]);
    $this->assertSame(E_WARNING, $messages[1][0]);
    $this->assertStringStartsWith('file_put_contents(vfs://permissionDenied/test/subdirectory/foo.php)', $messages[1][1]);
  }

}
