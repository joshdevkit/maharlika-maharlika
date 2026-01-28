<?php

namespace Maharlika\Database\Grammar;

use Maharlika\Database\Query\Builder;
use Maharlika\Database\RawExpression;

class Grammar
{
    protected string $tablePrefix = '';

    /**
     * The components that make up a select clause.
     *
     * @var array
     */
    protected array $selectComponents = [
        'columns',
        'from',
        'joins',
        'wheres',
        'groups',
        'havings',
        'orders',
        'limit',
        'offset',
    ];

    /**
     * Wrap a value in keyword identifiers (backticks for MySQL)
     */
    public function wrap(string|RawExpression $value): string
    {
        // Handle RawExpression - don't wrap it
        if ($value instanceof \Maharlika\Database\RawExpression) {
            return $value->getValue();
        }

        // If value contains *, don't wrap it
        if ($value === '*') {
            return $value;
        }

        // If already fully wrapped, return as is
        if (preg_match('/^`[^`]+`\.`[^`]+`$/', $value)) {
            return $value;
        }

        if (stripos($value, ' as ') !== false) {
            list($columnPart, $aliasPart) = explode(' as ', $value, 2);
            return $this->wrap(trim($columnPart)) . ' AS ' . $this->wrap(trim($aliasPart));
        }

        // Check for dot notation (table.column)
        $hasDot = strpos($value, '.') !== false;

        if ($hasDot) {
            $segments = explode('.', $value);
            $wrapped = [];
            foreach ($segments as $segment) {
                $wrappedSeg = $this->wrapSegment($segment);
                $wrapped[] = $wrappedSeg;
            }
            $result = implode('.', $wrapped);
            return $result;
        }

        $result = $this->wrapSegment($value);
        return $result;
    }


    /**
     * Wrap a single segment of a column/table name  
     */
    protected function wrapSegment(string $segment): string
    {
        // If already wrapped or is *, return as is
        if ($segment === '*') {
            return $segment;
        }

        // Check if already wrapped with backticks
        if (preg_match('/^`.*`$/', $segment)) {
            return $segment;
        }

        // Check if it's a function call (contains parentheses)
        if (strpos($segment, '(') !== false) {
            return $segment;
        }

        return '`' . str_replace('`', '``', $segment) . '`';
    }

    /**
     * Wrap an array of values
     */
    protected function wrapArray(array $values): array
    {
        return array_map(fn($value) => $this->wrap($value), $values);
    }

    /**
     * Compile a select query
     */
    public function compileSelect(Builder $query): string
    {
        if (empty($query->getColumns())) {
            $query->select(['*']);
        }

        return trim($this->concatenate($this->compileComponents($query)));
    }

    /**
     * Compile the components necessary for a select clause
     */
    protected function compileComponents(Builder $query): array
    {
        $sql = [];

        foreach ($this->selectComponents as $component) {
            // Always compile 'columns' and 'from' - they are essential
            if (in_array($component, ['columns', 'from'])) {
                $method = 'compile' . ucfirst($component);
                if (method_exists($this, $method)) {
                    $compiled = $this->$method($query);
                    if ($compiled !== '') {
                        $sql[$component] = $compiled;
                    }
                }
                continue;
            }

            // For other components, check if they have data
            $getter = 'get' . ucfirst($component);

            if (method_exists($query, $getter)) {
                $value = $query->$getter();

                // Only compile if there's actual data
                if (!is_null($value) && (!is_array($value) || !empty($value))) {
                    $method = 'compile' . ucfirst($component);

                    if (method_exists($this, $method)) {
                        $compiled = $this->$method($query);
                        if ($compiled !== '') {
                            $sql[$component] = $compiled;
                        }
                    }
                }
            }
        }

        return $sql;
    }

    /**
     * Concatenate an array of segments, removing empties
     */
    protected function concatenate(array $segments): string
    {
        return implode(' ', array_filter($segments, function ($value) {
            return (string) $value !== '';
        }));
    }

