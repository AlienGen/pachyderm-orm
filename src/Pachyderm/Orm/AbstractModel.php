<?php

namespace Pachyderm\Orm;

abstract class AbstractModel implements \ArrayAccess
{
    public null|string $deleted_at = null;
    public null|string $updated_at = null;
    public null|string $created_at = null;
    protected array $_fields = array();
    protected array $_data = array();

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
    public function offsetGet($key): mixed
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

    public function getFields(): array
    {
        if (empty($this->_fields)) {
            throw new \Exception('The array property "_fields" must be defined!');
        }
        return $this->_fields;
    }

    public function set(array $data = array()): void
    {
        foreach ($data as $k => $v) {
            $this->$k = $v;
        }
    }

    public function getId(): string
    {
        if (empty($this->primary_key)) {
            throw new \Exception('The property "primary_key" must be defined!');
        }

        // If the primary key is multiple
        if (is_array($this->primary_key)) {
            $id = array();
            foreach ($this->primary_key as $pk) {
                $id[] = $this->_data[$pk];
            }
            return $id;
        }

        // Single primary key.
        return $this->_data[$this->primary_key];
    }

    public function toArray(): array
    {
        return $this->_data;
    }
}
