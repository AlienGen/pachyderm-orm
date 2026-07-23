<?php

namespace Model;

use Pachyderm\Orm\Model;

class GuardedObject extends Model
{
    public $table = 'guarded_object';
    public $primary_key = 'id';

    protected array $guarded = ['is_admin', 'id'];
}
