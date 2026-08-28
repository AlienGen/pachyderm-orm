<?php

namespace Model;

use Pachyderm\Orm\Model;

class CompositeKeyObject extends Model
{
    public $table = 'composite_key_object';
    public $primary_key = ['left_id', 'right_id'];
}
