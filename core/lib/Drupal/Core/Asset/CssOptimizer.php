<?php

namespace Drupal\Core\Asset;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\Core\File\FileUrlGeneratorInterface;

/**
 * Optimizes a CSS asset.
 */
class CssOptimizer implements AssetOptimizerInterface {

  /**
   * The base path used by rewriteFileURI().
   *
   * @var string
   */
  public $rewriteFileURIBasePath;

  /**
   * The file URL generator.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected $fileUrlGenerator;

  /**
   * Constructs a CssOptimizer.
   *
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $file_url_generator
   *   The file URL generator.
   */
  public function __construct(FileUrlGeneratorInterface $file_url_generator) {
    $this->fileUrlGenerator = $file_url_generator;
  }

  /**
   * {@inheritdoc}
   */
  public function optimize(array $css_asset) {
    if ($css_asset['type'] != 'file') {
      throw new \Exception('Only file CSS assets can be optimized.');
    }
    if (!$css_asset['preprocess']) {
      throw new \Exception('Only file CSS assets with preprocessing enabled can be optimized.');
    }

    return $this->processFile($css_asset);
  }

  /**
   * Processes the contents of a CSS asset for cleanup.
   *
   * @param string $contents
   *   The contents of the CSS asset.
   *
   * @return string
   *   Contents of the CSS asset.
   */
  public function clean($contents) {
    // Remove multiple charset declarations for standards compliance (and fixing
    // Safari problems).
    $contents = preg_replace('/^@charset\s+[\'"](\S*?)\b[\'"];/i', '', $contents);

    return $contents;
  }

  /**
   * Processes CSS file and adds base URLs to any relative resource paths.
   *
   * @param array $css_asset
   *   A CSS asset. The array should contain the `data` key where the value
   *   should be the path to the CSS file relative to the Drupal root. This is
   *   an example of the `data` key's value,
   *   "core/assets/vendor/normalize-css/normalize.css".
   *
   * @return string
   *   The asset's cleaned/optimized contents.
   */
  protected function processFile($css_asset) {
    $contents = $this->loadFile($css_asset['data'], TRUE);
    if ($css_asset['media'] !== 'print' && $css_asset['media'] !== 'all') {
      $contents = '@media ' . $css_asset['media'] . '{' . $contents . '}' . "\n";
    }
    $contents = $this->clean($contents);

    // Get the parent directory of this file, relative to the Drupal root.
    $css_base_path = substr($css_asset['data'], 0, strrpos($css_asset['data'], '/'));
    // Store base path.
    $this->rewriteFileURIBasePath = $css_base_path . '/';

    // Anchor all paths in the CSS with its base URL, ignoring external and
    // absolute paths and paths starting with '#'.
    return preg_replace_callback(
      '/url\(\s*[\'"]?(?![a-z]+:|\/+|#|%23)([^\'")]+)[\'"]?\s*\)/i',
      [$this, 'rewriteFileURI'],
      $contents
    );
  }

  /**
   * Loads the stylesheet and resolves all @import commands.
   *
   * Loads a stylesheet and replaces @import commands with the contents of the
   * imported file. Use this instead of file_get_contents when processing
   * stylesheets.
   *
   * The returned contents are compressed removing white space and comments only
   * when CSS aggregation is enabled. This optimization will not apply for
   * color.module enabled themes with CSS aggregation turned off.
   *
   * Note: the only reason this method is public is so color.module can call it;
   * it is not on the AssetOptimizerInterface, so any future refactoring can
   * make it protected.
   *
   * @param string $file
   *   Name of the stylesheet to be processed.
   * @param bool|null $optimize
   *   Defines if CSS contents should be compressed or not.
   * @param bool $reset_base_path
   *   Used internally to facilitate recursive resolution of @import commands.
   *
   * @return string
   *   Contents of the stylesheet, including any resolved @import commands.
   */
  public function loadFile($file, $optimize = NULL, $reset_base_path = TRUE) {
    // These statics are not cache variables, so we don't use drupal_static().
    static $_optimize, $base_path;
    if ($reset_base_path) {
      $base_path = '';
    }
    // Store $optimize for preg_replace_callback with nested @import loops.
    if (isset($optimize)) {
      $_optimize = $optimize;
    }

    // Stylesheets are relative one to each other. Start by adding a base path
    // prefix provided by the parent stylesheet (if necessary).
    if ($base_path && !StreamWrapperManager::getScheme($file)) {
      $file = $base_path . '/' . $file;
    }
    // Store the parent base path to restore it later.
    $parent_base_path = $base_path;
    // Set the current base path to process possible child imports.
    $base_path = dirname($file);

    // Load the CSS stylesheet. We suppress errors because themes may specify
    // stylesheets in their .info.yml file that don't exist in the theme's path,
    // but are merely there to disable certain module CSS files.
    $content = '';
    if ($contents = @file_get_contents($file)) {
      // If a BOM is found, convert the file to UTF-8, then use substr() to
      // remove the BOM from the result.
      if ($encoding = (Unicode::encodingFromBOM($contents))) {
        $contents = mb_substr(Unicode::convertToUtf8($contents, $encoding), 1);
      }
      // If no BOM, check for fallback encoding. Per CSS spec the regex is very
      // strict.
      elseif (preg_match('/^@charset "([^"]+)";/', $contents, $matches)) {
        if ($matches[1] !== 'utf-8' && $matches[1] !== 'UTF-8') {
          $contents = substr($contents, strlen($matches[0]));
          $contents = Unicode::convertToUtf8($contents, $matches[1]);
        }
      }

      // Return the processed stylesheet.
      $content = $this->processCss($contents, $_optimize);
    }

    // Restore the parent base path as the file and its children are processed.
    $base_path = $parent_base_path;
    return $content;
  }

