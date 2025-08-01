<?php

namespace Drupal\views\Plugin\views\filter;

use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Attribute\ViewsFilter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Basic textfield filter to handle string filtering commands.
 *
 * Including equality, like, not like, etc.
 *
 * @ingroup views_filter_handlers
 */
#[ViewsFilter("string")]
class StringFilter extends FilterPluginBase implements FilterOperatorsInterface {

  /**
   * All words separated by spaces or sentences encapsulated by double quotes.
   */
  const WORDS_PATTERN = '/ (-?)("[^"]+"|[^" ]+)/i';

  /**
   * Exposed filter options.
   *
   * @var bool
   */
  protected $alwaysMultiple = TRUE;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * Constructs a new StringFilter object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Connection $connection) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->connection = $connection;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('database')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['expose']['contains']['required'] = ['default' => FALSE];
    $options['expose']['contains']['placeholder'] = ['default' => ''];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultExposeOptions() {
    parent::defaultExposeOptions();
    $this->options['expose']['placeholder'] = NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function buildExposeForm(&$form, FormStateInterface $form_state) {
    parent::buildExposeForm($form, $form_state);
    $form['expose']['placeholder'] = [
      '#type' => 'textfield',
      '#default_value' => $this->options['expose']['placeholder'],
      '#title' => $this->t('Placeholder'),
      '#size' => 40,
      '#description' => $this->t('Hint text that appears inside the field when empty.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function operators() {
    $operators = [
      '=' => [
        'title' => $this->t('Is equal to'),
        'short' => $this->t('='),
        'method' => 'opEqual',
        'values' => 1,
      ],
      '!=' => [
        'title' => $this->t('Is not equal to'),
        'short' => $this->t('!='),
        'method' => 'opEqual',
        'values' => 1,
      ],
      'contains' => [
        'title' => $this->t('Contains'),
        'short' => $this->t('contains'),
        'method' => 'opContains',
        'values' => 1,
      ],
      'word' => [
        'title' => $this->t('Contains any word'),
        'short' => $this->t('has word'),
        'method' => 'opContainsWord',
        'values' => 1,
      ],
      'allwords' => [
        'title' => $this->t('Contains all words'),
        'short' => $this->t('has all'),
        'method' => 'opContainsWord',
        'values' => 1,
      ],
      'starts' => [
        'title' => $this->t('Starts with'),
        'short' => $this->t('begins'),
        'method' => 'opStartsWith',
        'values' => 1,
      ],
      'not_starts' => [
        'title' => $this->t('Does not start with'),
        'short' => $this->t('not_begins'),
        'method' => 'opNotStartsWith',
        'values' => 1,
      ],
      'ends' => [
        'title' => $this->t('Ends with'),
        'short' => $this->t('ends'),
        'method' => 'opEndsWith',
        'values' => 1,
      ],
      'not_ends' => [
        'title' => $this->t('Does not end with'),
        'short' => $this->t('not_ends'),
        'method' => 'opNotEndsWith',
        'values' => 1,
      ],
      'not' => [
        'title' => $this->t('Does not contain'),
        'short' => $this->t('!has'),
        'method' => 'opNotLike',
        'values' => 1,
      ],
      'shorterthan' => [
        'title' => $this->t('Length is shorter than'),
        'short' => $this->t('shorter than'),
        'method' => 'opShorterThan',
        'values' => 1,
      ],
      'longerthan' => [
        'title' => $this->t('Length is longer than'),
        'short' => $this->t('longer than'),
        'method' => 'opLongerThan',
        'values' => 1,
      ],
      'regular_expression' => [
        'title' => $this->t('Regular expression'),
        'short' => $this->t('regex'),
        'method' => 'opRegex',
        'values' => 1,
      ],
      'not_regular_expression' => [
        'title' => $this->t('Negated regular expression'),
        'short' => $this->t('not regex'),
        'method' => 'opNotRegex',
        'values' => 1,
      ],
    ];
    // If the definition allows for the empty operator, add it.
    if (!empty($this->definition['allow empty'])) {
      $operators += [
        'empty' => [
          'title' => $this->t('Is empty (NULL)'),
          'method' => 'opEmpty',
          'short' => $this->t('empty'),
          'values' => 0,
        ],
        'not empty' => [
          'title' => $this->t('Is not empty (NOT NULL)'),
          'method' => 'opEmpty',
          'short' => $this->t('not empty'),
          'values' => 0,
        ],
      ];
    }

    return $operators;
  }

  /**
   * Build strings from the operators() for 'select' options.
   */
  public function operatorOptions($which = 'title') {
    $options = [];
    foreach ($this->operators() as $id => $info) {
      $options[$id] = $info[$which];
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function adminSummary() {
    if ($this->isAGroup()) {
      return $this->t('grouped');
    }
    if (!empty($this->options['exposed'])) {
      return $this->t('exposed');
    }

    $options = $this->operatorOptions('short');
    $output = '';
    if (!empty($options[$this->operator])) {
      $output = $options[$this->operator];
    }
    if (in_array($this->operator, $this->operatorValues(1))) {
      $output .= ' ' . $this->value;
    }
    return $output;
  }

  /**
   * Gets the operators that have a given number of values.
   */
  protected function operatorValues($values = 1) {
    $options = [];
    foreach ($this->operators() as $id => $info) {
      if (isset($info['values']) && $info['values'] == $values) {
        $options[] = $id;
      }
    }

    return $options;
  }

  /**
   * Provide a simple textfield for equality.
   */
  protected function valueForm(&$form, FormStateInterface $form_state) {
    // We have to make some choices when creating this as an exposed
    // filter form. For example, if the operator is locked and thus
    // not rendered, we can't render dependencies; instead we only
    // render the form items we need.
    $which = 'all';
    if (!empty($form['operator'])) {
      $source = ':input[name="options[operator]"]';
    }
    if ($exposed = $form_state->get('exposed')) {
      $identifier = $this->options['expose']['identifier'];

      if (empty($this->options['expose']['use_operator']) || empty($this->options['expose']['operator_id'])) {
        // Exposed and locked.
        $which = in_array($this->operator, $this->operatorValues(1)) ? 'value' : 'none';
      }
      else {
        $source = ':input[name="' . $this->options['expose']['operator_id'] . '"]';
      }
    }

    if ($which == 'all' || $which == 'value') {
      $form['value'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Value'),
        '#size' => 30,
        '#default_value' => $this->value,
      ];
      if (!empty($this->options['expose']['placeholder'])) {
        $form['value']['#attributes']['placeholder'] = $this->options['expose']['placeholder'];
      }
      $user_input = $form_state->getUserInput();
      if ($exposed && !isset($user_input[$identifier])) {
        $user_input[$identifier] = $this->value;
        $form_state->setUserInput($user_input);
      }

      if ($which == 'all') {
        // Setup #states for all operators with one value.
        foreach ($this->operatorValues(1) as $operator) {
          $form['value']['#states']['visible'][] = [
            $source => ['value' => $operator],
          ];
        }
      }
    }

    if (!isset($form['value'])) {
      // Ensure there is something in the 'value'.
      $form['value'] = [
        '#type' => 'value',
        '#value' => NULL,
      ];
    }
  }

  /**
   * Get the query operator.
   *
   * @return string
   *   Returns LIKE or NOT LIKE or the database specific equivalent based on the
   *   query's operator.
   */
  public function operator() {
    return $this->getConditionOperator($this->operator == '=' ? 'LIKE' : 'NOT LIKE');
  }

  /**
   * Get specified condition operator mapping value.
   *
   * @param string $operator
   *   Condition operator.
   *
   * @return string
   *   Specified condition operator mapping value.
   */
  protected function getConditionOperator($operator) {
    $mapping = $this->connection->mapConditionOperator($operator);
    return $mapping['operator'] ?? $operator;
  }

  /**
   * Add this filter to the query.
   *
   * Due to the nature of fapi, the value and the operator have an unintended
   * level of indirection. You will find them in $this->operator
   * and $this->value respectively.
   */
  public function query() {
    $this->ensureMyTable();
    $field = "$this->tableAlias.$this->realField";

    $info = $this->operators();
    if (!empty($info[$this->operator]['method'])) {
      $this->{$info[$this->operator]['method']}($field);
    }
  }

  /**
   * Adds a where clause for the operation, 'equals'.
   */
  public function opEqual($field) {
    $this->query->addWhere($this->options['group'], $field, $this->connection->escapeLike($this->value), $this->operator());
  }

  /**
   * Adds a where clause for the operation, 'LIKE'.
   */
  protected function opContains($field) {
    $operator = $this->getConditionOperator('LIKE');
    $this->query->addWhere($this->options['group'], $field, '%' . $this->connection->escapeLike($this->value) . '%', $operator);
  }

  /**
   * Adds a where clause for the operation, 'contains'.
   */
  protected function opContainsWord($field) {
    $where = $this->operator == 'word' ? $this->query->getConnection()->condition('OR') : $this->query->getConnection()->condition('AND');

    // Don't filter on empty strings.
    if (empty($this->value)) {
      return;
    }

    preg_match_all(static::WORDS_PATTERN, ' ' . $this->value, $matches, PREG_SET_ORDER);
    $operator = $this->getConditionOperator('LIKE');
    foreach ($matches as $match) {
      $phrase = FALSE;
      // Strip off phrase quotes.
      if ($match[2][0] == '"') {
        $match[2] = substr($match[2], 1, -1);
        $phrase = TRUE;
      }
      $words = trim($match[2], ',?!();:-');
      $words = $phrase ? [$words] : preg_split('/ /', $words, -1, PREG_SPLIT_NO_EMPTY);
      foreach ($words as $word) {
        $where->condition($field, '%' . $this->connection->escapeLike(trim($word, " ,!?")) . '%', $operator);
      }
    }

    if ($where->count() === 0) {
      return;
    }

    // Previously this was a call_user_func_array but that's unnecessary
    // as views will unpack an array that is a single arg.
    $this->query->addWhere($this->options['group'], $where);
  }

  /**
   * Adds a where clause for the operation, 'starts with'.
   */
  protected function opStartsWith($field) {
    $operator = $this->getConditionOperator('LIKE');
    $this->query->addWhere($this->options['group'], $field, $this->connection->escapeLike($this->value) . '%', $operator);
  }

  /**
   * Adds a where clause for the operation, 'not starts with'.
   */
  protected function opNotStartsWith($field) {
    $operator = $this->getConditionOperator('NOT LIKE');
    $this->query->addWhere($this->options['group'], $field, $this->connection->escapeLike($this->value) . '%', $operator);
  }

  /**
   * Adds a where clause for the operation, 'ends with'.
   */
  protected function opEndsWith($field) {
    $operator = $this->getConditionOperator('LIKE');
    $this->query->addWhere($this->options['group'], $field, '%' . $this->connection->escapeLike($this->value), $operator);
  }

  /**
   * Adds a where clause for the operation, 'not ends with'.
   */
  protected function opNotEndsWith($field) {
    $operator = $this->getConditionOperator('NOT LIKE');
    $this->query->addWhere($this->options['group'], $field, '%' . $this->connection->escapeLike($this->value), $operator);
  }

  /**
   * Adds a where clause for the operation, 'not LIKE'.
   */
  protected function opNotLike($field) {
    $operator = $this->getConditionOperator('NOT LIKE');
    $this->query->addWhere($this->options['group'], $field, '%' . $this->connection->escapeLike($this->value) . '%', $operator);
  }

  /**
   * Adds a where clause for the operation, 'shorter than'.
   */
  protected function opShorterThan($field) {
    $placeholder = $this->placeholder();
    // Type cast the argument to an integer because the SQLite database driver
    // has to do some specific alterations to the query base on that data type.
    $this->query->addWhereExpression($this->options['group'], "LENGTH($field) < $placeholder", [$placeholder => (int) $this->value]);
  }

  /**
   * Adds a where clause for the operation, 'longer than'.
   */
  protected function opLongerThan($field) {
    $placeholder = $this->placeholder();
    // Type cast the argument to an integer because the SQLite database driver
    // has to do some specific alterations to the query base on that data type.
    $this->query->addWhereExpression($this->options['group'], "LENGTH($field) > $placeholder", [$placeholder => (int) $this->value]);
  }

  /**
   * Filters by a regular expression.
   *
   * @param string $field
   *   The expression pointing to the queries field, for example "foo.bar".
   */
  protected function opRegex($field) {
    $this->query->addWhere($this->options['group'], $field, $this->value, 'REGEXP');
  }

  /**
   * Filters by a negated regular expression.
   *
   * @param string $field
   *   The expression pointing to the queries field, for example "foo.bar".
   */
  protected function opNotRegex($field) {
    $this->query->addWhere($this->options['group'], $field, $this->value, 'NOT REGEXP');
  }

  /**
   * Adds a where clause for the operation, 'EMPTY'.
   */
  protected function opEmpty($field) {
    if ($this->operator == 'empty') {
      $operator = "IS NULL";
    }
    else {
      $operator = "IS NOT NULL";
    }

    $this->query->addWhere($this->options['group'], $field, NULL, $operator);
  }

}