    /**
     * Compile the SELECT columns
     */
    protected function compileColumns(Builder $query): string
    {
        $columns = $query->getColumns();

        if (empty($columns) || $columns === ['*']) {
            $select = 'select';
            if ($query->isDistinct()) {
                $select .= ' distinct';
            }
            return $select . ' *';
        }

        // Handle DISTINCT
        $select = 'select';
        if ($query->isDistinct()) {
            $select .= ' distinct';
        }

        $wrapped = array_map(function ($column) {
            // Handle RawExpression
            if ($column instanceof \Maharlika\Database\RawExpression) {
                return $column->getValue();
            }
            return $this->wrap($column);
        }, $columns);

        return $select . ' ' . implode(', ', $wrapped);
    }
    /**
     * Compile the FROM clause
     */
    protected function compileFrom(Builder $query): string
    {
        $table = $query->getTable();

        if (!$table) {
            return '';
        }

        return 'from ' . $this->wrap($table);
    }

    /**
     * Compile the JOIN clauses
     * 
     * Note: For subqueries that will be used in EXISTS clauses,
     * we need to be careful with backticks as they can cause issues
     * with MySQL's correlated subquery resolution
     */
    protected function compileJoins(Builder $query): string
    {
        $joins = $query->getJoins();

        if (empty($joins)) {
            return '';
        }

        $sql = [];

        foreach ($joins as $join) {
            $type = strtolower($join['type']);

            if ($type === 'cross') {
                $sql[] = "cross join " . $this->wrap($join['table']);
            } else {
                $table = $this->wrap($join['table']);

                // For qualified columns (table.column), split and wrap each part
                $first = $this->wrapQualifiedColumn($join['first']);
                $second = $this->wrapQualifiedColumn($join['second']);

                $operator = $join['operator'];

                $sql[] = "{$type} join {$table} on {$first} {$operator} {$second}";
            }
        }

        return implode(' ', $sql);
    }

    /**
     * Wrap a qualified column name (table.column)
     */
    protected function wrapQualifiedColumn(string $column): string
    {
        if (strpos($column, '.') !== false) {
            [$table, $col] = explode('.', $column, 2);
            return $this->wrapSegment($table) . '.' . $this->wrapSegment($col);
        }

        return $this->wrapSegment($column);
    }

    /**
     * Compile the WHERE clauses
     */
    protected function compileWheres(Builder $query): string
    {
        $wheres = $query->getWheres();

        if (empty($wheres)) {
            return '';
        }

        $sql = $this->compileWheresToArray($query);

        if (count($sql) > 0) {
            return $this->concatenateWhereClauses($query, $sql);
        }

        return '';
    }

    /**
     * Get an array of all the where clauses for the query
     */
    protected function compileWheresToArray(Builder $query): array
    {
        $wheres = $query->getWheres();

        return array_map(function ($where, $index) {
            // Normalize the type for method name (convert snake_case to camelCase)
            $type = str_replace('_', '', ucwords($where['type'], '_'));
            $method = 'where' . $type;

            // Fallback if method doesn't exist
            if (!method_exists($this, $method)) {
                throw new \RuntimeException("Grammar method [{$method}] not found for where type [{$where['type']}]");
            }

            $clause = $this->$method($where);

            // For the first where clause, don't add the boolean
            if ($index === 0) {
                return $clause;
            }

            // For subsequent clauses, add the boolean (and/or)
            $boolean = strtolower($where['boolean']);
            return "{$boolean} {$clause}";
        }, $wheres, array_keys($wheres));
    }

    /**
     * Format the where clause statements into one string
     */
    protected function concatenateWhereClauses(Builder $query, array $sql): string
    {
        return 'where ' . implode(' ', $sql);
    }

    /**
     * Remove the leading boolean from a statement
     */
    protected function removeLeadingBoolean(string $value): string
    {
        return preg_replace('/and |or /i', '', $value, 1);
    }

