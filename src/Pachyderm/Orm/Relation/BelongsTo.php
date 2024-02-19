<?php

namespace Pachyderm\Orm\Relation;

use Pachyderm\Orm\Model;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class BelongsTo {
    private Model $model;

    public function __construct(Model $model) {
        $this->model = $model;
    }
}
