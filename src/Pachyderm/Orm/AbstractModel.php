<?php

namespace Pachyderm\Orm;

use Pachyderm\Db;
use App\Exceptions\NotFoundException;

abstract class AbstractModel
{
    public $deleted_at = null;
    public $updated_at = null;
    public $created_at = null;
    protected $data = array();

    public function __get($field)
    {
        if (!isset($this->data[$field])) {
            return null;
        }
        return $this->data[$field];
    }

    public function __set($field, $value)
    {
        $this->data[$field] = $value;
    }

    public function __isset(string $field): bool
    {
        return isset($this->data[$field]);
    }

    /**
     * Account constructor.
     * @param $data array
     */
    public function __construct($data = array())
    {
        $this->set($data);
    }

    public function set(array $data = array())
    {
        foreach ($data as $k => $v) {
            $this->$k = $v;
        }
    }

    public function getId()
    {
        return $this->data[$this->primary_key];
    }

    public function toArray()
    {
        return $this->data;
    }
}
