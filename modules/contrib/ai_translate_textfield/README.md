CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Installation
 * Configuration
 * Use
 * Maintainers


INTRODUCTION
------------

AI Textfield Translation (`ai_translate_textfield`) is a simple module that
adds a button to text fields to request a translation from an external
service.

This is useful in cases you want to keep the final control of the translation
in the editor's hands. The content is translated field by field.

Supports also CKEditor fields and Paragraphs.


REQUIREMENTS
------------

This module requires the [AI module](https://www.drupal.org/project/ai) and a provider
module that supports the `translate_text` operation type, such as
[DeepL Provider](https://www.drupal.org/project/ai_provider_deepl). Those modules may
have their own requirements.

If you want to strip the HTML markup from the original texts before feeding
it to the translator, you can install
[soundasleep/html2text](https://www.github.com/soundasleep/html2text) and it
will be used automatically instead of PHP `strip_tags()`. There's a config option
for this feature. The feature is not necessarily needed as the translation
services can usually handle HTML, but it might be useful in some cases.


INSTALLATION
------------

 * Install as you would normally install a contributed Drupal module. See:
   https://www.drupal.org/docs/extending-drupal/installing-modules
   for further information.


CONFIGURATION
-------------

There's a configuration form at /admin/config/ai/ai-translate-textfield. It
currently contains possibility to configure the translation service
backend and some options.

For instance, you can choose to enable a modal dialog warning to give
the editor some time to consider if they really want to execute the action.
Also, you can remove the formatting if you like.


USE
---

* Install the module.
* Configure it.
* Go to a form mode configuration of an entity that has the field you want
to be able to auto-translate. Select a field widget from this module.
* Edit a node or other entity with that field.


MAINTAINERS
-----------

Current maintainer:

 * [Jukka Huhta (jhuhta)](https://www.drupal.org/u/jhuhta)

Development of the first version was funded by:

 * [Aalto University](https://www.aalto.fi/en)

