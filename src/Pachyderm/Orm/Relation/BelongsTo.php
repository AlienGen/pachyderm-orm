<?php

namespace Pachyderm\Orm\Relation;

use Pachyderm\Orm\Model;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class BelongsTo
{
    /**
     * @param class-string<Model> $related
     */
    public function __construct(
        public readonly string $related,
        public readonly ?string $foreignKey = null,
        public readonly ?string $ownerKey = null,
        public readonly ?string $name = null,
    ) {
    }
}
