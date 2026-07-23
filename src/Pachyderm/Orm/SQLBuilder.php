<?php

namespace Pachyderm\Orm;

use Pachyderm\Orm\Relation\BelongsToMap;

class SQLBuilder
{
    protected const IDENTIFIER_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/';

    protected const ALLOWED_AGGREGATES = ['MAX', 'MIN', 'COUNT', 'SUM', 'AVG'];

    protected const ALLOWED_OPERATORS = [
        'AND',
        'OR',
        '=',
        '!=',
        '<>',
        '<',
        '>',
        '<=',
        '>=',
        'LIKE',
        'NOT LIKE',
        'IN',
        'NOT IN',
        'IS NULL',
        'IS NOT NULL',
        'EXISTS',
        'NOT EXISTS',
        'COLUMN',
    ];

    protected const COLUMN_OPERATORS = ['=', '!=', '<>', '<', '>', '<=', '>='];

    protected string $_table;
    protected Model|null $_model;

    protected array $_fields = [];
    protected array $_joins = [];
    protected QueryBuilder $_filters;
    protected QueryBuilder $_havings;
    protected array $_values = [];
    protected array $_orders = [];
    /** @var list<string> */
    protected array $_groups = [];
    protected bool $_hasAggregate = false;
    protected int $_offset = 0;
    protected int $_limit = -1;

    /**
     * Eager BelongsTo relations registered via with().
     * @var array<string, array{class: class-string<Model>, prefix: string}>
     */
    protected array $_eagerRelations = [];

    public function __construct(string|Model $table)
    {
        $this->_filters = new QueryBuilder();
        $this->_havings = new QueryBuilder();

        if (is_string($table)) {
            $this->assertIdentifier($table);
            $this->_table = $table;
            $this->_filters->prepend($this->_table);
            $this->_model = NULL;
            return;
        }

        if ($table instanceof Model) {
            $this->assertIdentifier($table->table);
            $this->_table = $table->table;
            $this->_filters->prepend($this->_table);
            $this->_model = $table;
            return;
        }
    }

    /**
     * Validate a SQL identifier (column or table.column).
     *
     * @throws \InvalidArgumentException
     */
    public static function assertIdentifier(string $identifier): void
    {
        if (!preg_match(self::IDENTIFIER_PATTERN, $identifier)) {
            throw new \InvalidArgumentException('Invalid SQL identifier: ' . $identifier);
        }
    }

    /**
     * Normalize and validate ORDER BY direction.
     *
     * @throws \InvalidArgumentException
     */
    public static function assertOrderDirection(string $direction): string
    {
        $normalized = strtoupper(trim($direction));
        if ($normalized !== 'ASC' && $normalized !== 'DESC') {
            throw new \InvalidArgumentException('Invalid ORDER BY direction: ' . $direction);
        }
        return $normalized;
    }

    /**
     * Validate a WHERE/JOIN operator.
     *
     * @throws \InvalidArgumentException
     */
    public static function assertOperator(string $operator): void
    {
        if (!in_array($operator, self::ALLOWED_OPERATORS, true)) {
            throw new \InvalidArgumentException('Invalid SQL operator: ' . $operator);
        }
    }

    /**
     * Set fields to retrieve.
     */
    public function select(...$fields): SQLBuilder
    {
        foreach ($fields as $f) {
            if (is_array($f)) {
                foreach ($f as $f2) {
                    $this->assertIdentifier($f2);
                    $this->_fields[$f2] = $this->_table . '.' . $f2;
                }
                continue;
            }
            $this->assertIdentifier($f);
            $this->_fields[$f] = $this->_table . '.' . $f;
        }
        return $this;
    }

    /**
     * Select a qualified column under an output alias (for JOIN hydration).
     */
    public function selectAs(string $qualifiedColumn, string $alias): SQLBuilder
    {
        $this->assertIdentifier($qualifiedColumn);
        $this->assertIdentifier($alias);
        $this->_fields[$alias] = ['expr' => $qualifiedColumn, 'as' => $alias];
        return $this;
    }

    public function selectMax(string $column, ?string $alias = null): SQLBuilder
    {
        return $this->selectAggregate('MAX', $column, $alias);
    }

