<?php

namespace Pachyderm\Orm;

class Collection implements \Iterator, \Countable, \ArrayAccess
{
  protected $_data = array();
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
   * Array like setter
   */
  public function offsetSet($key, $value): void
  {
    if (!$value instanceof AbstractModel) {
      throw new \Error('Unable to add other type to a collection!');
    }
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

  public function add(AbstractModel $m): void
  {
    $this->_data[] = $m;
  }

  public function first(): null|AbstractModel
  {
    if (empty($this->_data[0])) {
      return null;
    }
    return $this->_data[0];
  }

  public function count(): int
  {
    return count($this->_data);
  }

  public function current(): null|AbstractModel
  {
    return current($this->_data);
  }

  public function next(): void
  {
    next($this->_data);
  }

  public function key(): null|string|int
  {
    return key($this->_data);
  }

  public function valid(): bool
  {
    return current($this->_data) != NULL;
  }

  public function rewind(): void
  {
    reset($this->_data);
  }

  /**
   * @deprecated
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
