<?php

namespace App\baseClasses;

use Illuminate\Support\Collection;

defined('ABSPATH') or die('Something went wrong');
class KCQueryBuilder
{
    private $wpdb;
    private string $modelClass;
    private array $schema;
    private string $table;
    private array $wheres = [];
    private array $orders = [];
    private ?int $limitValue = null;
    private ?int $offsetValue = null;
    private array $joinClauses = [];
    private array $selectColumns = ['*'];
    private array $params = [];
    private ?string $fromTable = null;
    private array $groupByColumns = [];

    // Table alias when needed
    private ?string $tableAlias = null;

    public function __construct(string $modelClass)
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->modelClass = $modelClass;
        $this->schema = $modelClass::getSchema();
        $this->table = empty($this->schema['table_name']) ? $this->schema['user_table_name'] : ($wpdb->prefix . $this->schema['table_name']);
    }

    /**
     * Specify the columns to select
     * 
     * @param array $columns The columns to select
     * @return self
     */
    public function select(array $columns): self
    {
        $this->selectColumns = array_merge(array_diff($this->selectColumns, ['*']), $columns);
        return $this;
    }

    /**
     * Specify a table for the FROM clause
     * 
     * @param string $table The table name
     * @return self
     */
    public function from(string $table): self
    {
        $this->fromTable = $table;
        return $this;
    }

    /**
     * Where In clause
     * 
     * @param string $column The column to check
     * @param array $values The values to match against
     * @return self
     */
    public function whereIn(string $column, array $values): self
    {
        if (empty($values)) {
            // If no values provided, add a condition that will always be false
            return $this->where('1', '=', '0');
        }

        $placeholders = implode(', ', array_fill(0, count($values), '%s'));
        $column = $this->getColumnNameFromProperty($column);

        $this->wheres[] = [
            'raw' => true,
            'sql' => "{$column} IN ({$placeholders})",
            'values' => $values,
            'type' => 'AND'
        ];

        return $this;
    }

    /**
     * Group results by a column
     * 
     * @param string|array $columns Column or columns to group by
     * @return self
     */
    public function groupBy($columns): self
    {
        if (!property_exists($this, 'groupByColumns')) {
            $this->groupByColumns = [];
        }

        if (is_array($columns)) {
            $this->groupByColumns = array_merge($this->groupByColumns, $columns);
        } else {
            $this->groupByColumns[] = $columns;
        }

        return $this;
    }

    /**
     * Or Where In clause
     * 
     * @param string $column The column to check
     * @param array $values The values to match against
     * @return self
     */
    public function orWhereIn(string $column, array $values): self
    {
        if (empty($values)) {
            // If no values provided, add a condition that will always be false
            return $this->orWhere('1', '=', '0');
        }

        $placeholders = implode(', ', array_fill(0, count($values), '%s'));
        $column = $this->getColumnNameFromProperty($column);

        $this->wheres[] = [
            'raw' => true,
            'sql' => "{$column} IN ({$placeholders})",
            'values' => $values,
            'type' => 'OR'
        ];

        return $this;
    }

    /**
     * Where Null clause
     * 
     * @param string $column The column to check for null
     * @return self
     */
    public function whereNull(string $column): self
    {
        $column = $this->getColumnNameFromProperty($column);

        $this->wheres[] = [
            'raw' => true,
            'sql' => "{$column} IS NULL",
            'values' => [],
            'type' => 'AND'
        ];

        return $this;
    }

    /**
     * Where Not Null clause
     * 
     * @param string $column The column to check for not null
     * @return self
     */
    public function whereNotNull(string $column): self
    {
        $column = $this->getColumnNameFromProperty($column);

        $this->wheres[] = [
            'raw' => true,
            'sql' => "{$column} IS NOT NULL",
            'values' => [],
            'type' => 'AND'
        ];

        return $this;
    }

    public function whereNotIn(string $column, array $values): self
    {
        if (empty($values)) {
            // If no values provided, add a condition that will always be true
            return $this->where('1', '=', '1');
        }

        $placeholders = implode(', ', array_fill(0, count($values), '%s'));
        $column = $this->getColumnNameFromProperty($column);

        $this->wheres[] = [
            'raw' => true,
            'sql' => "{$column} NOT IN ({$placeholders})",
            'values' => $values,
            'type' => 'AND'
        ];

        return $this;
    }

    /**
     * Or Where Null clause
     * 
     * @param string $column The column to check for null
     * @return self
     */
    public function orWhereNull(string $column): self
    {
        $column = $this->getColumnNameFromProperty($column);

        $this->wheres[] = [
            'raw' => true,
            'sql' => "{$column} IS NULL",
            'values' => [],
            'type' => 'OR'
        ];

        return $this;
    }

    /**
     * Or Where Not Null clause
     * 
     * @param string $column The column to check for not null
     * @return self
     */
    public function orWhereNotNull(string $column): self
    {
        $column = $this->getColumnNameFromProperty($column);

        $this->wheres[] = [
            'raw' => true,
            'sql' => "{$column} IS NOT NULL",
            'values' => [],
            'type' => 'OR'
        ];

        return $this;
    }
    /**
     * Order results
     */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orders[] = [
            'column' => $this->getColumnNameFromProperty($column),
            'direction' => in_array(strtoupper($direction), ['ASC', 'DESC']) ? strtoupper($direction) : 'ASC'
        ];

        return $this;
    }

    /**
     * Limit results
     */
    public function limit(int $limit): self
    {
        $this->limitValue = max(1, $limit);
        return $this;
    }

    /**
     * Offset results
     */
    public function offset(int $offset): self
    {
        $this->offsetValue = max(0, $offset);
        return $this;
    }
    /**
     * Join another table using model class
     *
     * @param string|object $model The model class or instance to join with
     * @param string|callable $firstColumn The column from the first table or a callback for complex conditions
     * @param string|null $operator The operator for the join condition (optional when using callback)
     * @param string|null $secondColumn The column from the second table (optional when using callback)
     * @param string|null $alias Optional alias for the joined table
     * @return self
     */
    public function join($model, $firstColumn, $operator = null, $secondColumn = null, ?string $alias = null): self
    {
        $tableName = $this->getTableNameFromModel($model);
        $tableAlias = $alias ?: $this->generateTableAlias($tableName);

        // Handle callback for complex join conditions
        if (is_callable($firstColumn)) {
            $joinBuilder = new KCJoinConditionBuilder();
            call_user_func($firstColumn, $joinBuilder);

            $this->joinClauses[] = [
                'type' => 'INNER JOIN',
                'table' => $tableName,
                'alias' => $tableAlias,
                'conditions' => $joinBuilder->getConditions(),
                'model' => $model
            ];
        } else {
            // Standard single condition join
            $this->joinClauses[] = [
                'type' => 'INNER JOIN',
                'table' => $tableName,
                'alias' => $tableAlias,
                'firstColumn' => $firstColumn,
                'operator' => $operator,
                'secondColumn' => $secondColumn,
                'model' => $model
            ];
        }

        return $this;
    }

    /**
     * Left join with another table using model class
     *
     * @param string|object $model The model class or instance to join with
     * @param string|callable $firstColumn The column from the first table or a callback for complex conditions
     * @param string|null $operator The operator for the join condition (optional when using callback)
     * @param string|null $secondColumn The column from the second table (optional when using callback)
     * @param string|null $alias Optional alias for the joined table
     * @return self
     */
    public function leftJoin($model, $firstColumn, $operator = null, $secondColumn = null, ?string $alias = null): self
    {
        $tableName = $this->getTableNameFromModel($model);
        $tableAlias = $alias ?: $this->generateTableAlias($tableName);

        // Handle callback for complex join conditions
        if (is_callable($firstColumn)) {
            $joinBuilder = new KCJoinConditionBuilder();
            call_user_func($firstColumn, $joinBuilder);

            $this->joinClauses[] = [
                'type' => 'LEFT JOIN',
                'table' => $tableName,
                'alias' => $tableAlias,
                'conditions' => $joinBuilder->getConditions(),
                'model' => $model
            ];
        } else {
            // Standard single condition join
            $this->joinClauses[] = [
                'type' => 'LEFT JOIN',
                'table' => $tableName,
                'alias' => $tableAlias,
                'firstColumn' => $firstColumn,
                'operator' => $operator,
                'secondColumn' => $secondColumn,
                'model' => $model
            ];
        }

        return $this;
    }

    /**
     * Right join with another table using model class
     *
     * @param string|object $model The model class or instance to join with
     * @param string|callable $firstColumn The column from the first table or a callback for complex conditions
     * @param string|null $operator The operator for the join condition (optional when using callback)
     * @param string|null $secondColumn The column from the second table (optional when using callback)
     * @param string|null $alias Optional alias for the joined table
     * @return self
     */
    public function rightJoin($model, $firstColumn, $operator = null, $secondColumn = null, ?string $alias = null): self
    {
        $tableName = $this->getTableNameFromModel($model);
        $tableAlias = $alias ?: $this->generateTableAlias($tableName);

        // Handle callback for complex join conditions
        if (is_callable($firstColumn)) {
            $joinBuilder = new KCJoinConditionBuilder();
            call_user_func($firstColumn, $joinBuilder);

            $this->joinClauses[] = [
                'type' => 'RIGHT JOIN',
                'table' => $tableName,
                'alias' => $tableAlias,
                'conditions' => $joinBuilder->getConditions(),
                'model' => $model
            ];
        } else {
            // Standard single condition join
            $this->joinClauses[] = [
                'type' => 'RIGHT JOIN',
                'table' => $tableName,
                'alias' => $tableAlias,
                'firstColumn' => $firstColumn,
                'operator' => $operator,
                'secondColumn' => $secondColumn,
                'model' => $model
            ];
        }

        return $this;
    }

    /**
     * Set a custom table alias for the query
     * 
     * @param string $alias The table alias to use
     * @return self
     */
    public function setTableAlias(string $alias): self
    {
        $this->tableAlias = $alias;
        return $this;
    }

    /**
     * Get the table reference (with alias if set)
     * 
     * @return string
     */
    private function getTableReference(): string
    {
        if ($this->tableAlias) {
            return "{$this->table} AS {$this->tableAlias}";
        }
        return $this->table;
    }
    /**
     * Build the SELECT query
     */
    private function buildSelectQuery(): string
    {
        // Don't reset params here - keep the existing ones
        $queryParams = []; // Use a separate array for building this query

        // If a from table is specified, use it instead of the model's table
        $tableRef = $this->fromTable ?: $this->getTableReference();

        $query = "SELECT " . implode(', ', $this->selectColumns) . " FROM {$tableRef}";

        // Add joins
        foreach ($this->joinClauses as $join) {
            if (isset($join['conditions'])) {
                // New style join with multiple conditions
                $query .= " {$join['type']} {$join['table']} AS {$join['alias']} ON ";
                $query .= $this->buildJoinConditions($join['conditions']);
            } elseif (isset($join['alias'])) {
                // Single condition join with alias
                $query .= " {$join['type']} {$join['table']} AS {$join['alias']} ON {$join['firstColumn']} {$join['operator']} {$join['secondColumn']}";
            } else {
                // Legacy join without alias
                $query .= " {$join['type']} {$join['table']} ON {$join['firstColumn']} {$join['operator']} {$join['secondColumn']}";
            }
        }

        // Add where conditions
        if (!empty($this->wheres)) {
            $query .= " WHERE ";
            $whereClause = $this->buildWhereClauseForQuery($this->wheres, $queryParams);
            $query .= $whereClause;
        }

        // Add group by clause
        if (!empty($this->groupByColumns)) {
            $query .= " GROUP BY " . implode(', ', $this->groupByColumns);
        }


        // Add order by
        if (!empty($this->orders)) {
            $orderStatements = [];
            foreach ($this->orders as $order) {
                $orderStatements[] = "{$order['column']} {$order['direction']}";
            }
            $query .= " ORDER BY " . implode(', ', $orderStatements);
        }

        // Add limit and offset
        if ($this->limitValue !== null) {
            $query .= " LIMIT %d";
            $queryParams[] = $this->limitValue;

            if ($this->offsetValue !== null) {
                $query .= " OFFSET %d";
                $queryParams[] = $this->offsetValue;
            }
        }

        // Update the main params array with our query params
        $this->params = $queryParams;

        return $query;
    }

    /**
     * Build join conditions string from conditions array
     *
     * @param array $conditions Array of join conditions
     * @return string Built join conditions
     */
    private function buildJoinConditions(array $conditions): string
    {
        if (empty($conditions)) {
            return '1=1';
        }

        $clauses = [];
        $first = true;

        foreach ($conditions as $condition) {
            $prefix = $first ? '' : ' ' . $condition['type'] . ' ';

            if (isset($condition['raw']) && $condition['raw']) {
                $clauses[] = $prefix . $condition['sql'];
            } else {
                $clauses[] = $prefix . "{$condition['firstColumn']} {$condition['operator']} {$condition['secondColumn']}";
            }

            $first = false;
        }

        return implode('', $clauses);
    }

    /**
     * Collect parameters from where conditions recursively
     *
     * @param array $wheres Array of where conditions
     * @param array &$params Reference to params array to populate
     * @return void
     */
    private function collectWhereParams(array $wheres, array &$params): void
    {
        foreach ($wheres as $where) {
            if (isset($where['raw']) && $where['raw']) {
                // For raw SQL, add any values
                if (isset($where['values']) && is_array($where['values'])) {
                    $params = array_merge($params, $where['values']);
                }
            } else {
                // For standard where, add the value
                if (isset($where['value'])) {
                    $params[] = $where['value'];
                }
            }
        }
    }

    /**
     * Build a WHERE clause from an array of conditions for query building
     *
     * @param array $wheres Array of where conditions
     * @param array &$params Reference to params array to populate
     * @return string Built WHERE clause without the "WHERE" keyword
     */
    private function buildWhereClauseForQuery(array $wheres, array &$params): string
    {
        if (empty($wheres)) {
            return '1=1'; // Default true condition
        }

        $clauses = [];
        $first = true;

        foreach ($wheres as $where) {
            $prefix = $first ? '' : ' ' . $where['type'] . ' ';

            if (isset($where['raw']) && $where['raw']) {
                $clauses[] = $prefix . $where['sql'];
                // Add any values from raw SQL
                if (isset($where['values']) && is_array($where['values'])) {
                    $params = array_merge($params, $where['values']);
                }
            } else {
                $clauses[] = $prefix . "{$where['column']} {$where['operator']} %s";
                // Add the parameter value to params array
                $params[] = $where['value'];
            }

            $first = false;
        }

        return implode('', $clauses);
    }

    /**
     * Build a WHERE clause from an array of conditions (for nested queries)
     *
     * @param array $wheres Array of where conditions
     * @return string Built WHERE clause without the "WHERE" keyword
     */
    private function buildWhereClause(array $wheres): string
    {
        if (empty($wheres)) {
            return '1=1'; // Default true condition
        }

        $clauses = [];
        $first = true;

        foreach ($wheres as $where) {
            $prefix = $first ? '' : ' ' . $where['type'] . ' ';

            if (isset($where['raw']) && $where['raw']) {
                $clauses[] = $prefix . $where['sql'];
            } else {
                $clauses[] = $prefix . "{$where['column']} {$where['operator']} %s";
            }

            $first = false;
        }

        return implode('', $clauses);
    }

    /**
     * Add a raw WHERE condition to the query
     *
     * @param string $sql Raw SQL condition
     * @param array $params Parameters to bind to the condition
     * @return self
     */
    public function whereRaw(string $sql, array $params = []): self
    {
        $this->wheres[] = [
            'raw' => true,
            'sql' => $sql,
            'values' => $params,
            'type' => 'AND'
        ];

        // Add the values to our params array
        foreach ($params as $param) {
            $this->params[] = $param;
        }

        return $this;
    }

    /**
     * Add a raw OR WHERE condition to the query
     *
     * @param string $sql Raw SQL condition
     * @param array $params Parameters to bind to the condition
     * @return self
     */
    public function orWhereRaw(string $sql, array $params = []): self
    {
        $this->wheres[] = [
            'raw' => true,
            'sql' => $sql,
            'values' => $params,
            'type' => 'OR'
        ];

        // Add the values to our params array
        foreach ($params as $param) {
            $this->params[] = $param;
        }

        return $this;
    }

    /**
     * Add a where condition to the query
     *
     * @param string|callable $column Column name or callback function
     * @param string|null $operator Operator for comparison
     * @param mixed|null $value Value to compare against
     * @return self
     */
    public function where($column, $operator = null, $value = null): self
    {
        // Handle callback (closure) for grouped conditions
        if (is_callable($column)) {
            $nestedQuery = new self($this->modelClass);

            // Copy table alias and joins to nested query to maintain context
            $nestedQuery->tableAlias = $this->tableAlias;
            $nestedQuery->joinClauses = $this->joinClauses;

            // Pass the nested query to the callback
            call_user_func($column, $nestedQuery);

            // Get the where conditions from the nested query
            $nestedWheres = $nestedQuery->wheres;

            if (!empty($nestedWheres)) {
                // Collect parameters from nested where conditions
                $nestedParams = [];
                $this->collectWhereParams($nestedWheres, $nestedParams);

                // Build the nested WHERE clause (without adding to params yet)
                $nestedSql = $this->buildWhereClause($nestedWheres);

                // Add a raw where with the nested conditions
                $this->wheres[] = [
                    'raw' => true,
                    'sql' => '(' . $nestedSql . ')',
                    'values' => $nestedParams, // Store the collected nested params
                    'type' => 'AND'
                ];
            }

            return $this;
        }

        // Standard where clause handling
        if ($value === null && $operator !== null) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'column' => $this->getColumnNameFromProperty($column),
            'operator' => $operator,
            'value' => $value,
            'type' => 'AND'
        ];

        return $this;
    }

    /**
     * Get the column name from a property
     *
     * @param string $property The property name
     * @return string The column name
     */
    public function when($condition, $callback, $default = null): self
    {
        if ($condition) {
            call_user_func($callback, $this);
        } elseif ($default) {
            call_user_func($default, $this);
        }

        return $this;
    }
    /**
     * Add an OR where condition
     *
     * @param string|callable $column Column name or callback function
     * @param string|null $operator Operator for comparison
     * @param mixed|null $value Value to compare against
     * @return self
     */
    public function orWhere($column, $operator = null, $value = null): self
    {
        // Handle callback (closure) for grouped conditions
        if (is_callable($column)) {
            $nestedQuery = new self($this->modelClass);

            // Copy table alias and joins to nested query to maintain context
            $nestedQuery->tableAlias = $this->tableAlias;
            $nestedQuery->joinClauses = $this->joinClauses;

            // Pass the nested query to the callback
            call_user_func($column, $nestedQuery);

            // Get the where conditions from the nested query
            $nestedWheres = $nestedQuery->wheres;

            if (!empty($nestedWheres)) {
                // Collect parameters from nested where conditions
                $nestedParams = [];
                $this->collectWhereParams($nestedWheres, $nestedParams);

                // Build the nested WHERE clause (without adding to params yet)
                $nestedSql = $this->buildWhereClause($nestedWheres);

                // Add a raw where with the nested conditions
                $this->wheres[] = [
                    'raw' => true,
                    'sql' => '(' . $nestedSql . ')',
                    'values' => $nestedParams, // Store the collected nested params
                    'type' => 'OR'
                ];
            }

            return $this;
        }

        // Standard orWhere clause handling
        if ($value === null && $operator !== null) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'column' => $this->getColumnNameFromProperty($column),
            'operator' => $operator,
            'value' => $value,
            'type' => 'OR'
        ];

        return $this;
    }

    /**
     * Build and execute SELECT query
     */
    public function get(): Collection
    {
        // Global Filter: Ensure Patients have valid emails at the query level
        if ($this->modelClass === 'App\models\KCPatient')  {
            // Check if email filter is already set; if not, chain the requirements
            if (!array_filter($this->wheres, fn($w) => ($w['column'] ?? '') === 'user_email')) {
                $this->whereNotNull('email')
                    ->where('email', '!=', '')
                    ->where('email', 'LIKE', '%@%');
            }
        }

        $query = $this->buildSelectQuery();
        if (empty($this->params)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $results = $this->wpdb->get_results($query, ARRAY_A);
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $results = $this->wpdb->get_results( $this->wpdb->prepare($query, $this->params ?? []), ARRAY_A );
        }
        if ($this->wpdb->last_error) {
            KCErrorLogger::instance()->error("WPDB Error: " . $this->wpdb->last_error);
        }

        // HYDRATE MODELS
        return collect(array_map(function ($result) {
            return $this->hydrateModel($result);
        }, $results ?: []));
    }

    /**
     * Build and execute SELECT query for a single record
     */
    public function first()
    {
        $this->limit(1);
        $results = $this->get();

        return $results[0] ?? null;
    }


    public function preparedSql(): string
    {
        $query = $this->buildSelectQuery();
        if (empty($this->params)) {
            return $query;
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            return $this->wpdb->prepare($query, $this->params);
        }
    }

    /**
     * Find by primary key
     */
    public function find($id)
    {
        return $this->where($this->schema['primary_key'], $id)->first();
    }

    /**
     * Get the count of records matching the current query
     */
    public function count(): int
    {
        // Save current select columns and create a clone to avoid modifying the original
        $originalSelect = $this->selectColumns;
        $originalParams = $this->params;

        // Change select to COUNT(*)
        $this->selectColumns = ['COUNT(*) as count'];

        // Build query
        $query = $this->buildSelectQuery();

        // Execute query
        if (empty($this->params)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $result = $this->wpdb->get_var($query);
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $preparedQuery = $this->wpdb->prepare($query, $this->params);
            if (empty($preparedQuery)) {
                $result = 0;
            } else {
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                $result = $this->wpdb->get_var($preparedQuery);
            }
        }

        // Restore original select columns and params
        $this->selectColumns = $originalSelect;
        $this->params = $originalParams;

        return (int) $result;
    }

    /**
     * Create a new record
     */
    public function create(array $data)
    {
        $model = new $this->modelClass();

        foreach ($data as $property => $value) {
            $model->$property = $value;
        }

        return $model->save();
    }

    /**
     * Update records
     */
    public function update(array $data): int
    {
        $columns = [];
        $values = [];

        foreach ($data as $property => $value) {
            if (isset($this->schema['columns'][$property])) {
                $column = $this->schema['columns'][$property]['column'];
                $type = $this->schema['columns'][$property]['type'];

                $columns[] = "`$column` = %s";

                // Apply sanitizers
                if (!empty($this->schema['columns'][$property]['sanitizers'])) {
                    foreach ($this->schema['columns'][$property]['sanitizers'] as $sanitizer) {
                        $value = $sanitizer($value);
                    }
                }

                // Format value based on type
                $values[] = $this->formatValueForDatabase($value, $type);
            }
        }

        if (empty($columns)) {
            return 0;
        }

        // Add updated_at timestamp
        if ($this->schema['timestamps']) {
            $columns[] = "updated_at = %s";
            $values[] = current_time('mysql');
        }

        $query = "UPDATE {$this->table} SET " . implode(', ', $columns);

        // Add where conditions
        if (!empty($this->wheres)) {
            $query .= " WHERE ";
            $first = true;

            foreach ($this->wheres as $where) {
                if (!$first) {
                    $query .= " {$where['type']} ";
                }

                $query .= "{$where['column']} {$where['operator']} %s";
                $values[] = $where['value'];
                $first = false;
            }
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $preparedQuery = $this->wpdb->prepare($query, $values);
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        return $this->wpdb->query($preparedQuery);
    }

    /**
     * Delete records
     */
    public function delete(): int
    {
        // Use soft deletes if enabled
        if ($this->schema['soft_deletes']) {
            return $this->update(['deleted_at' => current_time('mysql')]);
        }

        $query = "DELETE FROM {$this->table}";
        $values = [];

        // Add where conditions
        if (!empty($this->wheres)) {
            $query .= " WHERE ";
            $first = true;
            foreach ($this->wheres as $where) {
                if (!$first) {
                    $query .= " {$where['type']} ";
                }
                if (isset($where['sql'])) {
                    $query .= $where['sql'];
                    $values[] = implode(',', $where['values']);
                } else {
                    $query .= "{$where['column']} {$where['operator']} %s";
                    $values[] = $where['value'];
                }
                $first = false;
            }
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $preparedQuery = $this->wpdb->prepare($query, $values);
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        return $this->wpdb->query($preparedQuery);
    }

    /**
     * Helper to get DB column name from property name
     */
    private function getColumnNameFromProperty(string $property): string
    {
        if (isset($this->schema['columns'][$property])) {
            return $this->schema['columns'][$property]['column'];
        }

        return $property;
    }

    /**
     * Format value based on type for DB storage
     */
    private function formatValueForDatabase($value, string $type)
    {
        switch ($type) {
            case 'int':
                return (int) $value;
            case 'float':
                return (float) $value;
            case 'bool':
                return (bool) $value ? 1 : 0;
            case 'datetime':
                return $value; // Assuming already in MySQL format
            case 'json':
                return json_encode($value);
            case 'string':
            default:
                return $value;
        }
    }

    /**
     * Hydrate a model from database result
     */
    private function hydrateModel(array $data)
    {
        $model = new $this->modelClass();
        $schema = $this->modelClass::getSchema();
        $columnToProperty = array_flip(array_map(function ($prop) {
            return $prop['column'];
        }, $schema['columns']));

        foreach ($data as $column => $value) {
            try {
                // For direct model columns/properties
                if (isset($columnToProperty[$column])) {
                    $property = $columnToProperty[$column];
                    $type = $schema['columns'][$property]['type'];

                    // This assignment triggers KCBaseModel::__set and its validators
                    $model->$property = $this->formatValueFromDatabase($value, $type);
                } else {
                    // For joined columns, store directly as properties on the model
                    $model->$column = $value;
                }
            } catch (\InvalidArgumentException $e) {
                // Skip invalid values to prevent a 500 error
                continue;
            }
        }

        return $model;
    }

    /**
     * Format value from database to PHP type
     */
    private function formatValueFromDatabase($value, string $type)
    {
        if ($value === null) {
            return null;
        }

        switch ($type) {
            case 'int':
                return (int) $value;
            case 'float':
                return (float) $value;
            case 'bool':
                return (bool) $value;
            case 'datetime':
                return $value; // Keep as MySQL datetime format
            case 'json':
                return json_decode($value, true);
            case 'string':
            default:
                return $value;
        }
    }

    /**
     * Helper method to get table name from a model class or instance
     *
     * @param string|object $model The model class name or instance
     * @return string The full table name with prefix
     */
    private function getTableNameFromModel($model): string
    {
        global $wpdb;

        // If it's a string (class name)
        if (is_string($model)) {
            if (class_exists($model)) {
                return !empty($model::getSchema()['table_name']) ? ($wpdb->prefix . $model::getSchema()['table_name']) : $model::getSchema()['user_table_name'];
            }

            // If it's not a class, assume it's already a table name
            return $wpdb->prefix . $model;
        }

        // If it's an object (model instance)
        if (is_object($model) && method_exists($model, 'getSchema')) {
            $class = get_class($model);
            return !empty($class::getSchema()['table_name']) ? ($wpdb->prefix . $class::getSchema()['table_name']) : $class::getSchema()['user_table_name'];
        }

        // Fallback - treat as raw table name
        return is_string($model) ? $wpdb->prefix . $model : '';
    }

    /**
     * Generate a table alias from table name
     *
     * @param string $tableName The full table name
     * @return string A short alias
     */
    private function generateTableAlias(string $tableName): string
    {
        // Extract the base name without prefix
        $baseName = preg_replace('/^' . preg_quote($this->wpdb->prefix, '/') . '/', '', $tableName);

        // Generate a simple alias using the first character of each word
        preg_match_all('/\b\w/', $baseName, $matches);
        $alias = implode('', $matches[0]);

        // Make sure we have at least one character
        return !empty($alias) ? $alias : substr($baseName, 0, 1);
    }
    public function removeGroupBy()
    {
        $this->groupByColumns = [];
        return $this;
    }
    public function countDistinct($column)
    {
        $this->selectColumns = ["COUNT(DISTINCT {$column}) as total"];
        $result = $this->first();
        return isset($result->total) ? (int) $result->total : 0;
    }


    /**
     * Where Between clause
     *
     * @param string $column The column to check
     * @param array $values Array with two values: [start, end]
     * @return self
     */
    public function whereBetween(string $column, array $values): self
    {
        if (count($values) !== 2) {
            // Invalid usage, ignore
            return $this;
        }
        $column = $this->getColumnNameFromProperty($column);
        $this->wheres[] = [
            'raw' => true,
            'sql' => "{$column} BETWEEN %s AND %s",
            'values' => [$values[0], $values[1]],
            'type' => 'AND'
        ];
        return $this;
    }

    /**
     * Or Where Between clause
     *
     * @param string $column The column to check
     * @param array $values Array with two values: [start, end]
     * @return self
     */
    public function orWhereBetween(string $column, array $values): self
    {
        if (count($values) !== 2) {
            // Invalid usage, ignore
            return $this;
        }
        $column = $this->getColumnNameFromProperty($column);
        $this->wheres[] = [
            'raw' => true,
            'sql' => "{$column} BETWEEN %s AND %s",
            'values' => [$values[0], $values[1]],
            'type' => 'OR'
        ];
        return $this;
    }

}
