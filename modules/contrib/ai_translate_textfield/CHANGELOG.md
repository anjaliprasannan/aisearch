# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0-alpha1] - 2024-10-15

### Added
- This module is now part of the Drupal AI ecosystem, and needs a provider module
  that supports `translate_text` operation type of the AI module. There is also
  a DeepL Provider module available, created by the same author than this module.
- The module now supports multiple translation providers. The provider can be
  selected in the module settings. No other text translation providers are known
  at this point though.

### Changed
- Error handling has been improved. The module now shows errors in the UI.
- Configuration has been altered - some keys were removed and some others
  added. Run drush updatedb and update (& export) the module settings after
  updating.
- The settings form is now under other AI settings.

### Removed
- No direct ChatGPT support anymore. The module now requires a provider module
  to be installed. But the module doesn't support the "chat" operation.
  This could be added at later point, to support a variety of other AI providers.

## [1.0.0-alpha1] - 2024-08-26

### Added
- Initial release of the module.
- Support text with summary fields.
- Add a method for handling language variants such as en -> en-GB.

## [1.0.x-dev] - 2024-02-02

### Added

- This changelog file.
- Support for new field types. Currently
  supported: `string_textarea`, `text_textarea`, `string_textfield`, `text_textfield`. More can be easily added by
  editing AiTranslatorInterface.
- Support for HTML formatting of the fields using the DeepL API.

### Changed

- **#3418835:** by `jhuhta`: Compatibility with other contrib modules: needs manual reconfiguration of the field
  widgets!
  - Deprecated the initial field widgets but not deleted them, to not break possible sites using this. They will be
    removed in the next major release.
- AiTranslatorInterface has been altered. This is a BC break, but it's not expected that anyone has implemented this
  interface yet.
- Started using [deepl-php](https://github.com/DeepLcom/deepl-php) library.

## [1.0.x-dev] - 2024-01-19

### Fixed

- **#3415743:** by `Revathi.B`: Add a settings entry in the info.yml.
- **#3415759:** by `akhil_01`: Fix config file name.

## [1.0.x-dev] - 2024-01-18

### Added

- Initial publication of the module