    /**
     * Compile a basic WHERE clause
     */
    protected function whereBasic(array $where): string
    {
        return $this->wrap($where['column']) . " {$where['operator']} ?";
    }

    /**
     * Compile a WHERE IN clause
     */
    protected function whereIn(array $where): string
    {
        $placeholders = implode(', ', array_fill(0, count($where['values']), '?'));
        return $this->wrap($where['column']) . " in ({$placeholders})";
    }

    /**
     * Compile a WHERE NOT IN clause
     */
    protected function whereNotIn(array $where): string
    {
        $placeholders = implode(', ', array_fill(0, count($where['values']), '?'));
        return $this->wrap($where['column']) . " not in ({$placeholders})";
    }

    /**
     * Compile a WHERE NULL clause
     */
    protected function whereNull(array $where): string
    {
        return $this->wrap($where['column']) . " is null";
    }

    /**
     * Compile a WHERE NOT NULL clause
     */
    protected function whereNotNull(array $where): string
    {
        return $this->wrap($where['column']) . " is not null";
    }

    /**
     * Compile a WHERE BETWEEN clause
     */
    protected function whereBetween(array $where): string
    {
        return $this->wrap($where['column']) . " between ? and ?";
    }

    /**
     * Compile a WHERE NOT BETWEEN clause
     */
    protected function whereNotBetween(array $where): string
    {
        return $this->wrap($where['column']) . " not between ? and ?";
    }

    /**
     * Compile a WHERE ANY clause
     */
    protected function whereAny(array $where): string
    {
        $conditions = [];
        foreach ($where['columns'] as $column) {
            $conditions[] = $this->wrap($column) . " {$where['operator']} ?";
        }
        return '(' . implode(' or ', $conditions) . ')';
    }

    /**
     * Compile a WHERE ANY NULL clause
     */
    protected function whereAnyNull(array $where): string
    {
        $conditions = [];
        foreach ($where['columns'] as $column) {
            $conditions[] = $this->wrap($column) . " is null";
        }
        return '(' . implode(' or ', $conditions) . ')';
    }

    /**
     * Compile a WHERE ANY NOT NULL clause
     */
    protected function whereAnyNotNull(array $where): string
    {
        $conditions = [];
        foreach ($where['columns'] as $column) {
            $conditions[] = $this->wrap($where['column']) . " is not null";
        }
        return '(' . implode(' or ', $conditions) . ')';
    }

    /**
     * Compile a WHERE LIKE clause
     */
    protected function whereLike(array $where): string
    {
        $not = $where['not'] ?? false;
        $operator = $not ? 'not like' : 'like';
        return $this->wrap($where['column']) . " {$operator} ?";
    }

    /**
     * Compile a WHERE column comparison clause (whereColumn)
     */
    protected function whereColumn(array $where): string
    {
        return $this->wrap($where['first']) . " {$where['operator']} " . $this->wrap($where['second']);
    }

    /**
     * Compile a nested WHERE clause
     */
    /**
     * Compile a nested WHERE clause
     */
    protected function whereNested(array $where): string
    {
        $nestedQuery = $where['query'];
        $nestedWheres = $nestedQuery->getWheres();

        if (empty($nestedWheres)) {
            return '';
        }

        // Compile the nested wheres WITHOUT the 'where' keyword
        $sql = [];

        foreach ($nestedWheres as $index => $nestedWhere) {
            $type = str_replace('_', '', ucwords($nestedWhere['type'], '_'));
            $method = 'where' . $type;

            if (!method_exists($this, $method)) {
                throw new \RuntimeException("Grammar method [{$method}] not found for where type [{$nestedWhere['type']}]");
            }

            $clause = $this->$method($nestedWhere);

            // For the first clause in the nested group, don't add boolean
            if ($index === 0) {
                $sql[] = $clause;
            } else {
                // For subsequent clauses, add the boolean (and/or)
                $boolean = strtolower($nestedWhere['boolean']);
                $sql[] = "{$boolean} {$clause}";
            }
        }

        return '(' . implode(' ', $sql) . ')';
    }

