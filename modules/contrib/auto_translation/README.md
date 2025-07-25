# Auto Translation Module for Drupal

## Introduction

Auto Translation is a Drupal module that automatically translates content between languages. It integrates with the Google Translate API, DeepL API, Drupal AI module, and LibreTranslate to provide high-quality translations across multiple languages.

## Features

- Automatic translation for all content types
- Real-time translation of Paragraphs and nested paragraphs when adding a node translation
- Bulk translation of nodes, with options to save as drafts or publish
- Support for translating Media, Block Content, and Taxonomy terms
- Choice between the free Google Translate browser API or the paid server-side API
- Integration with Drupal AI, Google Translate API, DeepL API, and LibreTranslate API
- Configurable translation settings
- Support for selecting an AI translation provider via the AI Translate module
- User-friendly interface

## How It Works

Auto Translation uses the Google Translate API, DeepL API, Drupal AI module, or LibreTranslate to translate content. The module provides a simple configuration interface where users can define language pairs, select a translation provider, and adjust other settings.

## Translation Quality

- The Google Translate API delivers high-quality translations in numerous languages, but machine translations may require manual review.
- LibreTranslate quality depends on the selected plan.
- Drupal AI Translate relies on the chosen provider's quality.
- DeepL API provides high-quality translations using advanced AI models.
- Manual review may be necessary to ensure translation accuracy and grammatical correctness.

## Installation

To install Auto Translation:

1. Download the module from Drupal.org or install it via Composer (preferred).
2. Upload the module to your Drupal site.
3. Enable the module through the Drupal administrative interface.

## Configuration

To configure Auto Translation:

1. Navigate to Extend > Modules in the Drupal admin panel.
2. Click on Configure next to the Auto Translation module.
3. Adjust the following settings:
  - Translation Provider: Select Google, LibreTranslate, or Drupal AI.
  - Content Types: Choose which content types should be auto-translated.
  - Bulk Translation: Select whether bulk-translated nodes should be published or saved as drafts.
  - Google API Type: Choose between server-side or client-side translation.
  - API Key: Enter your API key.
  - Drupal AI Module: Ensure at least one provider is configured and the AI Translate module is enabled.
  - DeepL API: Obtain an API key from the DeepL website.

## Usage

To use Auto Translation in Drupal 10+:

1. Enable one or more additional languages.
2. Enable and configure the Auto Translation module.
3. Click the Translate button on content that needs translation.
4. Review the auto-translated fields in the form.
5. Save and publish the translated content.

Alternatively, bulk translate content from /admin/content by selecting content items and choosing the Auto Translate action.

## Additional Features

- Real-time automatic translation
- Automatic translation of all fields when adding a node translation
- Form-based review process before saving translations
- Configurable content types (Media Types, Block Types, Taxonomy, and Node Types)
- Support for Content Moderation in bulk translations
- Option to exclude fields from translation
- Translation support for Paragraphs and nested Paragraphs
- Performance Optimization: Ensures efficient API usage and minimal website impact
- Security: Follows best practices and OWASP guidelines for data protection

## Conclusion

Auto Translator is a powerful tool for automatically translating content in Drupal. It is easy to use and provides high-quality translations for free.

Auto Translator for Drupal 10 is built with Gen AI support from GitHub Copilot, ChatGPT, and Google Bard to assist with development and documentation.

## Supporting this Module

If you need support or have feedback, you can submit a support request or report an issue here.

## Maintainers

- Alberto Cocchiara (bigbabert)

## License

This project is licensed under the GPL-2.0-or-later.
