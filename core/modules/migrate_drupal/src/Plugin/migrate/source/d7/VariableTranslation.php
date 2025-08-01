<?php

namespace Drupal\migrate_drupal\Plugin\migrate\source\d7;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate_drupal\Plugin\migrate\source\DrupalSqlBase;

/**
 * Drupal 7 variable_store source from database.
 *
 * Available configuration keys:
 * - variables: (required) The list of variable translations to retrieve from
 *   the source database. All translations are retrieved in a single row.
 *
 * Example:
 *
 * @code
 * plugin: d7_variable_translation
 * variables:
 *   - site_name
 *   - site_slogan
 * @endcode
 * In this example the translations for site_name and site_slogan variables are
 * retrieved from the source database.
 *
 * For additional configuration keys, refer to the parent classes.
 *
 * @see \Drupal\migrate\Plugin\migrate\source\SqlBase
 * @see \Drupal\migrate\Plugin\migrate\source\SourcePluginBase
 *
 * @MigrateSource(
 *   id = "d7_variable_translation",
 *   source_module = "i18n_variable",
 * )
 */
class VariableTranslation extends DrupalSqlBase {
  /**
   * The variable names to fetch.
   *
   * @var array
   */
  protected $variables;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration, StateInterface $state, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration, $state, $entity_type_manager);
    $this->variables = $this->configuration['variables'];
  }

  /**
   * {@inheritdoc}
   */
  protected function initializeIterator() {
    return new \ArrayIterator($this->values());
  }

  /**
   * Return the values of the variables specified in the plugin configuration.
   *
   * @return array
   *   An associative array where the keys are the variables specified in the
   *   plugin configuration and the values are the values found in the source.
   *   A key/value pair is added for the language code. Only those values are
   *   returned that are actually in the database.
   */
  protected function values() {
    $values = [];
    $result = $this->prepareQuery()->execute()->FetchAllAssoc('realm_key');
    foreach ($result as $variable_store) {
      $values[]['language'] = $variable_store['realm_key'];
    }
    $result = $this->prepareQuery()->execute()->FetchAll();
    foreach ($result as $variable_store) {
      foreach ($values as $key => $value) {
        if ($values[$key]['language'] === $variable_store['realm_key']) {
          if ($variable_store['serialized']) {
            $values[$key][$variable_store['name']] = unserialize($variable_store['value'], ['allowed_classes' => FALSE]);
            break;
          }
          else {
            $values[$key][$variable_store['name']] = $variable_store['value'];
            break;
          }
        }
      }
    }
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  protected function doCount() {
    return $this->initializeIterator()->count();
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return array_combine($this->variables, $this->variables);
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    $ids['language']['type'] = 'string';
    return $ids;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    return $this->select('variable_store', 'vs')
      ->fields('vs')
      ->condition('realm', 'language')
      ->condition('name', (array) $this->configuration['variables'], 'IN');
  }

}
