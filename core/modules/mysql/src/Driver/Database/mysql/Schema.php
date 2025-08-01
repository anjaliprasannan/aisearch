<?php

namespace Drupal\mysql\Driver\Database\mysql;

use Drupal\Core\Database\Exception\SchemaPrimaryKeyMustBeDroppedException;
use Drupal\Core\Database\SchemaException;
use Drupal\Core\Database\SchemaObjectExistsException;
use Drupal\Core\Database\SchemaObjectDoesNotExistException;
use Drupal\Core\Database\Schema as DatabaseSchema;
use Drupal\Component\Utility\Unicode;

// cspell:ignore gipk

/**
 * @addtogroup schemaapi
 * @{
 */

/**
 * MySQL implementation of \Drupal\Core\Database\Schema.
 */
class Schema extends DatabaseSchema {

  /**
   * Maximum length of a table comment in MySQL.
   */
  const COMMENT_MAX_TABLE = 60;

  /**
   * Maximum length of a column comment in MySQL.
   */
  const COMMENT_MAX_COLUMN = 255;

  /**
   * @var array
   *   List of MySQL string types.
   */
  protected $mysqlStringTypes = [
    'VARCHAR',
    'CHAR',
    'TINYTEXT',
    'MEDIUMTEXT',
    'LONGTEXT',
    'TEXT',
  ];

  /**
   * Get information about the table and database name from the prefix.
   *
   * @return array
   *   A keyed array with information about the database, table name and prefix.
   */
  protected function getPrefixInfo($table = 'default', $add_prefix = TRUE) {
    $info = ['prefix' => $this->connection->getPrefix()];
    if ($add_prefix) {
      $table = $info['prefix'] . $table;
    }
    if (($pos = strpos($table, '.')) !== FALSE) {
      $info['database'] = substr($table, 0, $pos);
      $info['table'] = substr($table, ++$pos);
    }
    else {
      $info['database'] = $this->connection->getConnectionOptions()['database'];
      $info['table'] = $table;
    }
    return $info;
  }

  /**
   * Builds a condition to match a table name with the information schema.
   *
   * MySQL uses databases like schemas rather than catalogs so when we build a
   * condition to query the information_schema.tables, we set the default
   * database as the schema unless specified otherwise, and exclude
   * table_catalog from the condition criteria.
   */
  protected function buildTableNameCondition($table_name, $operator = '=', $add_prefix = TRUE) {
    $table_info = $this->getPrefixInfo($table_name, $add_prefix);

    $condition = $this->connection->condition('AND');
    $condition->condition('table_schema', $table_info['database']);
    $condition->condition('table_name', $table_info['table'], $operator);
    return $condition;
  }

  /**
   * {@inheritdoc}
   */
  protected function createTableSql($name, $table) {
    $info = $this->connection->getConnectionOptions();

    // Provide defaults if needed.
    $table += [
      'mysql_engine' => 'InnoDB',
      'mysql_character_set' => 'utf8mb4',
    ];

    $sql = "CREATE TABLE {" . $name . "} (\n";

    // Add the SQL statement for each field.
    foreach ($table['fields'] as $field_name => $field) {
      $sql .= $this->createFieldSql($field_name, $this->processField($field)) . ", \n";
    }

    // Process keys & indexes.
    if (!empty($table['primary key']) && is_array($table['primary key'])) {
      $this->ensureNotNullPrimaryKey($table['primary key'], $table['fields']);
    }
    $keys = $this->createKeysSql($table);
    if (count($keys)) {
      $sql .= implode(", \n", $keys) . ", \n";
    }

    // Remove the last comma and space.
    $sql = substr($sql, 0, -3) . "\n) ";

    $sql .= 'ENGINE = ' . $table['mysql_engine'] . ' DEFAULT CHARACTER SET ' . $table['mysql_character_set'];
    // By default, MySQL uses the default collation for new tables, which is
    // 'utf8mb4_general_ci' (MySQL 5) or 'utf8mb4_0900_ai_ci' (MySQL 8) for
    // utf8mb4. If an alternate collation has been set, it needs to be
    // explicitly specified.
    // @see \Drupal\mysql\Driver\Database\mysql\Schema
    if (!empty($info['collation'])) {
      $sql .= ' COLLATE ' . $info['collation'];
    }

    // Add table comment.
    if (!empty($table['description'])) {
      $sql .= ' COMMENT ' . $this->prepareComment($table['description'], self::COMMENT_MAX_TABLE);
    }

    return [$sql];
  }

