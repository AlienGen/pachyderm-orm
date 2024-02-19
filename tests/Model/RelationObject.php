<?php

namespace Model;

use Pachyderm\Orm\Model;
use Pachyderm\Orm\Relation\BelongsTo;

class RelationObject extends Model {

    public $table = 'relation_object';
    public $primary_key = 'relation_object_id';

    #[BelongsTo(SimpleObject::class)]
    public $simple_id;
}
