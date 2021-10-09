<?php

namespace Pachyderm\Orm;

use Pachyderm\Db;

use App\Exceptions\NotFoundException;

abstract class Model extends AbstractModel
{
  protected $scopes = array();

  public static function paginate(Paginator $paginator) {
    return self::findAll($paginator->filters(), $paginator->order(), $paginator->offset(), $paginator->limit());
  }

  public static function findAll($where = NULL, $order = NULL, $offset = 0, $limit = 50) {
    $model = new static();

    $query = new QueryBuilder();
    $query->where($where);

    if(!empty($model->scopes)) {
      foreach($model->scopes AS $scopeName => $scope) {
        $query->where($scope);
      }
    }

    $items = Db::findAll($model->table, $query->build(), $order, $offset, $limit);
    $collection = new Collection();
    foreach($items AS $item) {
      $collection->add(new static($item));
    }
    return $collection;
  }

  public static function findFirst($where = null)
  {
      $collection = self::findAll($where, null, 0, 1);
      return $collection->getIndex(0);
  }

  public static function find($id) {

    if(empty($id)) {
      throw new NotFoundException('Model ' . get_called_class() . ' with id=' . $id . ' not found!');
    }

    $model = new static();
    $data = Db::findOne($model->table, $model->primary_key, $id);

    if(empty($data)) {
      throw new NotFoundException('Model not found!');
    }

    return new static($data);
  }

  public static function create($data)
  {
    $model = new static();

    unset($data['created_at']);

    if (!is_array($model->primary_key)) {
      unset($data[$model->primary_key]);
    }

    $id = Db::insert($model->table, $data);

    if (is_array($model->primary_key)) {
      $id = array();
      foreach ($model->primary_key as $pk) {
        $id[] = $data[$pk];
      }
    }

    if (!$id) {
      throw new Exception('Unable to create the entity!');
    }
    return static::find($id);
  }

  public function save()
  {
    if (!is_array($this->primary_key)) {
      return Db::update(
        $this->table,
        $this->data,
        ['=' =>
          [
            $this->primary_key,
            $this->data[$this->primary_key]
          ]
        ]
      );
    }

    $where = array();
    foreach ($this->primary_key as $pk) {
        $where['AND'][] = ['=' => [$pk, $this->data[$pk]]];
    }
    return Db::update($this->table, $this->data, $where);
  }

  public function delete()
  {
    if (!is_array($this->primary_key)) {
      return Db::delete($this->table, $this->primary_key, $this->data[$this->primary_key]);
    }

    $values = array();
    foreach ($this->primary_key as $pk) {
      $values[] = $this->data[$pk];
    }

    return Db::delete($this->table, $this->primary_key, $values);
  }

  public function addScope($name, $scope = NULL) {
    $this->scopes[$name] = $scope;
  }

  public static function where($field, $operator, $value) {
    $builder = new QueryBuilder();
    $builder->setModel(get_called_class());
    $builder->where($field, $operator, $value);
    return $builder;
  }
}

