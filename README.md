## Pachyderm ORM

A lightweight, expressive ORM for the Pachyderm micro‑framework.

- **Models**: map database tables to PHP classes
- **Fluent queries**: chainable builders for filters, joins, order, pagination
- **Collections**: iterable results with total count support
- **Inheritance**: optional parent model for shared fields

---

### Table of Contents

- [Installation](#installation)
- [Quick start](#quick-start)
- [Querying](#querying)
  - [Nested groups with QueryBuilder (OR groups)](#nested-groups-with-querybuilder-or-groups)
- [Relations](#relations)
- [Relations via inheritance (optional)](#relations-via-inheritance-optional)
- [Scopes](#scopes)
- [Pagination helper](#pagination-helper)
- [Testing](#testing)
- [Troubleshooting](#troubleshooting)
- [Advanced: Custom DB engine (optional)](#advanced-custom-db-engine-optional)
- [License](#license)

---

### Installation

```bash
composer require aliengen/pachyderm-orm
```

You're ready to use it in your Pachyderm project.

---

### Quick start

#### 1) Declare a Model

```php
<?php

namespace App\Models;

use Pachyderm\Orm\Model;

class MyEntity extends Model
{
    public string $table = 'my_entities';
    public string|array $primary_key = 'entity_id';
}
```

#### 2) Create an entity

```php
$entity = MyEntity::create([
    'column_1' => 'value of column 1',
    'column_2' => 'value of column 2',
]);

echo $entity->column_1; // value of column 1
```

#### 3) Find by id

```php
$entity = MyEntity::find(42);
```

#### 4) Update and save

```php
$entity->column_1 = 'My new value';
$entity->save();
```

#### 5) Delete

```php
$entity->delete();
```

---

### Querying

Use the fluent builder returned by `Model::builder()` or convenience helpers like `where()`.

- **All rows**

```php
$entities = MyEntity::findAll();
```

- **Basic filter**

```php
$entities = MyEntity::where('column_2', '=', 42)->get();
```

- **First row only**

```php
$entity = MyEntity::findFirst(['=' => ['entity_id', 42]]);
```

- **Order, offset, limit**

```php
$entities = MyEntity::builder()
    ->where(['=' => ['status', 'ACTIVE']])
    ->order('created_at', 'DESC')
    ->offset(0)
    ->limit(20)
    ->get();
```

- **Fluent and readable complex filters with `QueryBuilder` (recommended)**

```php
use Pachyderm\Orm\QueryBuilder;

$filters = (new QueryBuilder())
    ->where('status', '=', 'ACTIVE')
    ->where('score', '>', 10)
    ->orWhere('name', 'LIKE', '%john%')
    ->orWhere('type', 'IN', ['A', 'B']);

$entities = MyEntity::builder()
    ->where($filters)
    ->get();
```

You can still pass the nested array format if needed, but `QueryBuilder` exists to avoid writing those arrays by hand.

#### Nested groups with QueryBuilder (OR groups)

Group conditions by composing QueryBuilders and passing them to `where()`/`orWhere()`:

```php
use Pachyderm\Orm\QueryBuilder;

// (name LIKE '%john%' OR name LIKE '%jane%')
$nameOr = (new QueryBuilder())
    ->where('name', 'LIKE', '%john%')
    ->orWhere('name', 'LIKE', '%jane%');

// (type IN ('A','B') OR score > 90)
$typeOrScore = (new QueryBuilder())
    ->where('type', 'IN', ['A', 'B'])
    ->orWhere('score', '>', 90);

// status = 'ACTIVE' AND (name... OR name...) AND (type... OR score...)
$filters = (new QueryBuilder())
    ->where('status', '=', 'ACTIVE')
    ->where($nameOr)
    ->where($typeOrScore);

$results = MyEntity::builder()->where($filters)->get();
```

You can also mix nested groups with top-level OR:

```php
$group = (new QueryBuilder())
    ->where('country', '=', 'FR')
    ->orWhere('country', '=', 'DE');

$filters = (new QueryBuilder())
    ->where('status', '=', 'ACTIVE')
    ->orWhere($group); // status = 'ACTIVE' OR (country = 'FR' OR country = 'DE')

$results = MyEntity::builder()->where($filters)->get();
```

---

### Relations

For convenient relationship fetching and JSON‑ready serialization, use the trait `Pachyderm\Orm\Traits\RelationshipFetcherModel` in your models. It lets you declare relationship methods and include/exclude them when converting to arrays.

- Add the trait to your model

```php
use Pachyderm\Orm\Traits\RelationshipFetcherModel;

class Order extends Model
{
    use RelationshipFetcherModel;

    public string $table = 'orders';
    public string $primary_key = 'id';

    // Default related fields to include when serializing
    public array $additionalFields = ['customer', 'items'];

    // belongsTo
    public function customer(): Customer
    {
        return Customer::find($this->customer_id);
    }

    // hasMany (return a builder; it will be executed automatically)
    public function items(): SQLBuilder
    {
        return OrderItem::builder()->where('order_id', '=', $this->id);
    }
}
```

- Serialize with relations

```php
$order = Order::find(1001);
$array = $order->toArray(); // includes id, fields, plus customer and items
```

- Override included fields at runtime with `with()` and `without()`

```php
$order = Order::find(1001)
    ->with('customer')       // add a field
    ->without('items');      // remove a field

$result = $order->toArray();
```

- Control nested depth globally (prevents infinite recursion)

```php
use Pachyderm\Orm\Traits\RelationshipFetcherModel as RFM;

Order::maxDepth(2); // default is 1
```

- Supported return types in relationship methods
  - Model instance (must implement `toArray()`) – will be serialized recursively
  - `Pachyderm\Orm\Collection` – each item is serialized
  - `Pachyderm\Orm\SQLBuilder` – executed via `get()` then serialized
  - String – returned as-is
  - Any object with `reference()` – the return value of `reference()` is used
  - Plain arrays – items are serialized recursively

- Caching: during a single serialization pass, results of relationship methods are cached per model id to avoid duplicate queries.

Tip: For simple foreign keys, returning the related `Model::find(...)` is fine. For lists, prefer returning an `SQLBuilder` to defer execution until serialization.

---

### Relations via inheritance (optional)

You can model table inheritance by declaring a parent Model on the child using the `inherit` property. Shared fields are read from the parent table and joined automatically.

```php
class ParentEntity extends Model
{
    public string $table = 'parents';
    public string $primary_key = 'id';

    // Return list of field names present on this table
    public function getFields(): array
    {
        return ['name', 'email'];
    }
}

class ChildEntity extends Model
{
    public string $table = 'children';
    public string $primary_key = 'id';

    // either an instance or a class-string
    public string $inherit = ParentEntity::class;
}

// Querying ChildEntity will auto-join ParentEntity and project its fields
$items = ChildEntity::findAll();
```

When saving/creating `ChildEntity`, parent fields are forwarded to the parent model accordingly.

---

### Scopes

Scopes are predefined filters attached to a model.

```php
class Orders extends Model
{
    public string $table = 'orders';
    public string $primary_key = 'id';

    public function boot(): void
    {
        $this->addScope('onlyPaid', ['=' => ['status', 'PAID']]);
    }
}

$paid = Orders::builder()->where(['=' => ['country', 'FR']])->get();
// or rely on the scope via builder(true) by default
```

---

### Pagination helper

`Model::pagination(array $params)` consumes typical REST query params and returns a configured `SQLBuilder`:

- `page`, `size` – pagination
- `order` – string or array of "field,ASC|DESC"
- `filter` – optional nested array understood by `QueryBuilder` (you can avoid this by composing a `QueryBuilder` yourself and calling `builder()->where($qb)` instead)
- remaining key/values are applied as `=` filters; passing `'NULL'` uses `IS NULL`

```php
$builder = MyEntity::pagination([
    'page' => 3,
    'size' => 25,
    'order' => ['created_at,DESC', 'id,ASC'],
]);
$collection = $builder->get();
```

---

### Testing

Run the test suite:

```bash
composer test
```

---

### Troubleshooting

- For composite primary keys, set `public array $primary_key = ['col_a', 'col_b'];` on the model.
- Prefer `QueryBuilder` for complex filters to avoid writing nested arrays manually.

---

### Advanced: Custom DB engine (optional)

By default, the ORM uses `\Pachyderm\Db`. When running outside Pachyderm or in tests, you may set a custom engine (must implement the same static API):

```php
use Pachyderm\Orm\Model;

Model::setDbEngine(\App\Infrastructure\MyDb::class);
```

Required static methods on a custom engine class:
- `public static function query(string $sql): array`
- `public static function insert(string $table, array $data): string|int|null`
- `public static function update(string $table, array $data, array $where): void`
- `public static function delete(string $table, string|array $primaryKey, string|int|array $id): void`
- `public static function escape(mixed $value): string|int|float|null`

---

### License

This project is licensed under the MIT License. See `LICENSE` for details.
