<?php

namespace Pachyderm\Orm;

class Paginator {

  public $filters;
  public $sort = array();
  public $offset = 0;
  public $limit = 1000;

  public function __construct() {
    $this->filters = new QueryBuilder();
  }

  /**
   * @Deprecated
   */
  public function addFilter($field, $operator, $value) {
    if($value == 'NULL') {
      $this->filters->where($field, 'IS NULL');
      return;
    }
    $this->filters->where($field, $operator, $value);
  }

  public function addOrder($field, $order) {
    $this->sort[$field] = $order;
  }

  public function parse($params) {
    if(!empty($params['page']) && !empty($params['size'])) {
      $this->offset = ($params['page']-1) * $params['size'];
      $this->limit = $params['size'];
    }
    unset($params['page']);
    unset($params['size']);

    if(!empty($params['order'])) {
      if(is_array($params['order'])) {
        foreach($params['order'] AS $order) {
          $raw = explode(',', $order);
          $this->addOrder($raw[0], $raw[1] ?? 'ASC');
        }
      } else {
        $raw = explode(',', $params['order']);
        $this->addOrder($raw[0], $raw[1] ?? 'ASC');
      }
    }
    unset($params['order']);

    if(!empty($params['filter'])) {
      $query = new QueryBuilder($params['filter']);
      $this->filters->where($query);
      unset($params['filter']);
    }

    // @Deprecated
    $filters = [];
    foreach($params AS $k => $v) {
      $this->addFilter($k, '=', $v);
      //$filters[] = ['=' => [$k, $v]];
    }

//     if(!empty($filters)) {
//       if(empty($this->filters['AND'])) {
//         $this->filters['AND'] = [];
//       }
//       $this->filters['AND'] = array_merge($this->filters['AND'], $filters);
//     }
  }

  public function where($field, $operator, $value) {
    return $this->filters->where($field, $operator, $value);
  }

  public function filters() {
    return $this->filters;
  }

  public function order() {
    return $this->sort;
  }

  public function offset() {
    return $this->offset;
  }

  public function limit() {
    return $this->limit;
  }
}
