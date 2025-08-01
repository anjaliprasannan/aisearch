<?php

declare(strict_types=1);

namespace Drupal\Tests\Component\Utility;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Render\MarkupTrait;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Random;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

// cspell:ignore répét répété
/**
 * Tests \Drupal\Component\Utility\Html.
 */
#[CoversClass(Html::class)]
#[Group('Common')]
class HtmlTest extends TestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $property = new \ReflectionProperty('Drupal\Component\Utility\Html', 'seenIdsInit');
    $property->setValue(NULL, NULL);
  }

  /**
   * Tests the Html::cleanCssIdentifier() method.
   *
   * @param string $expected
   *   The expected result.
   * @param string $source
   *   The string being transformed to an ID.
   * @param array|null $filter
   *   (optional) An array of string replacements to use on the identifier. If
   *   NULL, no filter will be passed and a default will be used.
   *
   * @legacy-covers ::cleanCssIdentifier
   */
  #[DataProvider('providerTestCleanCssIdentifier')]
  public function testCleanCssIdentifier($expected, $source, $filter = NULL): void {
    if ($filter !== NULL) {
      $this->assertSame($expected, Html::cleanCssIdentifier($source, $filter));
    }
    else {
      $this->assertSame($expected, Html::cleanCssIdentifier($source));
    }
  }

  /**
   * Provides test data for testCleanCssIdentifier().
   *
   * @return array
   *   Test data.
   */
  public static function providerTestCleanCssIdentifier() {
    $id1 = 'abcdefghijklmnopqrstuvwxyz_ABCDEFGHIJKLMNOPQRSTUVWXYZ-0123456789';
    $id2 = '¡¢£¤¥';
    $id3 = 'css__identifier__with__double__underscores';
    $id4 = "\x80\x81";
    return [
      // Verify that no valid ASCII characters are stripped from the identifier.
      [$id1, $id1, []],
      // Verify that valid UTF-8 characters are not stripped from the
      // identifier.
      [$id2, $id2, []],
      // Verify that double underscores are not stripped from the identifier.
      [$id3, $id3],
      // Confirm that NULL identifier does not trigger PHP 8.1 deprecation message.
      ['', $id4],
      // Verify that invalid characters (including non-breaking space) are
      // stripped from the identifier.
      ['invalid_identifier', 'invalid_ !"#$%&\'()*+,./:;<=>?@[\\]^`{|}~ identifier', []],
      // Verify that an identifier starting with a digit is replaced.
      ['_css_identifier', '1css_identifier', []],
      // Verify that an identifier starting with a hyphen followed by a digit is
      // replaced.
      ['__css_identifier', '-1css_identifier', []],
      // Verify that an identifier starting with two hyphens is replaced.
      ['__css_identifier', '--css_identifier', []],
      // Verify that passing double underscores as a filter is processed.
      ['_css_identifier', '__css_identifier', ['__' => '_']],
    ];
  }

  /**
   * Tests that Html::getClass() cleans the class name properly.
   *
   * @legacy-covers ::getClass
   */
  public function testHtmlClass(): void {
    // Verify Drupal coding standards are enforced.
    $this->assertSame('class-name--ü', Html::getClass('CLASS NAME_[Ü]'), 'Enforce Drupal coding standards.');

    // Test Html::getClass() handles Drupal\Component\Render\MarkupInterface
    // input.
    $markup = HtmlTestMarkup::create('CLASS_FROM_OBJECT');
    $this->assertSame('class-from-object', Html::getClass($markup), 'Markup object is converted to CSS class.');
  }

  /**
   * Tests the Html::getUniqueId() method.
   *
   * @param string $expected
   *   The expected result.
   * @param string $source
   *   The string being transformed to an ID.
   * @param bool $reset
   *   (optional) If TRUE, reset the list of seen IDs. Defaults to FALSE.
   *
   * @legacy-covers ::getUniqueId
   */
  #[DataProvider('providerTestHtmlGetUniqueId')]
  public function testHtmlGetUniqueId($expected, $source, $reset = FALSE): void {
    if ($reset) {
      Html::resetSeenIds();
    }
    $this->assertSame($expected, Html::getUniqueId($source));
  }

  /**
   * Provides test data for testHtmlGetId().
   *
   * @return array
   *   Test data.
   */
  public static function providerTestHtmlGetUniqueId() {
    // cSpell:disable
    $id = 'abcdefghijklmnopqrstuvwxyz-0123456789';
    return [
      // Verify that letters, digits, and hyphens are not stripped from the ID.
      [$id, $id],
      // Verify that invalid characters are stripped from the ID.
      ['invalididentifier', 'invalid,./:@\\^`{Üidentifier'],
      // Verify Drupal coding standards are enforced.
      ['id-name-1', 'ID NAME_[1]'],
      // Verify that a repeated ID is made unique.
      ['test-unique-id', 'test-unique-id', TRUE],
      ['test-unique-id--2', 'test-unique-id'],
      ['test-unique-id--3', 'test-unique-id'],
    ];
    // cSpell:enable
  }

  /**
   * Tests the Html::getUniqueId() method.
   *
   * @param string $expected
   *   The expected result.
   * @param string $source
   *   The string being transformed to an ID.
   *
   * @legacy-covers ::getUniqueId
   */
  #[DataProvider('providerTestHtmlGetUniqueIdWithAjaxIds')]
  public function testHtmlGetUniqueIdWithAjaxIds($expected, $source): void {
    Html::setIsAjax(TRUE);
    $id = Html::getUniqueId($source);

    // Note, we truncate two hyphens at the end.
    // @see \Drupal\Component\Utility\Html::getId()
    if (str_contains($source, '--')) {
      $random_suffix = substr($id, strlen($source) + 1);
    }
    else {
      $random_suffix = substr($id, strlen($source) + 2);
    }
    $expected = $expected . $random_suffix;
    $this->assertSame($expected, $id);
  }

  /**
   * Provides test data for testHtmlGetId().
   *
   * @return array
   *   Test data.
   */
  public static function providerTestHtmlGetUniqueIdWithAjaxIds() {
    return [
      ['test-unique-id1--', 'test-unique-id1'],
      // Note, we truncate two hyphens at the end.
      // @see \Drupal\Component\Utility\Html::getId()
      ['test-unique-id1---', 'test-unique-id1--'],
      ['test-unique-id2--', 'test-unique-id2'],
    ];
  }

  /**
   * Tests the Html::getUniqueId() method.
   *
   * @param string $expected
   *   The expected result.
   * @param string $source
   *   The string being transformed to an ID.
   *
   * @legacy-covers ::getId
   */
  #[DataProvider('providerTestHtmlGetId')]
  public function testHtmlGetId($expected, $source): void {
    Html::setIsAjax(FALSE);
    $this->assertSame($expected, Html::getId($source));
  }

  /**
   * Provides test data for testHtmlGetId().
   *
   * @return array
   *   Test data.
   */
  public static function providerTestHtmlGetId() {
    // cSpell:disable
    $id = 'abcdefghijklmnopqrstuvwxyz-0123456789';
    return [
      // Verify that letters, digits, and hyphens are not stripped from the ID.
      [$id, $id],
      // Verify that invalid characters are stripped from the ID.
      ['invalididentifier', 'invalid,./:@\\^`{Üidentifier'],
      // Verify Drupal coding standards are enforced.
      ['id-name-1', 'ID NAME_[1]'],
      // Verify that a repeated ID is made unique.
      ['test-unique-id', 'test-unique-id'],
      ['test-unique-id', 'test-unique-id'],
    ];
    // cSpell:enable
  }

  /**
   * Tests Html::decodeEntities().
   *
   * @legacy-covers ::decodeEntities
   */
  #[DataProvider('providerDecodeEntities')]
  public function testDecodeEntities($text, $expected): void {
    $this->assertEquals($expected, Html::decodeEntities($text));
  }

  /**
   * Data provider for testDecodeEntities().
   *
   * @see testDecodeEntities()
   */
  public static function providerDecodeEntities() {
    return [
      ['Drupal', 'Drupal'],
      ['<script>', '<script>'],
      ['&lt;script&gt;', '<script>'],
      ['&#60;script&#62;', '<script>'],
      ['&amp;lt;script&amp;gt;', '&lt;script&gt;'],
      ['"', '"'],
      ['&#34;', '"'],
      ['&amp;#34;', '&#34;'],
      ['&quot;', '"'],
      ['&amp;quot;', '&quot;'],
      ["'", "'"],
      ['&#39;', "'"],
      ['&amp;#39;', '&#39;'],
      ['©', '©'],
      ['&copy;', '©'],
      ['&#169;', '©'],
      ['→', '→'],
      ['&#8594;', '→'],
      ['➼', '➼'],
      ['&#10172;', '➼'],
      ['&euro;', '€'],
    ];
  }

  /**
   * Tests Html::escape().
   *
   * @legacy-covers ::escape
   */
  #[DataProvider('providerEscape')]
  public function testEscape($expected, $text): void {
    $this->assertEquals($expected, Html::escape($text));
  }

  /**
   * Data provider for testEscape().
   *
   * @see testEscape()
   */
  public static function providerEscape() {
    return [
      ['Drupal', 'Drupal'],
      ['&lt;script&gt;', '<script>'],
      ['&amp;lt;script&amp;gt;', '&lt;script&gt;'],
      ['&amp;#34;', '&#34;'],
      ['&quot;', '"'],
      ['&amp;quot;', '&quot;'],
      ['&#039;', "'"],
      ['&amp;#039;', '&#039;'],
      ['©', '©'],
      ['→', '→'],
      ['➼', '➼'],
      ['€', '€'],
      // cspell:disable-next-line
      ['Drup�al', "Drup\x80al"],
    ];
  }

  /**
   * Tests relationship between escaping and decoding HTML entities.
   *
   * @legacy-covers ::decodeEntities
   * @legacy-covers ::escape
   */
  public function testDecodeEntitiesAndEscape(): void {
    $string = "<em>répét&eacute;</em>";
    $escaped = Html::escape($string);
    $this->assertSame('&lt;em&gt;répét&amp;eacute;&lt;/em&gt;', $escaped);
    $decoded = Html::decodeEntities($escaped);
    $this->assertSame('<em>répét&eacute;</em>', $decoded);
    $decoded = Html::decodeEntities($decoded);
    $this->assertSame('<em>répété</em>', $decoded);
    $escaped = Html::escape($decoded);
    $this->assertSame('&lt;em&gt;répété&lt;/em&gt;', $escaped);
  }

  /**
   * Tests Html::serialize().
   *
   * Resolves an issue by where an empty DOMDocument object sent to
   * serialization would cause errors in getElementsByTagName() in the
   * serialization function.
   *
   * @legacy-covers ::serialize
   */
  public function testSerialize(): void {
    $document = new \DOMDocument();
    $result = Html::serialize($document);
    $this->assertSame('', $result);
  }

  /**
   * @legacy-covers ::transformRootRelativeUrlsToAbsolute
   */
  #[DataProvider('providerTestTransformRootRelativeUrlsToAbsolute')]
  public function testTransformRootRelativeUrlsToAbsolute($html, $scheme_and_host, $expected_html): void {
    $this->assertSame($expected_html ?: $html, Html::transformRootRelativeUrlsToAbsolute($html, $scheme_and_host));
  }

  /**
   * @legacy-covers ::transformRootRelativeUrlsToAbsolute
   */
  #[DataProvider('providerTestTransformRootRelativeUrlsToAbsoluteAssertion')]
  public function testTransformRootRelativeUrlsToAbsoluteAssertion($scheme_and_host): void {
    $this->expectException(\AssertionError::class);
    Html::transformRootRelativeUrlsToAbsolute('', $scheme_and_host);
  }

  /**
   * Provides test data for testTransformRootRelativeUrlsToAbsolute().
   *
   * @return array
   *   Test data.
   */
  public static function providerTestTransformRootRelativeUrlsToAbsolute() {
    $data = [];

    // Random generator.
    $random = new Random();

    // One random tag name.
    $tag_name = strtolower($random->name(8, TRUE));

    // A site installed either in the root of a domain or a subdirectory.
    $base_paths = ['/', '/subdir/' . $random->name(8, TRUE) . '/'];

    foreach ($base_paths as $base_path) {
      // The only attribute that has more than just a URL as its value, is
      // 'srcset', so special-case it.
      $data += [
        "$tag_name, srcset, $base_path: root-relative" => ["<$tag_name srcset=\"http://example.com{$base_path}already-absolute 200w, {$base_path}root-relative 300w\">root-relative test</$tag_name>", 'http://example.com', "<$tag_name srcset=\"http://example.com{$base_path}already-absolute 200w, http://example.com{$base_path}root-relative 300w\">root-relative test</$tag_name>"],
        "$tag_name, srcset, $base_path: protocol-relative" => ["<$tag_name srcset=\"http://example.com{$base_path}already-absolute 200w, //example.com{$base_path}protocol-relative 300w\">protocol-relative test</$tag_name>", 'http://example.com', FALSE],
        "$tag_name, srcset, $base_path: absolute" => ["<$tag_name srcset=\"http://example.com{$base_path}already-absolute 200w, http://example.com{$base_path}absolute 300w\">absolute test</$tag_name>", 'http://example.com', FALSE],
        "$tag_name, empty srcset" => ["<$tag_name srcset>empty test</$tag_name>", 'http://example.com', FALSE],
      ];

      foreach (['href', 'poster', 'src', 'cite', 'data', 'action', 'formaction', 'about'] as $attribute) {
        $data += [
          "$tag_name, $attribute, $base_path: root-relative" => ["<$tag_name $attribute=\"{$base_path}root-relative\">root-relative test</$tag_name>", 'http://example.com', "<$tag_name $attribute=\"http://example.com{$base_path}root-relative\">root-relative test</$tag_name>"],
          "$tag_name, $attribute, $base_path: protocol-relative" => ["<$tag_name $attribute=\"//example.com{$base_path}protocol-relative\">protocol-relative test</$tag_name>", 'http://example.com', FALSE],
          "$tag_name, $attribute, $base_path: absolute" => ["<$tag_name $attribute=\"http://example.com{$base_path}absolute\">absolute test</$tag_name>", 'http://example.com', FALSE],
        ];
      }
    }

    // Double-character carriage return should be normalized.
    $data['line break with double special character'] = ["Test without links but with\r\nsome special characters", 'http://example.com', "Test without links but with\nsome special characters"];
    $data['line break with single special character'] = ["Test without links but with&#13;\nsome special characters", 'http://example.com', "Test without links but with\nsome special characters"];
    $data['carriage return within html'] = ["<a\rhref='/node'>My link</a>", 'http://example.com', '<a href="http://example.com/node">My link</a>'];

    return $data;
  }

  /**
   * Provides test data for testTransformRootRelativeUrlsToAbsoluteAssertion().
   *
   * @return array
   *   Test data.
   */
  public static function providerTestTransformRootRelativeUrlsToAbsoluteAssertion() {
    return [
      'only relative path' => ['llama'],
      'only root-relative path' => ['/llama'],
      'host and path' => ['example.com/llama'],
      'scheme, host and path' => ['http://example.com/llama'],
    ];
  }

}

/**
 * Marks an object's __toString() method as returning markup.
 */
class HtmlTestMarkup implements MarkupInterface {
  use MarkupTrait;

}
