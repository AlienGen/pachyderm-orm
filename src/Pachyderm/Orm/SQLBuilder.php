<?php

namespace Pachyderm\Orm;

use Pachyderm\Orm\Relation\BelongsToMap;

class SQLBuilder
{
    protected const IDENTIFIER_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/';

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
    ];

    protected string $_table;
    protected Model|null $_model;

    protected array $_fields = [];
    protected array $_joins = [];
    protected QueryBuilder $_filters;
    protected array $_values = [];
    protected array $_orders = [];
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
        $sql = $this->_select();
        $sql .= $this->_joins();

        $filters = $this->_filters->build();
        if (!empty($filters)) {
            $sql .= ' WHERE ' . $this->_generatefilters($filters);
        }

        $sql .= $this->_orders();

        if ($this->_offset >= 0 && $this->_limit >= 0) {
            $sql .= ' LIMIT ' . $this->_offset . ', ' . $this->_limit;
        }
        return $sql;
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
     * Add a new value to be escaped.
     */
    protected function _value($value): string
    {
        $c = count($this->_values);
        $varName = 'value' . ($c + 1);
        $this->_values[$varName] = $value;
        return ':' . $varName;
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

    /**
     * Generate the SELECT statement.
     */
    protected function _select(): string
    {
        $fieldsArray = [];
        $fromTable = false;
        foreach ($this->_fields as $field) {
            if (is_array($field)) {
                $fieldsArray[] = $this->_q($field['expr']) . ' AS ' . $this->_q($field['as']);
                continue;
            }

            $fieldsArray[] = $this->_q($field);
            if (strncmp($this->_table . '.', $field, strlen($this->_table) + 1) == 0) {
                $fromTable = true;
            }
        }

        if (!$fromTable) {
            array_unshift($fieldsArray, $this->_q($this->_table) . '.*');
        }

        $fields = join(', ', $fieldsArray);

        return 'SELECT SQL_CALC_FOUND_ROWS ' . $fields . ' FROM ' . $this->_q($this->_table);
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
        $operandCount = count($values);

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
