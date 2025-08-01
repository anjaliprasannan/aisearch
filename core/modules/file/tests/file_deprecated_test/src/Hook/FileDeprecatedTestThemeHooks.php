<?php

declare(strict_types=1);

namespace Drupal\file_deprecated_test\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for file_deprecated_test.
 */
class FileDeprecatedTestThemeHooks {
  // cspell:ignore garply tarz

  /**
   * Implements hook_file_mimetype_mapping_alter().
   *
   * @deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Kept only for BC test coverage, see \Drupal\KernelTests\Core\File\MimeTypeLegacyTest.
   *
   * @see https://www.drupal.org/node/3494040
   */
  #[Hook('file_mimetype_mapping_alter')]
  public function fileMimetypeMappingAlter(&$mapping): void {
    // Add new mappings.
    $mapping['mimetypes']['file_test_mimetype_1'] = 'made_up/file_test_1';
    $mapping['mimetypes']['file_test_mimetype_2'] = 'made_up/file_test_2';
    $mapping['mimetypes']['file_test_mimetype_3'] = 'made_up/doc';
    $mapping['mimetypes']['application-x-compress'] = 'application/x-compress';
    $mapping['mimetypes']['application-x-tarz'] = 'application/x-tarz';
    $mapping['mimetypes']['application-x-garply-waldo'] = 'application/x-garply-waldo';
    $mapping['extensions']['file_test_1'] = 'file_test_mimetype_1';
    $mapping['extensions']['file_test_2'] = 'file_test_mimetype_2';
    $mapping['extensions']['file_test_3'] = 'file_test_mimetype_2';
    $mapping['extensions']['z'] = 'application-x-compress';
    $mapping['extensions']['tar.z'] = 'application-x-tarz';
    $mapping['extensions']['garply.waldo'] = 'application-x-garply-waldo';
    // Override existing mapping.
    $mapping['extensions']['doc'] = 'file_test_mimetype_3';
  }

}
