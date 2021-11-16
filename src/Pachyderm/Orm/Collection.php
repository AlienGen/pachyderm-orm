<?php

namespace Pachyderm\Orm;

class Collection
{
  public $data = array();

  public function __construct() {

  }

  public function add(AbstractModel $m) {
    $this->data[] = $m;
  }

  public function getIndex(int $i) {
      if (!isset($this->data[$i])) {
          return null;
      }

      return $this->data[$i];
  }

  public function toArray() {
    $array = array();
    foreach($this->data as $m) {
      $array[] = $m->toArray();
    }
    return $array;
  }

  public function toObject($pk = null) {
      $object = array();
      foreach ($this->data as $m) {
          if (empty($pk)) {
              $pk = $m->primary_key;
          }
          $object[$m->$pk] = $m->toArray();
      }
      return $object;
  }

  public function save() {
    $array = array();
    foreach($this->data as $m) {
      $array[] = $m->save();
    }
    return $array;
  }

  public function delete() {
    foreach($this->data as $m) {
      $m->delete();
    }
  }
}