    /**
     * Compile a raw WHERE clause
     */
    protected function whereRaw(array $where): string
    {
        return $where['sql'];
    }

    /**
     * Compile a WHERE DATE clause
     */
    protected function whereDate(array $where): string
    {
        return "date({$this->wrap($where['column'])}) {$where['operator']} ?";
    }

    /**
     * Compile a WHERE YEAR clause
     */
    protected function whereYear(array $where): string
    {
        return "year({$this->wrap($where['column'])}) {$where['operator']} ?";
    }

    /**
     * Compile a WHERE MONTH clause
     */
    protected function whereMonth(array $where): string
    {
        return "month({$this->wrap($where['column'])}) {$where['operator']} ?";
    }

    /**
     * Compile a WHERE DAY clause
     */
    protected function whereDay(array $where): string
    {
        return "day({$this->wrap($where['column'])}) {$where['operator']} ?";
    }

    /**
     * Compile a WHERE TIME clause
     */
    protected function whereTime(array $where): string
    {
        return "time({$this->wrap($where['column'])}) {$where['operator']} ?";
    }

    /**
     * Compile the ORDER BY clause
     */
    protected function compileOrders(Builder $query): string
    {
        $orders = $query->getOrders();

        if (empty($orders)) {
            return '';
        }

        return 'order by ' . implode(', ', $this->compileOrdersToArray($query, $orders));
    }

    /**
     * Compile the query orders to an array
     */
    protected function compileOrdersToArray(Builder $query, array $orders): array
    {
        $compiled = [];
        $seen = [];

        foreach ($orders as $order) {
            $direction = strtolower($order['direction']);
            $column = $this->wrap($order['column']);

            // Create a unique key to track duplicates
            $key = $column . '_' . $direction;

            // Skip if we've already seen this exact order clause
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $compiled[] = "{$column} {$direction}";
        }

        return $compiled;
    }

    /**
     * Compile the LIMIT clause
     */
    protected function compileLimit(Builder $query): string
    {
        return 'limit ' . (int) $query->getLimit();
    }

    /**
     * Compile the OFFSET clause
     */
    protected function compileOffset(Builder $query): string
    {
        return 'offset ' . (int) $query->getOffset();
    }

    /**
     * Compile an INSERT statement
     */
    public function compileInsert(Builder $query, array $data): string
    {
        $table = $this->wrap($query->getTable());

        // Handle batch inserts
        if (isset($data[0]) && is_array($data[0])) {
            $columns = array_keys($data[0]);
            $placeholders = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
            $values = implode(', ', array_fill(0, count($data), $placeholders));
        } else {
            $columns = array_keys($data);
            $values = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        }

        $wrappedColumns = array_map(fn($col) => $this->wrap($col), $columns);
        $columnString = implode(', ', $wrappedColumns);

        return "insert into {$table} ({$columnString}) values {$values}";
    }

    /**
     * Get bindings for INSERT statement
     */
    public function getInsertBindings(array $data): array
    {
        // Handle batch inserts
        if (isset($data[0]) && is_array($data[0])) {
            $bindings = [];
            foreach ($data as $row) {
                $bindings = array_merge($bindings, array_values($row));
            }
            return $bindings;
        }

        return array_values($data);
    }

    /**
     * Compile an UPDATE statement
     */
    public function compileUpdate(Builder $query, array $data): string
    {
        $table = $this->wrap($query->getTable());

        $sets = [];
        foreach (array_keys($data) as $column) {
            $sets[] = $this->wrap($column) . " = ?";
        }

        $sql = "update {$table} set " . implode(', ', $sets);

        // Add where clauses
        if (!empty($query->getWheres())) {
            $sql .= ' ' . $this->compileWheres($query);
        }

        return $sql;
    }