  /**
   * Creates an SQL string for a field used in table creation or alteration.
   *
   * @param string $name
   *   Name of the field.
   * @param array $spec
   *   The field specification, as per the schema data structure format.
   */
  protected function createFieldSql($name, $spec) {
    $sql = "[" . $name . "] " . $spec['mysql_type'];

    if (in_array($spec['mysql_type'], $this->mysqlStringTypes)) {
      if (isset($spec['length'])) {
        $sql .= '(' . $spec['length'] . ')';
      }
      if (isset($spec['type']) && $spec['type'] == 'varchar_ascii') {
        $sql .= ' CHARACTER SET ascii';
      }
      if (!empty($spec['binary'])) {
        $sql .= ' BINARY';
      }
      // Note we check for the "type" key here. "mysql_type" is VARCHAR:
      elseif (isset($spec['type']) && $spec['type'] == 'varchar_ascii') {
        $sql .= ' COLLATE ascii_general_ci';
      }
    }
    elseif (isset($spec['precision']) && isset($spec['scale'])) {
      $sql .= '(' . $spec['precision'] . ', ' . $spec['scale'] . ')';
    }

    if (!empty($spec['unsigned'])) {
      $sql .= ' unsigned';
    }

    if (isset($spec['not null'])) {
      if ($spec['not null']) {
        $sql .= ' NOT NULL';
      }
      else {
        $sql .= ' NULL';
      }
    }

    if (!empty($spec['auto_increment'])) {
      $sql .= ' auto_increment';
    }

    // $spec['default'] can be NULL, so we explicitly check for the key here.
    if (array_key_exists('default', $spec)) {
      $sql .= ' DEFAULT ' . $this->escapeDefaultValue($spec['default']);
    }

    if (empty($spec['not null']) && !isset($spec['default'])) {
      $sql .= ' DEFAULT NULL';
    }

    // Add column comment.
    if (!empty($spec['description'])) {
      $sql .= ' COMMENT ' . $this->prepareComment($spec['description'], self::COMMENT_MAX_COLUMN);
    }

    return $sql;
  }

