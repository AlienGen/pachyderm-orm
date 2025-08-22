<?php
namespace Pachyderm\Orm\Traits;

use Pachyderm\Orm\Collection;
use Pachyderm\Orm\SQLBuilder;
use Pachyderm\Service;

class DeepDepthCounter
{
    private static $_currentDepth = 0;

    public static function increment()
    {
        self::$_currentDepth++;
    }

    public static function decrement()
    {
        self::$_currentDepth--;
    }

    public static function get()
    {
        return self::$_currentDepth;
    }
}

class RelationshipInternalCache
{
    private static $cache = [];

    public static function has(string $key): bool
    {
        return isset(self::$cache[$key]);
    }

    public static function set(string $key, mixed $value)
    {
        self::$cache[$key] = $value;
    }

    public static function get(string $key): mixed
    {
        return self::$cache[$key] ?? null;
    }

    public static function clear()
    {
        self::$cache = [];
    }
}

trait RelationshipFetcherModel
{
    private $_withFields = [];
    private $_withoutFields = [];

    private static $maxDepth = 1;

    public static function maxDepth(int $depth)
    {
        self::$maxDepth = $depth;
    }

    public function with($field)
    {
        $this->_withFields[] = $field;
        return $this;
    }

    public function without($field)
    {
        $this->_withoutFields[] = $field;
        return $this;
    }

    public function toArray(): array
    {
        if (DeepDepthCounter::get() > self::$maxDepth - 1) {
            return parent::toArray();
        }

        $allFields = $this->additionalFields;
        if (count($this->_withFields) > 0) {
            $allFields = array_merge($allFields, $this->_withFields);
        }
        if (count($this->_withoutFields) > 0) {
            $allFields = array_diff($allFields, $this->_withoutFields);
        }

        DeepDepthCounter::increment();
        $array = parent::toArray();
        foreach ($allFields as $field) {
            // If the field is an existing field, just add it to the array
            if (!method_exists($this, $field)) {
                $array[$field] = $this->$field;
                continue;
            }

            $cacheKey = get_called_class() . '::' . $field . '::' . $this->getId();
            if (RelationshipInternalCache::has($cacheKey)) {
                $array[$field] = RelationshipInternalCache::get($cacheKey);
                continue;
            }

            // Retrieve the entity from the relationship
            $relationship = $this->$field();
            $value = $this->extractValue($relationship);
            RelationshipInternalCache::set($cacheKey, $value);
            $array[$field] = $value;
        }
        DeepDepthCounter::decrement();
        return $array;
    }

    private function extractValue(mixed $relationship): mixed
    {
        if ($relationship === null) {
            return null;
        }

        if (is_string($relationship)) {
            return $relationship;
        }

        if (is_object($relationship) && method_exists($relationship, 'reference')) {
            return $relationship->reference();
        }

        if ($relationship instanceof Collection) {
            $array = [];
            foreach ($relationship as $item) {
                $array[] = $this->extractValue($item);
            }
            return $array;
        }

        if (is_object($relationship) && method_exists($relationship, 'toArray')) {
            return $relationship->toArray();
        }

        if ($relationship instanceof SQLBuilder) {
            return $relationship->get()->toArray();
        }

        if (is_array($relationship)) {
            $array = [];
            foreach ($relationship as $item) {
                $array[] = $this->extractValue($item);
            }
            return $array;
        }

        return $relationship;
    }
}