    /**
     * Compile a DELETE statement
     */
    public function compileDelete(Builder $query): string
    {
        $table = $this->wrap($query->getTable());

        $sql = "delete from {$table}";

        // Add where clauses
        if (!empty($query->getWheres())) {
            $sql .= ' ' . $this->compileWheres($query);
        }

        return $sql;
    }

    /**
     * Compile GROUP BY clause
     */
    protected function compileGroups(Builder $query): string
    {
        $groups = $query->getGroups();

        if (empty($groups)) {
            return '';
        }

        $wrapped = array_map(fn($group) => $this->wrap($group), $groups);
        return 'group by ' . implode(', ', $wrapped);
    }

    /**
     * Compile HAVING clause
     */
    protected function compileHavings(Builder $query): string
    {
        $havings = $query->getHavings();

        if (empty($havings)) {
            return '';
        }

        $sql = [];

        foreach ($havings as $index => $having) {
            $clause = $this->wrap($having['column']) . " {$having['operator']} ?";

            if ($index === 0) {
                $sql[] = 'having ' . $clause;
            } else {
                $boolean = strtolower($having['boolean']);
                $sql[] = "{$boolean} {$clause}";
            }
        }

        return implode(' ', $sql);
    }

    /**
     * Compile an INSERT IGNORE statement (MySQL specific)
     */
    public function compileInsertIgnore(Builder $query, array $data): string
    {
        $sql = $this->compileInsert($query, $data);
        return str_replace('insert into', 'insert ignore into', $sql);
    }

    /**
     * Compile an INSERT ON DUPLICATE KEY UPDATE statement (MySQL specific)
     */
    public function compileInsertOnDuplicateKeyUpdate(Builder $query, array $data, array $updateData): string
    {
        $insertSql = $this->compileInsert($query, $data);

        $updates = [];
        foreach (array_keys($updateData) as $column) {
            $updates[] = $this->wrap($column) . " = values({$this->wrap($column)})";
        }

        return $insertSql . ' on duplicate key update ' . implode(', ', $updates);
    }

    /**
     * Compile a TRUNCATE statement
     */
    public function compileTruncate(Builder $query): string
    {
        return 'truncate table ' . $this->wrap($query->getTable());
    }

    /**
     * Compile an UPDATE with JOIN statement
     */
    public function compileUpdateWithJoins(Builder $query, array $data): string
    {
        $table = $this->wrap($query->getTable());

        $sets = [];
        foreach (array_keys($data) as $column) {
            $sets[] = "{$table}.{$this->wrap($column)} = ?";
        }

        $sql = "update {$table}";

        // Add joins
        if (!empty($query->getJoins())) {
            $sql .= ' ' . $this->compileJoins($query);
        }

        $sql .= ' set ' . implode(', ', $sets);

        // Add where clauses
        if (!empty($query->getWheres())) {
            $sql .= ' ' . $this->compileWheres($query);
        }

        return $sql;
    }

    /**
     * Compile a DELETE with JOIN statement
     */
    public function compileDeleteWithJoins(Builder $query): string
    {
        $table = $this->wrap($query->getTable());

        $sql = "delete {$table} from {$table}";

        // Add joins
        if (!empty($query->getJoins())) {
            $sql .= ' ' . $this->compileJoins($query);
        }

        // Add where clauses
        if (!empty($query->getWheres())) {
            $sql .= ' ' . $this->compileWheres($query);
        }

        return $sql;
    }

    /**
     * Compile a UNION statement
     */
    public function compileUnion(array $unions): string
    {
        $sql = [];

        foreach ($unions as $union) {
            $keyword = $union['all'] ? 'union all' : 'union';
            $sql[] = $keyword . ' ' . $union['query'];
        }

        return implode(' ', $sql);
    }

    /**
     * Compile DISTINCT
     */
    protected function compileDistinct(Builder $query): string
    {
        return $query->isDistinct() ? 'distinct ' : '';
    }
}
