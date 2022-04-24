<?php

namespace Pachyderm\Orm;

class QueryBuilder
{

    protected $model = NULL;
    protected $filters = array();

    public function __construct($serialized = NULL)
    {
        if ($serialized !== NULL) {
            $this->filters = (array)json_decode(base64_decode($serialized), TRUE);
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
            $this->append($globalOperator, $field->build());
            return $this;
        }

        if (is_callable([$this, $field])) {
            $field($this);
            return $this;
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

    public function setModel($classname)
    {
        $this->model = $classname;
    }

    public function get()
    {
        $model = $this->model;
        // @FIXME: Remove offset and limit
        return $model::findAll($this, NULL, 0, 1000000);
    }
}
/*

$subquery = new QueryBuilder();


$subquery
  ->where('company_id', '=', 1)
  ->where('date', '<', date('Y-m-d'))
  ->where('user_id', 'IS NULL');

$query = new QueryBuilder();

$query
  ->where('contract_id', '!=', 42)
  ->orWhere($subquery);

$array = $query->build();
echo 'As array: ', PHP_EOL;
print_r($array);

// ['AND' => [['=' => ['contract_id', $this->contract['contract_id']], ['=' => ['version_id', $this->version['version_id']]]]]]

echo 'Serialized: ', PHP_EOL;
echo $query->serialize(), PHP_EOL;
*/
