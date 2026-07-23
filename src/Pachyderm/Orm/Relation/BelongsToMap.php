<?php

namespace Pachyderm\Orm\Relation;

use Pachyderm\Orm\Model;

/**
 * Discovers and caches BelongsTo attributes on model classes.
 */
final class BelongsToMap
{
    /** @var array<class-string, array<string, array{related: class-string<Model>, foreignKey: string, ownerKey: string, property: string}>> */
    private static array $cache = [];

    /**
     * @param class-string<Model> $modelClass
     * @return array<string, array{related: class-string<Model>, foreignKey: string, ownerKey: string, property: string}>
     */
    public static function for(string $modelClass): array
    {
        if (isset(self::$cache[$modelClass])) {
            return self::$cache[$modelClass];
        }

        $map = [];
        $ref = new \ReflectionClass($modelClass);
        foreach ($ref->getProperties() as $property) {
            $attrs = $property->getAttributes(BelongsTo::class);
            if ($attrs === []) {
                continue;
            }

            /** @var BelongsTo $attr */
            $attr = $attrs[0]->newInstance();
            if (!is_subclass_of($attr->related, Model::class)) {
                throw new \InvalidArgumentException(
                    'BelongsTo related class must extend Model: ' . $attr->related
                );
            }

            $propName = $property->getName();
            $related = new $attr->related();
            if (is_array($related->primary_key)) {
                throw new \InvalidArgumentException(
                    'BelongsTo related model must use a string primary_key: ' . $attr->related
                );
            }

            $foreignKey = $attr->foreignKey ?? $propName;
            $ownerKey = $attr->ownerKey ?? $related->primary_key;
            $name = $attr->name ?? (str_ends_with($propName, '_id')
                ? substr($propName, 0, -3)
                : $propName);

            $map[$name] = [
                'related' => $attr->related,
                'foreignKey' => $foreignKey,
                'ownerKey' => $ownerKey,
                'property' => $propName,
            ];
        }

        return self::$cache[$modelClass] = $map;
    }

    public static function clearCache(): void
    {
        self::$cache = [];
    }
}