  /**
   * Set database-engine specific properties for a field.
   *
   * @param array $field
   *   A field description array, as specified in the schema documentation.
   */
  protected function processField($field) {

    if (!isset($field['size'])) {
      $field['size'] = 'normal';
    }

    // Set the correct database-engine specific datatype.
    // In case one is already provided, force it to uppercase.
    if (isset($field['mysql_type'])) {
      $field['mysql_type'] = mb_strtoupper($field['mysql_type']);
    }
    else {
      $map = $this->getFieldTypeMap();
      $field['mysql_type'] = $map[$field['type'] . ':' . $field['size']];
    }

    if (isset($field['type']) && $field['type'] == 'serial') {
      $field['auto_increment'] = TRUE;
    }

    return $field;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldTypeMap() {
    // Put :normal last so it gets preserved by array_flip. This makes
    // it much easier for modules (such as schema.module) to map
    // database types back into schema types.
    // $map does not use drupal_static as its value never changes.
    static $map = [
      'varchar_ascii:normal' => 'VARCHAR',

      'varchar:normal'  => 'VARCHAR',
      'char:normal'     => 'CHAR',

      'text:tiny'       => 'TINYTEXT',
      'text:small'      => 'TINYTEXT',
      'text:medium'     => 'MEDIUMTEXT',
      'text:big'        => 'LONGTEXT',
      'text:normal'     => 'TEXT',

      'serial:tiny'     => 'TINYINT',
      'serial:small'    => 'SMALLINT',
      'serial:medium'   => 'MEDIUMINT',
      'serial:big'      => 'BIGINT',
      'serial:normal'   => 'INT',

      'int:tiny'        => 'TINYINT',
      'int:small'       => 'SMALLINT',
      'int:medium'      => 'MEDIUMINT',
      'int:big'         => 'BIGINT',
      'int:normal'      => 'INT',

      'float:tiny'      => 'FLOAT',
      'float:small'     => 'FLOAT',
      'float:medium'    => 'FLOAT',
      'float:big'       => 'DOUBLE',
      'float:normal'    => 'FLOAT',

      'numeric:normal'  => 'DECIMAL',

      'blob:big'        => 'LONGBLOB',
      'blob:normal'     => 'BLOB',
    ];
    return $map;
  }

  /**
   * Creates the keys for an SQL table.
   */
  protected function createKeysSql($spec) {
    $keys = [];

    if (!empty($spec['primary key'])) {
      $keys[] = 'PRIMARY KEY (' . $this->createKeySql($spec['primary key']) . ')';
    }
    if (!empty($spec['unique keys'])) {
      foreach ($spec['unique keys'] as $key => $fields) {
        $keys[] = 'UNIQUE KEY [' . $key . '] (' . $this->createKeySql($fields) . ')';
      }
    }
    if (!empty($spec['indexes'])) {
      $indexes = $this->getNormalizedIndexes($spec);
      foreach ($indexes as $index => $fields) {
        $keys[] = 'INDEX [' . $index . '] (' . $this->createKeySql($fields) . ')';
      }
    }

    return $keys;
  }

  /**
   * Gets normalized indexes from a table specification.
   *
   * Shortens indexes to 191 characters if they apply to utf8mb4-encoded
   * fields, in order to comply with the InnoDB index limitation of 756 bytes.
   *
   * @param array $spec
   *   The table specification.
   *
   * @return array
   *   List of shortened indexes.
   *
   * @throws \Drupal\Core\Database\SchemaException
   *   Thrown if field specification is missing.
   */
  protected function getNormalizedIndexes(array $spec) {
    $indexes = $spec['indexes'] ?? [];
    foreach ($indexes as $index_name => $index_fields) {
      foreach ($index_fields as $index_key => $index_field) {
        // Get the name of the field from the index specification.
        $field_name = is_array($index_field) ? $index_field[0] : $index_field;
        // Check whether the field is defined in the table specification.
        if (isset($spec['fields'][$field_name])) {
          // Get the MySQL type from the processed field.
          $mysql_field = $this->processField($spec['fields'][$field_name]);
          if (in_array($mysql_field['mysql_type'], $this->mysqlStringTypes)) {
            // Check whether we need to shorten the index.
            if ((!isset($mysql_field['type']) || $mysql_field['type'] != 'varchar_ascii') && (!isset($mysql_field['length']) || $mysql_field['length'] > 191)) {
              // Limit the index length to 191 characters.
              $this->shortenIndex($indexes[$index_name][$index_key]);
            }
          }
        }
        else {
          throw new SchemaException("MySQL needs the '$field_name' field specification in order to normalize the '$index_name' index");
        }
      }
    }
    return $indexes;
  }

  /**
   * Helper function for normalizeIndexes().
   *
   * Shortens an index to 191 characters.
   *
   * @param array $index
   *   The index array to be used in createKeySql.
   *
   * @see Drupal\mysql\Driver\Database\mysql\Schema::createKeySql()
   * @see Drupal\mysql\Driver\Database\mysql\Schema::normalizeIndexes()
   */
  protected function shortenIndex(&$index) {
    if (is_array($index)) {
      if ($index[1] > 191) {
        $index[1] = 191;
      }
    }
    else {
      $index = [$index, 191];
    }
  }

  /**
   * Creates an SQL key for the given fields.
   */
  protected function createKeySql($fields) {
    $return = [];
    foreach ($fields as $field) {
      if (is_array($field)) {
        $return[] = '[' . $field[0] . '] (' . $field[1] . ')';
      }
      else {
        $return[] = '[' . $field . ']';
      }
    }
    return implode(', ', $return);
  }

  /**
   * {@inheritdoc}
   */
  public function renameTable($table, $new_name) {
    if (!$this->tableExists($table)) {
      throw new SchemaObjectDoesNotExistException("Cannot rename '$table' to '$new_name': table '$table' doesn't exist.");
    }
    if ($this->tableExists($new_name)) {
      throw new SchemaObjectExistsException("Cannot rename '$table' to '$new_name': table '$new_name' already exists.");
    }

    $info = $this->getPrefixInfo($new_name);
    $this->executeDdlStatement('ALTER TABLE {' . $table . '} RENAME TO [' . $info['table'] . ']');
  }

  /**
   * {@inheritdoc}
   */
  public function dropTable($table) {
    if (!$this->tableExists($table)) {
      return FALSE;
    }

    $this->executeDdlStatement('DROP TABLE {' . $table . '}');
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function addField($table, $field, $spec, $keys_new = []) {
    if (!$this->tableExists($table)) {
      throw new SchemaObjectDoesNotExistException("Cannot add field '$table.$field': table doesn't exist.");
    }
    if ($this->fieldExists($table, $field)) {
      throw new SchemaObjectExistsException("Cannot add field '$table.$field': field already exists.");
    }

    // Fields that are part of a PRIMARY KEY must be added as NOT NULL.
    $is_primary_key = isset($keys_new['primary key']) && in_array($field, $keys_new['primary key'], TRUE);
    if ($is_primary_key) {
      $this->ensureNotNullPrimaryKey($keys_new['primary key'], [$field => $spec]);
    }

    $fix_null = FALSE;
    if (!empty($spec['not null']) && !isset($spec['default']) && !$is_primary_key) {
      $fix_null = TRUE;
      $spec['not null'] = FALSE;
    }
    $query = 'ALTER TABLE {' . $table . '} ADD ';
    $query .= $this->createFieldSql($field, $this->processField($spec));
    if ($keys_sql = $this->createKeysSql($keys_new)) {
      // Make sure to drop the existing primary key before adding a new one.
      // This is only needed when adding a field because this method, unlike
      // changeField(), is supposed to handle primary keys automatically.
      if (isset($keys_new['primary key']) && $this->indexExists($table, 'PRIMARY')) {
        $query .= ', DROP PRIMARY KEY';
      }

      $query .= ', ADD ' . implode(', ADD ', $keys_sql);
    }
    try {
      $this->executeDdlStatement($query);
    }
    catch (SchemaPrimaryKeyMustBeDroppedException $e) {
      // MySQL error number 4111 (ER_DROP_PK_COLUMN_TO_DROP_GIPK) indicates that
      // when dropping and adding a primary key, the generated invisible primary
      // key (GIPK) column must also be dropped.
      if (isset($keys_new['primary key']) && $this->indexExists($table, 'PRIMARY') && $this->findPrimaryKeyColumns($table) === ['my_row_id']) {
        $this->executeDdlStatement($query . ', DROP COLUMN [my_row_id]');
      }
      else {
        throw $e;
      }
    }

    if (isset($spec['initial_from_field'])) {
      if (isset($spec['initial'])) {
        $expression = 'COALESCE(' . $spec['initial_from_field'] . ', :default_initial_value)';
        $arguments = [':default_initial_value' => $spec['initial']];
      }
      else {
        $expression = $spec['initial_from_field'];
        $arguments = [];
      }
      $this->connection->update($table)
        ->expression($field, $expression, $arguments)
        ->execute();
    }
    elseif (isset($spec['initial'])) {
      $this->connection->update($table)
        ->fields([$field => $spec['initial']])
        ->execute();
    }
    if ($fix_null) {
      $spec['not null'] = TRUE;
      $this->changeField($table, $field, $field, $spec);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function dropField($table, $field) {
    if (!$this->fieldExists($table, $field)) {
      return FALSE;
    }

    // When dropping a field that is part of a composite primary key MySQL
    // automatically removes the field from the primary key, which can leave the
    // table in an invalid state. MariaDB 10.2.8 requires explicitly dropping
    // the primary key first for this reason. We perform this deletion
    // explicitly which also makes the behavior on both MySQL and MariaDB
    // consistent with PostgreSQL.
    // @see https://mariadb.com/kb/en/library/alter-table
    $primary_key = $this->findPrimaryKeyColumns($table);
    if ((count($primary_key) > 1) && in_array($field, $primary_key, TRUE)) {
      $this->dropPrimaryKey($table);
    }

    $this->executeDdlStatement('ALTER TABLE {' . $table . '} DROP [' . $field . ']');
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function indexExists($table, $name) {
    // Returns one row for each column in the index. Result is string or FALSE.
    // Details at http://dev.mysql.com/doc/refman/5.0/en/show-index.html
    $row = $this->connection->query('SHOW INDEX FROM {' . $table . '} WHERE key_name = ' . $this->connection->quote($name))->fetchAssoc();
    return isset($row['Key_name']);
  }

  /**
   * {@inheritdoc}
   */
  public function addPrimaryKey($table, $fields) {
    if (!$this->tableExists($table)) {
      throw new SchemaObjectDoesNotExistException("Cannot add primary key to table '$table': table doesn't exist.");
    }
    if ($this->indexExists($table, 'PRIMARY')) {
      throw new SchemaObjectExistsException("Cannot add primary key to table '$table': primary key already exists.");
    }

    $this->executeDdlStatement('ALTER TABLE {' . $table . '} ADD PRIMARY KEY (' . $this->createKeySql($fields) . ')');
  }

  /**
   * {@inheritdoc}
   */
  public function dropPrimaryKey($table) {
    if (!$this->indexExists($table, 'PRIMARY')) {
      return FALSE;
    }

    $this->executeDdlStatement('ALTER TABLE {' . $table . '} DROP PRIMARY KEY');
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  protected function findPrimaryKeyColumns($table) {
    if (!$this->tableExists($table)) {
      return FALSE;
    }
    $result = $this->connection->query("SHOW KEYS FROM {" . $table . "} WHERE Key_name = 'PRIMARY'")->fetchAllAssoc('Column_name');
    return array_keys($result);
  }

  /**
   * {@inheritdoc}
   */
  public function addUniqueKey($table, $name, $fields) {
    if (!$this->tableExists($table)) {
      throw new SchemaObjectDoesNotExistException("Cannot add unique key '$name' to table '$table': table doesn't exist.");
    }
    if ($this->indexExists($table, $name)) {
      throw new SchemaObjectExistsException("Cannot add unique key '$name' to table '$table': unique key already exists.");
    }

    $this->executeDdlStatement('ALTER TABLE {' . $table . '} ADD UNIQUE KEY [' . $name . '] (' . $this->createKeySql($fields) . ')');
  }

  /**
   * {@inheritdoc}
   */
  public function dropUniqueKey($table, $name) {
    if (!$this->indexExists($table, $name)) {
      return FALSE;
    }

    $this->executeDdlStatement('ALTER TABLE {' . $table . '} DROP KEY [' . $name . ']');
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function addIndex($table, $name, $fields, array $spec) {
    if (!$this->tableExists($table)) {
      throw new SchemaObjectDoesNotExistException("Cannot add index '$name' to table '$table': table doesn't exist.");
    }
    if ($this->indexExists($table, $name)) {
      throw new SchemaObjectExistsException("Cannot add index '$name' to table '$table': index already exists.");
    }

    $spec['indexes'][$name] = $fields;
    $indexes = $this->getNormalizedIndexes($spec);

    $this->executeDdlStatement('ALTER TABLE {' . $table . '} ADD INDEX [' . $name . '] (' . $this->createKeySql($indexes[$name]) . ')');
  }

  /**
   * {@inheritdoc}
   */
  public function dropIndex($table, $name) {
    if (!$this->indexExists($table, $name)) {
      return FALSE;
    }

    $this->executeDdlStatement('ALTER TABLE {' . $table . '} DROP INDEX [' . $name . ']');
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  protected function introspectIndexSchema($table) {
    if (!$this->tableExists($table)) {
      throw new SchemaObjectDoesNotExistException("The table $table doesn't exist.");
    }

    $index_schema = [
      'primary key' => [],
      'unique keys' => [],
      'indexes' => [],
    ];

    $result = $this->connection->query('SHOW INDEX FROM {' . $table . '}')->fetchAll();
    foreach ($result as $row) {
      if ($row->Key_name === 'PRIMARY') {
        $index_schema['primary key'][] = $row->Column_name;
      }
      elseif ($row->Non_unique == 0) {
        $index_schema['unique keys'][$row->Key_name][] = $row->Column_name;
      }
      else {
        $index_schema['indexes'][$row->Key_name][] = $row->Column_name;
      }
    }

    return $index_schema;
  }

  /**
   * {@inheritdoc}
   */
  public function changeField($table, $field, $field_new, $spec, $keys_new = []) {
    if (!$this->fieldExists($table, $field)) {
      throw new SchemaObjectDoesNotExistException("Cannot change the definition of field '$table.$field': field doesn't exist.");
    }
    if (($field != $field_new) && $this->fieldExists($table, $field_new)) {
      throw new SchemaObjectExistsException("Cannot rename field '$table.$field' to '$field_new': target field already exists.");
    }
    if (isset($keys_new['primary key']) && in_array($field_new, $keys_new['primary key'], TRUE)) {
      $this->ensureNotNullPrimaryKey($keys_new['primary key'], [$field_new => $spec]);
    }

    $sql = 'ALTER TABLE {' . $table . '} CHANGE [' . $field . '] ' . $this->createFieldSql($field_new, $this->processField($spec));
    if ($keys_sql = $this->createKeysSql($keys_new)) {
      $sql .= ', ADD ' . implode(', ADD ', $keys_sql);
    }
    $this->executeDdlStatement($sql);

    if ($spec['type'] === 'serial') {
      $max = $this->connection->query('SELECT MAX(`' . $field_new . '`) FROM {' . $table . '}')->fetchField();
      $this->executeDdlStatement("ALTER TABLE {" . $table . "} AUTO_INCREMENT = " . ($max + 1));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function prepareComment($comment, $length = NULL) {
    // Truncate comment to maximum comment length.
    if (isset($length)) {
      // Add table prefix before truncating.
      $comment = Unicode::truncate($this->connection->prefixTables($comment), $length, TRUE, TRUE);
    }
    // Remove semicolons to avoid triggering multi-statement check.
    $comment = strtr($comment, [';' => '.']);
    return $this->connection->quote($comment);
  }

  /**
   * Retrieve a table or column comment.
   */
  public function getComment($table, $column = NULL) {
    $condition = $this->buildTableNameCondition($table);
    if (isset($column)) {
      $condition->condition('column_name', $column);
      $condition->compile($this->connection, $this);
      // Don't use {} around information_schema.columns table.
      return $this->connection->query("SELECT column_comment AS column_comment FROM information_schema.columns WHERE " . (string) $condition, $condition->arguments())->fetchField();
    }
    $condition->compile($this->connection, $this);
    // Don't use {} around information_schema.tables table.
    $comment = $this->connection->query("SELECT table_comment AS table_comment FROM information_schema.tables WHERE " . (string) $condition, $condition->arguments())->fetchField();
    // Work-around for MySQL 5.0 bug http://bugs.mysql.com/bug.php?id=11379
    return preg_replace('/; InnoDB free:.*$/', '', $comment);
  }

}

/**
 * @} End of "addtogroup schemaapi".
 */
