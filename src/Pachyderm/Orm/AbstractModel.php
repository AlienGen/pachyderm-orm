<?php

namespace Pachyderm\Orm;

abstract class AbstractModel implements \ArrayAccess
{
    public $deleted_at = null;
    public $updated_at = null;
    public $created_at = null;
    protected $_data = array();

    /**
     * Magic getter to get the value of a field.
     */
    public function __get($field)
    {
        if (!isset($this->_data[$field])) {
            return null;
        }
        return $this->_data[$field];
    }

    /**
     * Magic setter to set the value of a field
     */
    public function __set($field, $value)
    {
        $this->_data[$field] = $value;
    }

    /**
     * Magic isset to define the behavior of the isset method.
     */
    public function __isset(string $field): bool
    {
        return isset($this->_data[$field]);
    }

    /**
     * Array like setter
     */
    public function offsetSet($key, $value): void
    {
        $this->_data[$key] = $value;
    }

    /**
     * Array like exists
     */
    public function offsetExists($key): bool
    {
        return array_key_exists($key, $this->_data);
    }

    /**
     * Array like unset
     */
    public function offsetUnset($key): void
    {
        unset($this->_data[$key]);
    }

    /**
     * Array like getter
     */
    public function offsetGet($key)
    {
        return $this->_data[$key];
    }

    /**
     * Account constructor.
     * @param $data array
     */
    public function __construct(array $data = array())
    {
        $this->set($data);
    }

    public function set(array $data = array()): void
    {
        foreach ($data as $k => $v) {
            $this->$k = $v;
        }
    }

    public function getId(): string
    {
        return $this->_data[$this->primary_key];
    }

    public function toArray(): array
    {
        return $this->_data;
    }
}
