<?php

namespace Pachyderm\Orm;

use \Pachyderm\Db;
use \Pachyderm\Orm\Exception\ModelNotFoundException;

abstract class Model extends AbstractModel
{
  protected $_scopes = array();

  public function __construct(array $data = array())
  {
    parent::__construct($data);
    if (empty($this->table)) {
      throw new \Exception('Property "table" must be set on model ' . get_called_class());
    }

    if (empty($this->primary_key)) {
      throw new \Exception('Property "primary_key" must be set on model ' . get_called_class());
    }
  }

  private function _execute_hook(string $hook, mixed $data = NULL)
  {
    if (method_exists($this, $hook)) {
      return $this->$hook($data);
    }
    return $data;
  }

  private function _getInherit()
  {
    if (!isset($this->inherit)) {
      return NULL;
    }
    $parent = $this->inherit;
    $p = new $parent();
    if (!$p instanceof Model) {
      throw new \Exception('"inherit" property must be a Model instance!');
    }
    return $p;
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
  public static function create(array $data): Model
  {
    $model = new static();

    // TODO: Avoid doing that
    unset($data['created_at']);

    // Remove primary_key from the data if the model is not a relation table.
    if (!is_array($model->primary_key)) {
      unset($data[$model->primary_key]);
    }

    /**
     * Call hook.
     */
    $data = $model->_execute_hook('pre_create', $data);

    /**
     * Create parent if the model is an inheritance.
     */
    $parent = $model->_getInherit();
    if ($parent !== NULL) {
      // Insert the fields for the parents.
      $fields = $parent->getFields();
      $parentData = [];
      foreach ($fields as $f) {
        if (is_string($f)) {
          $parentData[$f] = $data[$f];
          unset($data[$f]);
        }
      }
      $p = $parent::create($parentData);

      // Set the primary key with the parent field.
      $data[$model->primary_key] = $p->getId();
    }

    $id = Db::insert($model->table, $data);

    if (is_array($model->primary_key)) {
      $id = array();
      foreach ($model->primary_key as $pk) {
        $id[] = $data[$pk];
      }
    }

    if (!$id) {
      throw new \Exception('Unable to create the entity!');
    }

    // Fetch the entity from the database.
    $entity = static::find($id);

    return $model->_execute_hook('post_create', $entity);
  }

  public static function builder(): SQLBuilder
  {
    $model = new static();

    $builder = new SQLBuilder($model);
    $parent = $model->_getInherit();
    if ($parent !== NULL) {
      $parentBuilder = $parent::builder()
        ->select($parent->getFields());
      $builder->join($parentBuilder, $parent->primary_key, $model->primary_key);
    }

    return $builder;
  }

  public static function where($field, $operator, $value): SQLBuilder
  {
    return self::builder()
      ->where($field, $operator, $value);
  }

  public static function query(SQLBuilder $builder): Collection
  {
    $sql = $builder->build();
    $values = $builder->values();

    // TODO: Replace with PDO later.
    foreach ($values as $key => $value) {
      $sql = str_replace(':' . $key, '"' . Db::escape($value) . '"', $sql);
    }

    /**
     * Query the database.
     */
    $results = Db::query($sql);

    /**
     * Instantiate the final objects.
     */
    $collection = new Collection();
    while ($item = $results->fetch_assoc()) {
      $collection->add(new static($item));
    }

    return $collection;
  }

  public static function findAll($where = NULL, $order = NULL, int $offset = 0, int $limit = 50): Collection
  {
    $query = self::builder()
      ->where($where)
      ->offset($offset)
      ->limit($limit);

    if ($order !== NULL) {
      foreach ($order as $k => $v) {
        $query->order($k, $v);
      }
    }

    /**
     * Handle scopes.
     */
    $model = new static();
    if (!empty($model->_scopes)) {
      foreach ($model->_scopes as $scopeName => $scope) {
        $query->where($scope);
      }
    }

    return $query->get();
  }

  public static function pagination(array $params): SQLBuilder
  {
    $offset = 0;
    $limit = 50;

    if (!empty($params['page']) && !empty($params['size'])) {
      $offset = ($params['page'] - 1) * $params['size'];
      $limit = $params['size'];
    }
    unset($params['page']);
    unset($params['size']);

    $builder = self::builder()
      ->offset($offset)
      ->limit($limit);

    if (!empty($params['order'])) {
      $orders = is_array($params['order']) ? $params['order'] : [$params['order']];

      foreach ($orders as $order) {
        $raw = explode(',', $order);
        $builder->order($raw[0], $raw[1] ?? 'ASC');
      }
    }
    unset($params['order']);

    if (!empty($params['filter'])) {
      $query = new QueryBuilder($params['filter']);
      $builder->where($query);
      unset($params['filter']);
    }

    $model = new static();
    $parent = $model->_getInherit();
    $parentFields = [];
    if ($parent !== NULL) {
      foreach ($parent->getFields() as $f) {
        $parentFields[$f] = $parent->table;
      }
    }

    /**
     * @deprecated
     */
    foreach ($params as $k => $v) {
      // Prefix the field if it belongs to another table.
      if (!empty($parentFields[$k])) {
        $k = $parentFields[$k] . '.' . $k;
      }
      $builder->where($k, '=', $v);
    }

    return $builder;
  }

  /**
   * @deprecated
   */
  public static function paginate(Paginator $paginator): Collection
  {
    return self::findAll($paginator->filters(), $paginator->order(), $paginator->offset(), $paginator->limit());
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
    $data = self::where($model->primary_key, '=', $id)
      ->first();

    if (empty($data)) {
      throw new ModelNotFoundException('Model not found!');
    }

    return $data;
  }
}
