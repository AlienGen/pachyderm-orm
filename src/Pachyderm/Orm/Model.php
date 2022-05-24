<?php

namespace Pachyderm\Orm;

use \Pachyderm\Db;
use \Pachyderm\Orm\Exception\ModelNotFoundException;

abstract class Model extends AbstractModel
{
  protected $_scopes = array();

  private function _execute_hook(string $hook, $data = NULL)
  {
    if (method_exists($this, $hook)) {
      return $this->$hook($data);
    }
    return $data;
  }

  private function _build_where(): array
  {
    // Single key.
    if (!is_array($this->primary_key)) {
      return [
        '=' =>
        [
          $this->primary_key,
          $this[$this->primary_key]
        ]
      ];
    }

    // Multiple keys.
    $where = array();
    foreach ($this->primary_key as $pk) {
      $where['AND'][] = ['=' => [$pk, $this[$pk]]];
    }
    return $where;
  }

  public function save(): void
  {
    $where = $this->_build_where();
    $data = $this->_execute_hook('pre_update', $this->_data);
    Db::update($this->table, $data, $where);
    $this->_execute_hook('post_update');
  }

  public function delete(): void
  {
    $where = $this->_build_where();
    $this->_execute_hook('pre_delete');
    Db::delete($this->table, $this->primary_key, $where);
    $this->_execute_hook('post_delete');
  }

  public function addScope(string $name, $scope = NULL): void
  {
    $this->_scopes[$name] = $scope;
  }

  /**
   *
   * Static methods
   *
   */
  public static function paginate(Paginator $paginator): Collection
  {
    return self::findAll($paginator->filters(), $paginator->order(), $paginator->offset(), $paginator->limit());
  }

  public static function where($field, $operator, $value): QueryBuilder
  {
    $builder = new QueryBuilder();
    $builder->setModel(get_called_class());
    $builder->where($field, $operator, $value);
    return $builder;
  }

  public static function findAll($where = NULL, $order = NULL, int $offset = 0, int $limit = 50): Collection
  {
    $model = new static();

    $query = new QueryBuilder();
    $query->where($where);

    /**
     * Handle scopes.
     */
    if (!empty($model->_scopes)) {
      foreach ($model->_scopes as $scopeName => $scope) {
        $query->where($scope);
      }
    }

    /**
     * Query the database.
     */
    $items = Db::findAll($model->table, $query->build(), $order, $offset, $limit);

    /**
     * Instantiate the final objects.
     */
    $collection = new Collection();
    foreach ($items as $item) {
      $collection->add(new static($item));
    }
    return $collection;
  }

  public static function findFirst($where = null): Model
  {
    return self::findAll($where, null, 0, 1)->first();
  }

  public static function find($id): Model
  {
    if (empty($id)) {
      throw new ModelNotFoundException('Model ' . get_called_class() . ' with id=' . $id . ' not found!');
    }

    $model = new static();
    $data = Db::findOne($model->table, $model->primary_key, $id);

    if (empty($data)) {
      throw new ModelNotFoundException('Model not found!');
    }

    return new static($data);
  }

  public static function create(array $data): Model
  {
    $model = new static();

    unset($data['created_at']);

    if (!is_array($model->primary_key)) {
      unset($data[$model->primary_key]);
    }

    $data = $model->_execute_hook('pre_create', $data);
    $id = Db::insert($model->table, $data);
    $model->_execute_hook('post_create');

    if (is_array($model->primary_key)) {
      $id = array();
      foreach ($model->primary_key as $pk) {
        $id[] = $data[$pk];
      }
    }

    if (!$id) {
      throw new \Exception('Unable to create the entity!');
    }
    return static::find($id);
  }
}
