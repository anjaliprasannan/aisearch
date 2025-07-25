# AI TMGMT (Translation Management)

The AI TMGMT (Translation Management) module is an AI translator plugin
for the Translation Management Tools (TMGMT) project. It uses the 
[AI](https://drupal.org/project/ai) module under the hood to allow you to use
OpenAI, Ollama, and many more other providers (both paid and local/free). You
will always have access to the latest and most cost-effective models to
translate your content accurately and automatically.

For a full description of the module, visit the
[project page](https://www.drupal.org/project/ai_tmgmt).

Submit bug reports and feature suggestions, or track changes in the
[issue queue](https://www.drupal.org/project/issues/ai_tmgmt).

## Features overview

- Fast automated translation of content using AI with ability to tweak
  translation style.
- Translate one or multiple nodes by a few simple mouse clicks.
- Use advanced translation jobs management tool to submit and review
  translations.
- The project of course also supports implicitly all the features which are
  provided by TMGMT like a feature-rich review process, being able to translate
  different sources and more.

## Table of contents

- Installation
- Configuration
- Prompt Examples
- Maintainers

## Installation

Install as you would normally install a contributed Drupal module. For further
information, see
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).

## Configuration

- Enable this module and at least one TMGMT 'Source' provider like 'Content
  Entity Source'
- Configure at least one Provider in the AI module at /admin/config/ai/settings
- Enable the TMGMT AI Provider at /admin/tmgmt/translators
- Check it works by going to /admin/tmgmt/sources, request a translation, and
  reviewing the generated translation results.

Note: you probably want to install this patch as the base TMGMT module runs jobs
twice, so you will use more tokens until the issue is fixed: 
[#3198609: Same Job processed/checkout twice by the JobCheckoutManager](https://www.drupal.org/project/tmgmt/issues/3198609).

## Prompt Examples

1. Default prompt
```
Translate from %source% into %target% language

%text%
```

2. Ability to change style of the translation:

```
Translate from %source% into %target% language in Ernest Hemingway style

%text%
```

## Migrating from the 'TMGMT OpenAI' module

Steps to migrate from https://www.drupal.org/project/tmgmt_openai:

1. Install this module as per the instructions
2. Ensure the new AI TMGMT provider is set up correctly
3. Ensure all Jobs related to tmgmt_openai are completed
4. Disable the tmgmt_openai provider
5. Uninstall the tmgmt_openai module

## Maintainers

- [Minnur Yunusov (minnur)](https://www.drupal.org/u/minnur)
  Wrote the original module and continues to maintain the project.
- [Scott Euser (scott_euser)](https://www.drupal.org/u/scott_euser)
  Ported the module over to leverage the AI module and supports maintenance.
