<?php

namespace Pachyderm\Orm;

class QueryBuilder
{
    protected $_table = NULL;
    protected $filters = array();

    public function __construct($serialized = NULL)
    {
        if ($serialized !== NULL) {
            $this->filters = (array)json_decode(base64_decode($serialized), TRUE);
        }
    }

    /**
     * Prepend the table name to this builder and any existing unqualified fields.
     */
    public function prepend($table)
    {
        $this->_table = $table;
        if (!empty($this->filters)) {
            $this->filters = $this->qualifyFilters($this->filters, $table);
        }
    }

    private function append($operator, $filter)
    {
        if (empty($this->filters)) {
            $this->filters = $filter;
            return $this;
        }
        $this->filters = [$operator => [$this->filters, $filter]];
    }

    /**
     * Qualify an unqualified column with the given table (no-op if already table.column).
     */
    private function qualifyField(string $field, string $table): string
    {
        if (strpos($field, '.') === false) {
            return $table . '.' . $field;
        }
        return $field;
    }

    /**
     * Recursively qualify bare field identifiers in a filter tree.
     */
    private function qualifyFilters(array $filters, string $table): array
    {
        if ($filters === []) {
            return $filters;
        }

        $op = array_key_first($filters);
        if ($op === null) {
            return $filters;
        }

        $values = $filters[$op];
        if (!is_array($values)) {
            return $filters;
        }

        if ($op === 'AND' || $op === 'OR') {
            $qualified = [];
            foreach ($values as $child) {
                $qualified[] = is_array($child)
                    ? $this->qualifyFilters($child, $table)
                    : $child;
            }
            return [$op => $qualified];
        }

        if ($op === 'EXISTS' || $op === 'NOT EXISTS') {
            return $filters;
        }

        if ($op === 'COLUMN') {
            if (count($values) >= 3) {
                $values[0] = is_string($values[0])
                    ? $this->qualifyField($values[0], $table)
                    : $values[0];
                $values[2] = is_string($values[2])
                    ? $this->qualifyField($values[2], $table)
                    : $values[2];
            }
            return ['COLUMN' => $values];
        }

        if (isset($values[0]) && is_string($values[0])) {
            $values[0] = $this->qualifyField($values[0], $table);
        }

        return [$op => $values];
    }

    private function addWhere($globalOperator, $field, $operator, $value)
    {
        if (empty($field)) {
            return $this;
        }

        if (is_array($field)) {
            $this->append($globalOperator, $field);
            return $this;
        }

        if ($field instanceof QueryBuilder) {
            if ($this->_table !== null && $field->_table === null) {
                $field->prepend($this->_table);
            }
            $this->append($globalOperator, $field->build());
            return $this;
        }

        if ($field instanceof \Closure || (is_callable($field) && !is_string($field))) {
            $field($this);
            return $this;
        }

        if ($this->_table !== null) {
            $field = $this->qualifyField($field, $this->_table);
        }

        if ($value === NULL && $operator !== NULL) {
            $this->append($globalOperator, [$operator => [$field]]);
            return $this;
        }

        $this->append($globalOperator, [$operator => [$field, $value]]);

        return $this;
    }

    public function where($field, $operator = NULL, $value = NULL)
    {
        return $this->addwhere('AND', $field, $operator, $value);
    }

    public function orWhere($field, $operator = NULL, $value = NULL)
    {
        return $this->addwhere('OR', $field, $operator, $value);
    }

    public function build()
    {
        return $this->filters;
    }

    public function serialize()
    {
        return base64_encode(json_encode($this->filters));
    }
}
