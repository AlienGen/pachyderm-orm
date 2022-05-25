# pachyderm-orm

An ORM for the Micro PHP Framework Pachyderm

## Declare a Model

```php
<?php

namespace App\Models;

use Pachyderm\Orm\Model;

class MyEntity extends Model
{
  public $table = 'my_entities';
  public $primary_key = 'entity_id';

}
```

## Use a model

### Create

```php
$data = [
    'column_1' => 'value of column 1',
    'column_2' => 'value of column 2',
];
$entity = MyEntity::create($data);

echo 'Column 1: ', $entity->column_1;
```

### Read

#### Retrieve by id

```php
$entity_id = 42;
$entity = MyEntity::find($entity_id);

echo 'Column 1: ', $entity->column_1;
```

#### Retrieve a list of entities

```php
$entities = MyEntity::findAll();
foreach($entities AS $entity) {
    echo 'Column 1: ', $entity->column_1;
}
```

#### Retrieve a list of entities using filters

```php
$entities = MyEntity::where('column_2', '=', 42)->get();
foreach($entities AS $entity) {
    echo 'Column 1: ', $entity->column_1;
}
```

### Update

```php
$entity->column_1 = 'My new value';
$entity->save();

echo 'Column 1: ', $entity->column_1;
```

### Delete

```php
$entity->delete();
```