    public function selectMin(string $column, ?string $alias = null): SQLBuilder
    {
        return $this->selectAggregate('MIN', $column, $alias);
    }

    public function selectSum(string $column, ?string $alias = null): SQLBuilder
    {
        return $this->selectAggregate('SUM', $column, $alias);
    }

    public function selectAvg(string $column, ?string $alias = null): SQLBuilder
    {
        return $this->selectAggregate('AVG', $column, $alias);
    }

    public function selectCount(string $column = '*', ?string $alias = 'count'): SQLBuilder
    {
        return $this->selectAggregate('COUNT', $column, $alias);
    }

    /**
     * Filter WHERE.
     */
    public function where($field, $operand = NULL, $value = NULL): SQLBuilder
    {
        $this->_filters->where($field, $operand, $value);
        return $this;
    }

    public function orWhere($field, $operand = NULL, $value = NULL): SQLBuilder
    {
        $this->_filters->orWhere($field, $operand, $value);
        return $this;
    }

    /**
     * Compare two columns (no bound value). Useful for correlated subqueries.
     */
    public function whereColumn(string $left, string $operator, string $right): SQLBuilder
    {
        return $this->addColumnComparison('AND', $left, $operator, $right);
    }

    public function orWhereColumn(string $left, string $operator, string $right): SQLBuilder
    {
        return $this->addColumnComparison('OR', $left, $operator, $right);
    }

    public function whereExists(SQLBuilder $sub): SQLBuilder
    {
        $this->_filters->where(['EXISTS' => [$sub]]);
        return $this;
    }

    public function orWhereExists(SQLBuilder $sub): SQLBuilder
    {
        $this->_filters->orWhere(['EXISTS' => [$sub]]);
        return $this;
    }

    public function whereNotExists(SQLBuilder $sub): SQLBuilder
    {
        $this->_filters->where(['NOT EXISTS' => [$sub]]);
        return $this;
    }

    public function orWhereNotExists(SQLBuilder $sub): SQLBuilder
    {
        $this->_filters->orWhere(['NOT EXISTS' => [$sub]]);
        return $this;
    }

    public function groupBy(string ...$columns): SQLBuilder
    {
        foreach ($columns as $column) {
            $qualified = $this->qualifyColumn($column);
            $this->_groups[] = $qualified;
        }
        return $this;
    }

    public function having($field, $operand = NULL, $value = NULL): SQLBuilder
    {
        $this->_havings->where($field, $operand, $value);
        return $this;
    }

    public function orHaving($field, $operand = NULL, $value = NULL): SQLBuilder
    {
        $this->_havings->orWhere($field, $operand, $value);
        return $this;
    }

    /**
     * INNER JOIN another table (used by inheritance).
     */
    public function join(string|SQLBuilder $table, string $field, $foreign = NULL, ?string $alias = null): SQLBuilder
    {
        return $this->addJoin('INNER', $table, $field, $foreign, $alias);
    }

    /**
     * LEFT JOIN another table (BelongsTo eager load).
     */
    public function leftJoin(string|SQLBuilder $table, string $field, $foreign = NULL, ?string $alias = null): SQLBuilder
    {
        return $this->addJoin('LEFT', $table, $field, $foreign, $alias);
    }

    /**
     * Eager-load BelongsTo relations declared via attributes (LEFT JOIN + aliases).
     */
    public function with(string ...$relations): SQLBuilder
    {
        if ($this->_model === null) {
            throw new \Exception('Unable to call "with" on a builder not associated with a model!');
        }

        $map = BelongsToMap::for($this->_model::class);
        foreach ($relations as $relation) {
            if (!isset($map[$relation])) {
                throw new \InvalidArgumentException(
                    'Unknown BelongsTo relation "' . $relation . '" on ' . $this->_model::class
                );
            }

            if (isset($this->_eagerRelations[$relation])) {
                continue;
            }

            $meta = $map[$relation];
            /** @var Model $related */
            $related = new $meta['related']();
            $this->assertIdentifier($relation);

            $this->leftJoin($related->table, $meta['ownerKey'], $meta['foreignKey'], $relation);

            foreach ($related->getSelectFieldsForRelation($meta['ownerKey']) as $col) {
                $this->assertIdentifier($col);
                $this->selectAs($relation . '.' . $col, $relation . '__' . $col);
            }

            $this->_eagerRelations[$relation] = [
                'class' => $meta['related'],
                'prefix' => $relation . '__',
            ];
        }

        return $this;
    }

