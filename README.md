## Pachyderm ORM

A lightweight ORM for the [Pachyderm](https://github.com/AlienGen/Pachyderm) micro-framework.

- **Models** — map tables to PHP classes
- **Fluent queries** — filter, join, order, paginate
- **BelongsTo relations** — load related rows with a LEFT JOIN
- **Collections** — iterable results with a total count
- **Mass assignment guards** — optional `$fillable` / `$guarded`

---

### Table of Contents

- [Installation](#installation)
- [Quick start](#quick-start)
- [Querying](#querying)
  - [Nested groups with QueryBuilder](#nested-groups-with-querybuilder)
  - [EXISTS, subqueries, and aggregates](#exists-subqueries-and-aggregates)
- [Relations](#relations)
  - [BelongsTo](#belongsto)
  - [Serializing relations to arrays (optional)](#serializing-relations-to-arrays-optional)
- [Table inheritance (optional)](#table-inheritance-optional)
- [Scopes](#scopes)
- [Pagination helper](#pagination-helper)
- [Mass assignment](#mass-assignment)
- [CRUD routes](#crud-routes)
- [Testing](#testing)
- [Troubleshooting](#troubleshooting)
- [Custom DB engine](#custom-db-engine)
- [License](#license)

---

### Installation

```bash
composer require aliengen/pachyderm-orm
```

---

### Quick start

#### 1) Declare a model

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

#### 2) Create

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

Use `Model::builder()` or helpers like `where()`.

```php
// All rows (default limit applies via findAll)
$entities = MyEntity::findAll();

// Filter
$entities = MyEntity::where('column_2', '=', 42)->get();

// First match
$entity = MyEntity::findFirst(['=' => ['entity_id', 42]]);

// Order, offset, limit
$entities = MyEntity::builder()
    ->where(['=' => ['status', 'ACTIVE']])
    ->order('created_at', 'DESC')
    ->offset(0)
    ->limit(20)
    ->get();
```

For complex filters, prefer `QueryBuilder` over nested arrays:

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

#### Nested groups with QueryBuilder

Compose builders to group `AND` / `OR` conditions:

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

$filters = (new QueryBuilder())
    ->where('status', '=', 'ACTIVE')
    ->where($nameOr)
    ->where($typeOrScore);

$results = MyEntity::builder()->where($filters)->get();
```

When a nested `QueryBuilder` is passed into a parent that already has an owner table (the root filters on `SQLBuilder`, or another builder after `prepend`), unqualified column names in the nested tree inherit that table prefix — the same rule as direct `where('col', ...)`. Already-qualified names (`other.col`) are left unchanged. Prefer nested builders over bare columns when combining compound filters with `with()` / JOINs so shared column names stay unambiguous.

Top-level OR works the same way:

```php
$group = (new QueryBuilder())
    ->where('country', '=', 'FR')
    ->orWhere('country', '=', 'DE');

$filters = (new QueryBuilder())
    ->where('status', '=', 'ACTIVE')
    ->orWhere($group); // ACTIVE OR (FR OR DE)

$results = MyEntity::builder()->where($filters)->get();
```

#### EXISTS, subqueries, and aggregates

Use these on the query builder when you need “rows that have related rows”, nested `IN` selects, or `GROUP BY` summaries.

They are **not** available through the pagination `filter` query parameter (that path only supports simple filters).

```php
// Orders that have at least one line item with qty > 0
$orders = Order::builder()->whereExists(
    OrderItem::builder()
        ->whereColumn('order_items.order_id', '=', 'orders.id')
        ->where('qty', '>', 0)
)->get();

// Users with no matching flag row
User::builder()->whereNotExists(
    Flag::builder()->whereColumn('flags.user_id', '=', 'users.id')
)->get();

// IN (subquery)
User::builder()->where(
    'id',
    'IN',
    Order::builder()->select('user_id')->where('status', '=', 'PAID')
)->get();

// Aggregates
Order::builder()
    ->select('status')
    ->selectMax('amount', 'max_amount') // also: selectMin, selectSum, selectAvg, selectCount
    ->groupBy('status')
    ->having('max_amount', '>', 100)
    ->get();
```

With `groupBy()` or aggregates, list the columns you need in `select()` yourself (the builder will not add `table.*` automatically).

---

### Relations

#### BelongsTo

**BelongsTo** is a many-to-one link: each order has one customer. You declare a typed property for the related model, and tell the ORM which foreign-key column points to it.

1. Add a nullable typed property (the association).
2. Annotate it with `#[BelongsTo(foreignKey: '...')]`.
3. **Do not** declare the foreign-key column as a PHP property — treat it like any other table column (`$order->customer_id`).
4. Load it explicitly with `builder()->with('customer')` (a SQL `LEFT JOIN`). Without `with()`, `$order->customer` stays `null`.

```php
use Pachyderm\Orm\Model;
use Pachyderm\Orm\QueryBuilder;
use Pachyderm\Orm\Relation\BelongsTo;

class Order extends Model
{
    public string $table = 'orders';
    public string $primary_key = 'id';

    // Related type is taken from the property; foreignKey is the column on orders
    #[BelongsTo(foreignKey: 'customer_id')]
    public ?Customer $customer = null;

    // Or be explicit:
    // #[BelongsTo(Customer::class, foreignKey: 'customer_id', ownerKey: 'id')]
}

class Customer extends Model
{
    public string $table = 'customers';
    public string $primary_key = 'id';

    // Columns to bring back when the relation is eager-loaded
    protected array $_fields = ['id', 'name', 'email'];
}

$order = Order::builder()->with('customer')->first();

$order->customer;     // Customer instance, or null if no match
$order->customer_id;  // foreign-key value from the orders row

// Filter on the joined table (alias = relation name)
Order::builder()
    ->with('customer')
    ->where('customer.name', '=', 'Ada')
    ->get();

// Owner-table columns in nested QueryBuilders stay qualified under with()
$open = (new QueryBuilder())
    ->where('closed_at', 'IS NULL')
    ->orWhere('status', '=', 'OPEN');

Order::builder()
    ->where($open)
    ->with('customer')
    ->get();
```

When you `save()` or `create()`, only column values are written. The related `Customer` object is never inserted into a column — change `$order->customer_id` if you need to update the link.

Many-to-many is not supported.

#### Serializing relations to arrays (optional)

`RelationshipFetcherModel` is a separate helper for building arrays/JSON. It is **not** the same as BelongsTo eager loading.

- Prefer `#[BelongsTo]` + `builder()->with(...)` to **load** many-to-one data in one query.
- Use this trait when you need `toArray()` to include related data (including has-many via methods).
- This trait’s `with()` / `without()` only affect serialization — they do **not** add SQL joins.

```php
use Pachyderm\Orm\SQLBuilder;
use Pachyderm\Orm\Traits\RelationshipFetcherModel;

class Order extends Model
{
    use RelationshipFetcherModel;

    public string $table = 'orders';
    public string $primary_key = 'id';

    public array $additionalFields = ['customer', 'items'];

    public function customer(): Customer
    {
        return Customer::find($this->customer_id);
    }

    public function items(): SQLBuilder
    {
        return OrderItem::builder()->where('order_id', '=', $this->id);
    }
}

$order = Order::find(1001);
$array = $order->toArray(); // includes customer and items

$array = Order::find(1001)
    ->with('customer')   // include in toArray()
    ->without('items')   // omit from toArray()
    ->toArray();

Order::maxDepth(2); // limit nested serialization (default 1)
```

Relationship methods may return a model, a `Collection`, an `SQLBuilder` (run automatically), a string, an object with `reference()`, or a plain array.

---

### Table inheritance (optional)

Share columns across a parent table and a child table with `inherit`. Queries on the child join the parent automatically.

```php
class ParentEntity extends Model
{
    public string $table = 'parents';
    public string $primary_key = 'id';

    public function getFields(): array
    {
        return ['name', 'email'];
    }
}

class ChildEntity extends Model
{
    public string $table = 'children';
    public string $primary_key = 'id';

    public string $inherit = ParentEntity::class;
}

$items = ChildEntity::findAll();
```

On create/save, parent fields are written to the parent table.

---

### Scopes

Scopes are default filters registered in `boot()`. They apply whenever you use `builder()` (unless you pass `builder(false)`).

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

$paidInFrance = Orders::builder()
    ->where(['=' => ['country', 'FR']])
    ->get();
```

---

### Pagination helper

`Model::pagination($params)` maps common list query params to a builder:

| Param | Meaning |
|--------|---------|
| `page`, `size` | Offset / limit |
| `order` | `"field,ASC"` or `"field,DESC"` (string or list) |
| `filter` | Serialized simple filters only (no EXISTS/subqueries) |
| other keys | Equality filters; value `'NULL'` becomes `IS NULL` |

```php
$collection = MyEntity::pagination([
    'page' => 3,
    'size' => 25,
    'order' => ['created_at,DESC', 'id,ASC'],
])->get();
```

Column names and sort directions are validated. Invalid input throws `\InvalidArgumentException`.

---

### Mass assignment

Control which keys `create()` and `set()` accept:

- Non-empty `$fillable` — only those keys
- Else non-empty `$guarded` — everything except those keys (`['*']` blocks all)
- Else — all keys (default)

Scalar primary keys are stripped on `create()` and `set()`. Composite primary keys (relation tables) are kept on `create()` so they can be inserted, and stripped on `set()` so identity cannot be changed. Use `forceSet()` only for trusted hydration (for example after a DB read).

```php
class User extends Model
{
    public string $table = 'users';
    public string $primary_key = 'id';

    protected array $fillable = ['name', 'email'];
    // or: protected array $guarded = ['is_admin'];
}
```

---

### CRUD routes

`CRUDRoute` wires list / read / create / update / delete endpoints. Treat it as a scaffold: **add authorization and `$fillable` before production use**.

```php
use Pachyderm\Orm\Helper\CRUDRoute;

CRUDRoute::init($dispatcher);

CRUDRoute::route('user', User::class, '/api/', function (string $action, mixed ...$args): bool {
    // Return false → HTTP 403
    return /* allowed? */;
});
```

---

### Testing

```bash
composer test
```

---

### Troubleshooting

- **Composite primary keys** — `public array $primary_key = ['col_a', 'col_b'];`
- **Complex filters** — build them with `QueryBuilder` instead of hand-written nested arrays
- **`$order->customer` is always null** — call `->with('customer')` on the builder; associations are not loaded by default
- **BelongsTo join missing columns** — list them on the related model via `protected array $_fields` (or override `getFields()`)
- **Do not declare `public $customer_id`** next to `#[BelongsTo]` — keep the FK as a normal column attribute so it loads and saves correctly

---

### Custom DB engine

By default the ORM uses `\Pachyderm\Db`. For tests or other environments:

```php
use Pachyderm\Orm\Model;

Model::setDbEngine(\App\Infrastructure\MyDb::class);
```

Required static methods:

- `query(string $sql): array`
- `insert(string $table, array $data): string|int|null`
- `update(string $table, array $data, array $where): void`
- `delete(string $table, string|array $primaryKey, string|int|array $id): void`
- `escape(mixed $value): string|int|float|null`

Optional (preferred when available):

- `queryWithParams(string $sql, array $params): array` — used instead of interpolating escaped values

---

### License

MIT. See `LICENSE`.
