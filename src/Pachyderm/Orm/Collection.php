<?php

namespace Pachyderm\Orm;

use Pachyderm\Utils\IterableObjectSet;

class Collection extends IterableObjectSet
{
    protected int $_total_record = 0;

    public function __construct(int $totalRecords = 0)
    {
        $this->_total_record = $totalRecords;
    }

    public function totalRecords(): int
    {
        return $this->_total_record;
    }

    /**
     * Avoid the modification of the collection using object access.
     */
    public function __set($key, $value)
    {
        throw new \Error('Collection cannot contains new properties!');
    }

    /**
     * Ensure the type of data stored in the collection.
     */
    public function offsetSet($key, $value): void
    {
        if (!$value instanceof AbstractModel) {
            throw new \Error('Unable to add other type to a collection!');
        }
        $this->_data[$key] = $value;
    }

    public function add(AbstractModel $m): void
    {
        $this->_data[] = $m;
    }

    public function addAll(Collection $c): void
    {
        foreach ($c as $m) {
            $this->add($m);
        }
    }

    public function first(): null|AbstractModel
    {
        if (empty($this->_data[0])) {
            return null;
        }
        return $this->_data[0];
    }

    /**
     * Ensure the type returned by the collection.
     */
    public function current(): null|AbstractModel
    {
        return current($this->_data);
    }

    /**
     * @deprecated The collection is iterable
     */
    public function toArray(): array
    {
        $array = array();
        foreach ($this->_data as $m) {
            $array[] = $m->toArray();
        }
        return $array;
    }

    /**
     * @deprecated
     */
    public function toObject(string $pk = null): iterable
    {
        $object = array();
        foreach ($this->_data as $m) {
            if (empty($pk)) {
                $pk = $m->primary_key;
            }
            $object[$m->$pk] = $m->toArray();
        }
        return $object;
    }

    public function save(): void
    {
        foreach ($this->_data as $m) {
            $m->save();
        }
    }

    public function delete(): void
    {
        foreach ($this->_data as $m) {
            $m->delete();
        }
    }
}
