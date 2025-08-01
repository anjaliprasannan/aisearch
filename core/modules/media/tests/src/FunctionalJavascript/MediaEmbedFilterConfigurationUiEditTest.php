<?php

declare(strict_types=1);

namespace Drupal\Tests\media\FunctionalJavascript;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Media Embed Filter Configuration Ui Edit.
 *
 * @legacy-covers ::media_filter_format_edit_form_validate
 */
#[Group('media')]
#[Group('#slow')]
class MediaEmbedFilterConfigurationUiEditTest extends MediaEmbedFilterTestBase {

  /**
   * Tests validation when editing.
   *
   * @legacy-covers \Drupal\media\Hook\MediaHooks::formFilterFormatEditFormAlter
   */
  #[DataProvider('providerTestValidations')]
  public function testValidationWhenEditing($filter_html_status, $filter_align_status, $filter_caption_status, $filter_html_image_secure_status, $media_embed, $allowed_html, $expected_error_message): void {
    $this->drupalGet('admin/config/content/formats/manage/media_embed_test');

    // Enable the `filter_html` and `media_embed` filters.
    $page = $this->getSession()->getPage();
    if ($filter_html_status) {
      $page->checkField('filters[filter_html][status]');
    }
    if ($filter_align_status) {
      $page->checkField('filters[filter_align][status]');
    }
    if ($filter_caption_status) {
      $page->checkField('filters[filter_caption][status]');
    }
    if ($filter_html_image_secure_status) {
      $page->checkField('filters[filter_html_image_secure][status]');
    }
    if ($media_embed === TRUE || is_numeric($media_embed)) {
      $page->checkField('filters[media_embed][status]');
      // Set a non-default weight.
      if (is_numeric($media_embed)) {
        $this->click('.tabledrag-toggle-weight');
        $page->selectFieldOption('filters[media_embed][weight]', $media_embed);
      }
    }
    if (!empty($allowed_html)) {
      $page->clickLink('Limit allowed HTML tags and correct faulty HTML');
      $page->fillField('filters[filter_html][settings][allowed_html]', $allowed_html);
    }
    $page->pressButton('Save configuration');

    if ($expected_error_message) {
      $this->assertSession()->pageTextNotContains('The text format Test format has been updated.');
      $this->assertSession()->pageTextContains($expected_error_message);
    }
    else {
      $this->assertSession()->pageTextContains('The text format Test format has been updated.');
    }
  }

}
