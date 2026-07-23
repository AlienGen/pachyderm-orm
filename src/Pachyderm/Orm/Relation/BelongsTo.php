<?php

namespace Pachyderm\Orm\Relation;

use Pachyderm\Orm\Model;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class BelongsTo
{
    /**
     * @param class-string<Model>|null $related Related model; inferred from the property type when omitted
     * @param string|null $foreignKey Owner FK column (default: "{property}_id")
     * @param string|null $ownerKey Related primary key (default: related model's primary_key)
     */
    public function __construct(
        public readonly ?string $related = null,
        public readonly ?string $foreignKey = null,
        public readonly ?string $ownerKey = null,
    ) {
    }
}
