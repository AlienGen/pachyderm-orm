<?php

namespace Model;

use Pachyderm\Orm\Model;

class FillableObject extends Model
{
    public $table = 'fillable_object';
    public $primary_key = 'id';

    protected array $fillable = ['name', 'email'];
}