    /**
     * @return array<string, array{class: class-string<Model>, prefix: string}>
     */
    public function eagerRelations(): array
    {
        return $this->_eagerRelations;
    }

    public function order(string $field, string $order): SQLBuilder
    {
        $this->assertIdentifier($field);
        $this->_orders[$field] = self::assertOrderDirection($order);
        return $this;
    }

    /**
     * Set the offset.
     */
    public function offset(int $offset): SQLBuilder
    {
        $this->_offset = $offset;
        return $this;
    }

    /**
     * Set the limit.
     */
    public function limit(int $limit): SQLBuilder
    {
        $this->_limit = $limit;
        return $this;
    }

    /**
     * Build the SQL.
     */
    public function build(): string
    {
        return $this->buildInternal(false, false);
    }

    /**
     * Build SQL suitable for embedding as a subquery / EXISTS.
     */
    public function buildSubquery(bool $forExists = false): string
    {
        return $this->buildInternal(true, $forExists);
    }

    /**
     * Return the list of values to escape used in the query.
     */
    public function values(): array
    {
        return $this->_values;
    }

    /**
     * Return SQL with placeholders and the bound values (no string interpolation).
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    public function toStatement(): array
    {
        return [$this->build(), $this->values()];
    }

    /**
     * Return the final SQL query using the DB engine.
     */
    public function toSQL($engine) {
        $sql = $this->build();
        $values = $this->values();

        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $list = [];
                foreach ($value as $v) {
                    $escapedValue = $engine::escape($v);
                    if(!is_numeric($escapedValue) && is_string($escapedValue)) {
                        $escapedValue = "'" . $escapedValue . "'";
                    }
                    $list[] = $escapedValue;
                }
                $safeValue = '(' . join(',', $list) . ')';
                if (empty($list)) {
                    $safeValue = '(FALSE)';
                }
            } else {
                $safeValue = '"' . $engine::escape($value) . '"';
            }
            $sql = str_replace(':' . $key, $safeValue, $sql);
        }
        return $sql;
    }

    /**
     * Execute the query on the model.
     */
    public function get(): Collection
    {
        if ($this->_model === NULL) {
            throw new \Exception('Unable to call "get" on a builder not associated with a model!');
        }
        $model = $this->_model;
        return $model::query($this);
    }

    /**
     * Execute the query and return the first model.
     */
    public function first(): null|Model
    {
        return $this
            ->offset(0)
            ->limit(1)
            ->get()
            ->first();
    }

    /**
     * Quote the table and column name.
     */
    protected function _q($element): string
    {
        $this->assertIdentifier((string) $element);
        $els = explode('.', $element);
        $elements = [];
        foreach ($els as $el) {
            $elements[] = '`' . $el . '`';
        }
        return join('.', $elements);
    }

    /**
     * Add a new value to be escaped (or embed a subquery).
     */
    protected function _value($value): string
    {
        if ($value instanceof SQLBuilder) {
            return $this->_embedSubquery($value, false);
        }

        $c = count($this->_values);
        $varName = 'value' . ($c + 1);
        $this->_values[$varName] = $value;
        return ':' . $varName;
    }

    protected function buildInternal(bool $subquery, bool $forExists): string
    {
        $this->_values = [];

        $sql = $this->_select($subquery, $forExists);
        $sql .= $this->_joins();

        $filters = $this->_filters->build();
        if (!empty($filters)) {
            $sql .= ' WHERE ' . $this->_generatefilters($filters);
        }

        $sql .= $this->_groupBy();

        $havings = $this->_havings->build();
        if (!empty($havings)) {
            $sql .= ' HAVING ' . $this->_generatefilters($havings);
        }

        $sql .= $this->_orders();

        if ($this->_offset >= 0 && $this->_limit >= 0) {
            $sql .= ' LIMIT ' . $this->_offset . ', ' . $this->_limit;
        }
        return $sql;
    }

    protected function selectAggregate(string $agg, string $column, ?string $alias): SQLBuilder
    {
        $agg = strtoupper($agg);
        if (!in_array($agg, self::ALLOWED_AGGREGATES, true)) {
            throw new \InvalidArgumentException('Invalid aggregate function: ' . $agg);
        }

        if ($column === '*') {
            if ($agg !== 'COUNT') {
                throw new \InvalidArgumentException('Only COUNT may use *');
            }
            $qualified = '*';
        } else {
            $qualified = $this->qualifyColumn($column);
        }

        if ($alias === null || $alias === '') {
            $base = $column === '*' ? 'all' : (str_contains($column, '.') ? substr($column, strrpos($column, '.') + 1) : $column);
            $alias = strtolower($agg) . '_' . $base;
        }
        $this->assertIdentifier($alias);

        $this->_hasAggregate = true;
        $this->_fields[$alias] = [
            'agg' => $agg,
            'column' => $qualified,
            'as' => $alias,
        ];
        return $this;
    }

    protected function qualifyColumn(string $column): string
    {
        if (strpos($column, '.') === false) {
            $column = $this->_table . '.' . $column;
        }
        $this->assertIdentifier($column);
        return $column;
    }

    protected function addColumnComparison(string $boolOp, string $left, string $operator, string $right): SQLBuilder
    {
        $this->assertIdentifier($left);
        $this->assertIdentifier($right);
        if (!in_array($operator, self::COLUMN_OPERATORS, true)) {
            throw new \InvalidArgumentException('Invalid column comparison operator: ' . $operator);
        }
        if ($boolOp === 'OR') {
            $this->_filters->orWhere(['COLUMN' => [$left, $operator, $right]]);
        } else {
            $this->_filters->where(['COLUMN' => [$left, $operator, $right]]);
        }
        return $this;
    }

    /**
     * @param 'INNER'|'LEFT' $type
     */
    protected function addJoin(string $type, string|SQLBuilder $table, string $field, $foreign = NULL, ?string $alias = null): SQLBuilder
    {
        if ($table instanceof SQLBuilder) {
            $entity = $table;
            $table = $entity->_table;

            foreach ($entity->_fields as $k => $f) {
                $this->_fields[$k] = $f;
            }

            $this->_filters->where($entity->_filters);
        } else {
            $this->assertIdentifier($table);
        }

        $this->assertIdentifier($field);
        if ($foreign !== NULL && $foreign !== '') {
            $this->assertIdentifier((string) $foreign);
        }
        if ($alias !== null && $alias !== '') {
            $this->assertIdentifier($alias);
        }

        $this->_joins[] = [
            'table' => $table,
            'field' => $field,
            'foreign' => $foreign,
            'type' => $type,
            'alias' => $alias,
        ];
        return $this;
    }

    protected function _embedSubquery(SQLBuilder $sub, bool $forExists): string
    {
        $sql = $sub->buildSubquery($forExists);
        $replacements = [];
        foreach ($sub->values() as $key => $value) {
            $replacements[':' . $key] = $this->_value($value);
        }
        uksort($replacements, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));
        if ($replacements !== []) {
            $sql = strtr($sql, $replacements);
        }
        return '(' . $sql . ')';
    }

    /**
     * Generate the SELECT statement.
     */
    protected function _select(bool $subquery = false, bool $forExists = false): string
    {
        $fieldsArray = [];
        $fromTable = false;
        $skipStar = $this->_hasAggregate || $this->_groups !== [] || $forExists;

        foreach ($this->_fields as $field) {
            if (is_array($field)) {
                if (isset($field['agg'])) {
                    if ($field['column'] === '*') {
                        $fieldsArray[] = $field['agg'] . '(*) AS ' . $this->_q($field['as']);
                    } else {
                        $fieldsArray[] = $field['agg'] . '(' . $this->_q($field['column']) . ') AS ' . $this->_q($field['as']);
                    }
                    continue;
                }
                $fieldsArray[] = $this->_q($field['expr']) . ' AS ' . $this->_q($field['as']);
                continue;
            }

            $fieldsArray[] = $this->_q($field);
            if (strncmp($this->_table . '.', $field, strlen($this->_table) + 1) == 0) {
                $fromTable = true;
            }
        }

        if ($forExists && $fieldsArray === []) {
            $fieldsArray[] = '1';
        } elseif (!$skipStar && !$fromTable) {
            array_unshift($fieldsArray, $this->_q($this->_table) . '.*');
        }

        if ($fieldsArray === []) {
            throw new \InvalidArgumentException('SELECT requires at least one column when using GROUP BY or aggregates');
        }

        $fields = join(', ', $fieldsArray);
        $prefix = $subquery ? 'SELECT ' : 'SELECT SQL_CALC_FOUND_ROWS ';

        return $prefix . $fields . ' FROM ' . $this->_q($this->_table);
    }

    /**
     * Generate the JOINs.
     */
    protected function _joins(): string
    {
        $sql = '';
        foreach ($this->_joins as $join) {
            // Backward-compatible tuple: [table, field, foreign]
            if (array_is_list($join) || isset($join[0])) {
                $table = $join[0];
                $field = $join[1];
                $foreign = $join[2] ?? $join[1];
                $type = 'INNER';
                $alias = null;
            } else {
                $table = $join['table'];
                $field = $join['field'];
                $foreign = $join['foreign'] ?? $join['field'];
                $type = $join['type'] ?? 'INNER';
                $alias = $join['alias'] ?? null;
            }

            $joinType = $type === 'LEFT' ? 'LEFT JOIN' : 'INNER JOIN';
            $right = ($alias !== null && $alias !== '') ? $alias : $table;

            $sql .= ' ' . $joinType . ' ' . $this->_q($table);
            if ($alias !== null && $alias !== '') {
                $sql .= ' AS ' . $this->_q($alias);
            }
            $sql .= ' ON ' . $this->_q($right . '.' . $field)
                . ' = ' . $this->_q($this->_table . '.' . ($foreign ?? $field));
        }
        return $sql;
    }

    /**
     * Generate the content of a WHERE clause (used in SELECT, UPDATE, DELETE).
     */
    protected function _generatefilters(array $array): string
    {
        $op = array_key_first($array);
        if ($op === null) {
            throw new \InvalidArgumentException('Invalid filter structure: empty operator');
        }

        self::assertOperator((string) $op);
        $values = $array[$op];
        $operandCount = is_array($values) ? count($values) : 0;

        switch ($op) {
            case 'AND':
            case 'OR':
                $sql = '(';
                for ($i = 0; $i < $operandCount; $i++) {
                    if ($i === $operandCount - 1) {
                        $sql .= $this->_generatefilters($values[$i]);
                        break;
                    }
                    $sql .= $this->_generatefilters($values[$i]) . ' ' . $op . ' ';
                }
                $sql .= ')';
                break;

            case 'EXISTS':
            case 'NOT EXISTS':
                if ($operandCount < 1 || !$values[0] instanceof SQLBuilder) {
                    throw new \InvalidArgumentException($op . ' requires an SQLBuilder subquery');
                }
                $sql = $op . ' ' . $this->_embedSubquery($values[0], true);
                break;

            case 'COLUMN':
                if ($operandCount < 3) {
                    throw new \InvalidArgumentException('COLUMN comparison requires left, operator, right');
                }
                [$left, $cmp, $right] = $values;
                if (!in_array($cmp, self::COLUMN_OPERATORS, true)) {
                    throw new \InvalidArgumentException('Invalid column comparison operator: ' . $cmp);
                }
                $sql = $this->_q($left) . ' ' . $cmp . ' ' . $this->_q($right);
                break;

            default:
                if ($operandCount > 1) {
                    $sql = $this->_q($values[0]) . ' ' . $op . ' ' . $this->_value($values[1]);
                    break;
                }
                $sql = $this->_q($values[0]) . ' ' . $op;
                break;
        }
        return $sql;
    }

    protected function _groupBy(): string
    {
        if ($this->_groups === []) {
            return '';
        }
        $cols = [];
        foreach ($this->_groups as $col) {
            $cols[] = $this->_q($col);
        }
        return ' GROUP BY ' . join(', ', $cols);
    }

    protected function _orders(): string
    {
        $sql = '';
        if (!empty($this->_orders)) {
            $orders = [];
            foreach ($this->_orders as $k => $v) {
                $orders[] = $this->_q($k) . ' ' . $v;
            }
            $sql .= ' ORDER BY ' . join(', ', $orders);
        }
        return $sql;
    }
}
