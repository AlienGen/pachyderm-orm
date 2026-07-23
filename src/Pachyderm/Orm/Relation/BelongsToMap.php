<?php

namespace Pachyderm\Orm\Relation;

use Pachyderm\Orm\Model;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionUnionType;

/**
 * Discovers and caches BelongsTo attributes on model association properties.
 */
final class BelongsToMap
{
    /**
     * @var array<class-string, array<string, array{related: class-string<Model>, foreignKey: string, ownerKey: string, property: string}>>
     */
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
            $propName = $property->getName();
            $typeRelated = self::resolveModelType($property);

            if ($typeRelated === null) {
                throw new \InvalidArgumentException(
                    'BelongsTo on ' . $modelClass . '::$' . $propName
                    . ' must be declared on a typed Model association property (e.g. public ?Customer $customer)'
                );
            }

            if ($attr->related !== null && $attr->related !== $typeRelated) {
                throw new \InvalidArgumentException(
                    'BelongsTo related class mismatch on ' . $modelClass . '::$' . $propName
                    . ': type hint ' . $typeRelated . ' vs attribute ' . $attr->related
                );
            }

            $relatedClass = $attr->related ?? $typeRelated;
            if (!is_subclass_of($relatedClass, Model::class)) {
                throw new \InvalidArgumentException(
                    'BelongsTo related class must extend Model: ' . $relatedClass
                );
            }

            $related = new $relatedClass();
            if (is_array($related->primary_key)) {
                throw new \InvalidArgumentException(
                    'BelongsTo related model must use a string primary_key: ' . $relatedClass
                );
            }

            $foreignKey = $attr->foreignKey ?? ($propName . '_id');
            $ownerKey = $attr->ownerKey ?? $related->primary_key;

            $map[$propName] = [
                'related' => $relatedClass,
                'foreignKey' => $foreignKey,
                'ownerKey' => $ownerKey,
                'property' => $propName,
            ];
        }

        return self::$cache[$modelClass] = $map;
    }

    /**
     * @param class-string<Model> $modelClass
     * @return list<string>
     */
    public static function associationPropertyNames(string $modelClass): array
    {
        return array_keys(self::for($modelClass));
    }

    public static function clearCache(): void
    {
        self::$cache = [];
    }

    /**
     * @return class-string<Model>|null
     */
    private static function resolveModelType(ReflectionProperty $property): ?string
    {
        $type = $property->getType();
        if ($type === null) {
            return null;
        }

        $candidates = [];
        if ($type instanceof ReflectionNamedType) {
            $candidates[] = $type;
        } elseif ($type instanceof ReflectionUnionType) {
            foreach ($type->getTypes() as $inner) {
                if ($inner instanceof ReflectionNamedType) {
                    $candidates[] = $inner;
                }
            }
        }

        $modelClass = null;
        foreach ($candidates as $named) {
            if ($named->isBuiltin()) {
                continue;
            }
            $name = $named->getName();
            if (!is_subclass_of($name, Model::class)) {
                continue;
            }
            if ($modelClass !== null && $modelClass !== $name) {
                throw new \InvalidArgumentException(
                    'BelongsTo property ' . $property->getDeclaringClass()->getName()
                    . '::$' . $property->getName() . ' must have a single Model type'
                );
            }
            $modelClass = $name;
        }

        return $modelClass;
    }
}