  /**
   * Loads stylesheets recursively and returns contents with corrected paths.
   *
   * This function is used for recursive loading of stylesheets and
   * returns the stylesheet content with all url() paths corrected.
   *
   * @param array $matches
   *   An array of matches by a preg_replace_callback() call that scans for
   *   @import-ed CSS files, except for external CSS files.
   *
   * @return string
   *   The contents of the CSS file at $matches[1], with corrected paths.
   *
   * @see \Drupal\Core\Asset\AssetOptimizerInterface::loadFile()
   */
  protected function loadNestedFile($matches) {
    $filename = $matches[1];
    // Load the imported stylesheet and replace @import commands in there as
    // well.
    $file = $this->loadFile($filename, NULL, FALSE);

    // Determine the file's directory.
    $directory = dirname($filename);
    // If the file is in the current directory, make sure '.' doesn't appear in
    // the url() path.
    $directory = $directory == '.' ? '' : $directory . '/';

    // Alter all internal asset paths. Leave external paths alone. We don't need
    // to normalize absolute paths here because that will be done later.
    return preg_replace('/url\(\s*([\'"]?)(?![a-z]+:|\/+)([^\'")]+)([\'"]?)\s*\)/i', 'url(\1' . $directory . '\2\3)', $file);
  }

  /**
   * Processes the contents of a stylesheet for aggregation.
   *
   * @param string $contents
   *   The contents of the stylesheet.
   * @param bool $optimize
   *   (optional) Boolean whether CSS contents should be minified. Defaults to
   *   FALSE.
   *
   * @return string
   *   Contents of the stylesheet including the imported stylesheets.
   */
  protected function processCss($contents, $optimize = FALSE) {
    // Remove unwanted CSS code that cause issues.
    $contents = $this->clean($contents);

    if ($optimize) {
      // Perform some safe CSS optimizations.
      // Regexp to match comment blocks.
      $comment = '/\*[^*]*\*+(?:[^/*][^*]*\*+)*/';
      // Regexp to match double quoted strings.
      $double_quot = '"[^"\\\\]*(?:\\\\.[^"\\\\]*)*"';
      // Regexp to match single quoted strings.
      $single_quot = "'[^'\\\\]*(?:\\\\.[^'\\\\]*)*'";
      // Strip all comment blocks, but keep double/single quoted strings.
      $contents = preg_replace(
        "<($double_quot|$single_quot)|$comment>Ss",
        "$1",
        $contents
      );
      // Remove certain whitespace.
      // There are different conditions for removing leading and trailing
      // whitespace.
      // @see http://php.net/manual/regexp.reference.subpatterns.php
      $contents = preg_replace('<
        # Do not strip any space from within single or double quotes
          (' . $double_quot . '|' . $single_quot . ')
        # Strip leading and trailing whitespace.
        | \s*([@{};,])\s*
        # Strip only leading whitespace from:
        # - Closing parenthesis: Retain "@media (bar) and foo".
        | \s+([\)])
        # Strip only trailing whitespace from:
        # - Opening parenthesis: Retain "@media (bar) and foo".
        # - Colon: Retain :pseudo-selectors.
        | ([\(:])\s+
      >xSs',
        // Only one of the four capturing groups will match, so its reference
        // will contain the wanted value and the references for the
        // two non-matching groups will be replaced with empty strings.
        '$1$2$3$4',
        $contents
      );
      // End the file with a new line.
      $contents = trim($contents);
      $contents .= "\n";
    }

    // Replaces @import commands with the actual stylesheet content.
    // This happens recursively but omits external files and local files
    // with supports- or media-query qualifiers, as those are conditionally
    // loaded depending on the user agent.
    $contents = preg_replace_callback(
      '/@import\s*(?:url\(\s*)?[\'"]?(?![a-z]+:)(?!\/\/)([^\'"\()]+)[\'"]?\s*\)?\s*;/', [
        $this,
        'loadNestedFile',
      ],
      $contents);

    return $contents;
  }

  /**
   * Prefixes all paths within a CSS file for processFile().
   *
   * Note: the only reason this method is public is so color.module can call it;
   * it is not on the AssetOptimizerInterface, so any future refactoring can
   * make it protected.
   *
   * @param array $matches
   *   An array of matches by a preg_replace_callback() call that scans for
   *   url() references in CSS files, except for external or absolute ones.
   *
   * @return string
   *   The file path.
   */
  public function rewriteFileURI($matches) {
    // Prefix with base and remove '../' segments where possible.
    $path = $this->rewriteFileURIBasePath . $matches[1];
    $last = '';
    while ($path != $last) {
      $last = $path;
      $path = preg_replace('`(^|/)(?!\.\./)([^/]+)/\.\./`', '$1', $path);
    }
    return 'url(' . $this->fileUrlGenerator->generateString($path) . ')';
  }

}
