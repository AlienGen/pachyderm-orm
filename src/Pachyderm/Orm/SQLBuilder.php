<?php

namespace Pachyderm\Orm;

class SQLBuilder
{
    protected string $_table;
    protected Model $_model;

    protected array $_fields = [];
    protected array $_joins = [];
    protected QueryBuilder $_filters;
    protected array $_values = [];
    protected array $_orders = [];
    protected int $_offset = 0;
    protected int $_limit = -1;

    public function __construct(string|Model $table)
    {
        $this->_filters = new QueryBuilder();
        $this->_filters->prepend($table);

        if (is_string($table)) {
            $this->_table = $table;
            return;
        }

        if (new $table() instanceof Model) {
            $model = new $table();
            $this->_table = $model->table;
            $this->_model = $model;
            return;
        }
    }

    /**
     * Set fields to retrieve.
     */
    public function select(...$fields): SQLBuilder
    {
        foreach ($fields as $f) {
            $this->_fields[$f] = $this->_table . '.' . $f;
        }
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
     * Join another table.
     */
    public function join(string|SQLBuilder $table, string $field, $foreign = NULL): SQLBuilder
    {
        if ($table instanceof SQLBuilder) {
            $entity = $table;
            // Set Table name
            $table = $entity->_table;

            // Set Fields
            foreach ($entity->_fields as $k => $f) {
                $this->_fields[$k] = $f;
            }

            // Set joins.

            // Set filters.
            $this->_filters->where($entity->_filters);
        }
        $this->_joins[] = [$table, $field, $foreign];
        return $this;
    }

    public function order(string $field, string $order): SQLBuilder
    {
        $this->_orders[$field] = $order;
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
     * Execute the query on the model.
     */
    public function get(): Collection
    {
        $model = $this->_model;
        return $model::query($this);
    }

    /**
     * Execute the query and return the first model.
     */
    public function first(): Model
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
     * Generate the SELECT statement.
     */
    protected function _select(): string
    {
        $fieldsArray = [];
        $fromTable = false;
        foreach ($this->_fields as $field) {
            $fieldsArray[] = $this->_q($field);
            if (strncmp($this->_table . '.', $field, strlen($this->_table) + 1) == 0) {
                $fromTable = true;
            }
        }

        if (!$fromTable) {
            array_unshift($fieldsArray, $this->_q($this->_table) . '.*');
        }

        $fields = join(', ', $fieldsArray);

        return 'SELECT ' . $fields . ' FROM ' . $this->_q($this->_table);
    }

    /**
     * Generate the JOINs.
     */
    protected function _joins(): string
    {
        $sql = '';
        foreach ($this->_joins as $join) {
            $table = $join[0];
            $sql .= ' INNER JOIN ' . $this->_q($table) . ' ON ' . $this->_q($table . '.' . $join[1]) . ' = ' . $this->_q($this->_table . '.' . ($join[2] ?? $join[1]));
        }
        return $sql;
    }

    /**
     * Generate the content of a WHERE clause (used in SELECT, UPDATE, DELETE).
     */
    protected function _generatefilters(array $array): string
    {
        $op = array_key_first($array);
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
                $orders[] = '`' . $k . '` ' . $v;
            }
            $sql .= ' ORDER BY ' . join(', ', $orders);
        }
        return $sql;
    }
}
