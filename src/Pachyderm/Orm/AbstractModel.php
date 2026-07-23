<?php

namespace Pachyderm\Orm;

use Pachyderm\Orm\Relation\BelongsToMap;
use Pachyderm\Utils\IterableObjectSet;

abstract class AbstractModel extends IterableObjectSet
{
    public null|string $deleted_at = null;
    public null|string $updated_at = null;
    public null|string $created_at = null;

    /**
     * Snapshot of last persisted attribute values for dirty tracking.
     * @var array<string, mixed>
     */
    protected array $_original = [];

    /**
     * Overridable set to specify the fields during the inheritance.
     */
    protected array $_fields = array();

    /**
     * Mass-assignment allowlist. When non-empty, only these keys are accepted by set()/create().
     * @var list<string>
     */
    protected array $fillable = [];

    /**
     * Mass-assignment denylist. Used when $fillable is empty. Use ['*'] to deny all.
     * @var list<string>
     */
    protected array $guarded = [];

    /**
     * Account constructor.
     * @param $data array
     */
    public function __construct(iterable $data = array())
    {
        $this->forceSet($data);
        $this->syncOriginal();
    }

    public function getFields(): array
    {
        if (empty($this->_fields)) {
            throw new \Exception('The array property "_fields" must be defined!');
        }
        return $this->_fields;
    }

    /**
     * Columns to SELECT for a BelongsTo eager join (owner key always included).
     *
     * @return list<string>
     */
    public function getSelectFieldsForRelation(string $mustInclude): array
    {
        if (empty($this->_fields)) {
            return [$mustInclude];
        }

        $fields = [];
        foreach ($this->_fields as $f) {
            if (is_string($f)) {
                $fields[] = $f;
            }
        }

        if (!in_array($mustInclude, $fields, true)) {
            $fields[] = $mustInclude;
        }

        return $fields;
    }

    /**
     * Mass-assign attributes (respects $fillable / $guarded and strips primary keys).
     */
    public function set(iterable $data = array()): void
    {
        foreach ($this->filterMassAssignment($data, true) as $k => $v) {
            $this->$k = $v;
        }
    }

    /**
     * Assign attributes without mass-assignment filtering (hydration / refresh).
     */
    public function forceSet(iterable $data = array()): void
    {
        foreach ($data as $k => $v) {
            $this->$k = $v;
        }
    }

    /**
     * @param iterable<string, mixed> $data
     * @return array<string, mixed>
     */
    public function filterMassAssignment(iterable $data, bool $stripPrimaryKey = false): array
    {
        $filtered = [];
        foreach ($data as $k => $v) {
            $filtered[$k] = $v;
        }

        $filtered = $this->stripAssociations($filtered);

        if (!empty($this->fillable)) {
            $allowed = array_flip($this->fillable);
            $filtered = array_intersect_key($filtered, $allowed);
        } elseif (!empty($this->guarded)) {
            if (in_array('*', $this->guarded, true)) {
                $filtered = [];
            } else {
                foreach ($this->guarded as $guardedKey) {
                    unset($filtered[$guardedKey]);
                }
            }
        }

        if ($stripPrimaryKey && isset($this->primary_key) && !empty($this->primary_key)) {
            if (is_array($this->primary_key)) {
                foreach ($this->primary_key as $pk) {
                    unset($filtered[$pk]);
                }
            } else {
                unset($filtered[$this->primary_key]);
            }
        }

        return $filtered;
    }

    /**
     * @return list<string>
     */
    public function associationPropertyNames(): array
    {
        return BelongsToMap::associationPropertyNames(static::class);
    }

    /**
     * Remove BelongsTo association keys and Model instances from a data bag.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function stripAssociations(array $data): array
    {
        foreach ($this->associationPropertyNames() as $name) {
            unset($data[$name]);
        }
        foreach ($data as $key => $value) {
            if ($value instanceof Model) {
                unset($data[$key]);
            }
        }
        return $data;
    }

    /**
     * Copy current attributes into the original snapshot.
     */
    public function syncOriginal(): void
    {
        $this->_original = $this->_data;
    }

    /**
     * @return array<string, mixed>|mixed|null
     */
    public function getOriginal(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->_original;
        }

        return array_key_exists($key, $this->_original) ? $this->_original[$key] : null;
    }

    public function isDirty(?string $key = null): bool
    {
        if ($key !== null) {
            $current = array_key_exists($key, $this->_data) ? $this->_data[$key] : null;
            $original = array_key_exists($key, $this->_original) ? $this->_original[$key] : null;
            return $current !== $original;
        }

        return $this->getChanges() !== [];
    }

    /**
     * @return array<string, mixed>
     */
    public function getChanges(): array
    {
        $changes = [];
        $keys = array_unique(array_merge(array_keys($this->_original), array_keys($this->_data)));
        foreach ($keys as $key) {
            $current = array_key_exists($key, $this->_data) ? $this->_data[$key] : null;
            $original = array_key_exists($key, $this->_original) ? $this->_original[$key] : null;
            if ($current !== $original) {
                $changes[$key] = $current;
            }
        }
        return $this->stripAssociations($changes);
    }

    public function getId(): string|iterable|null
    {
        if (empty($this->primary_key)) {
            throw new \Exception('The property "primary_key" must be defined!');
        }

        // If the primary key is multiple
        if (is_array($this->primary_key)) {
            $id = array();
            foreach ($this->primary_key as $pk) {
                if (!isset($this->_data[$pk])) {
                    return NULL;
                }
                $id[] = $this->_data[$pk];
            }
            return $id;
        }

        if (!isset($this->_data[$this->primary_key])) {
            return NULL;
        }

        // Single primary key.
        return $this->_data[$this->primary_key];
    }

    /**
     * Overridable method to add more "virtual" items in the set.
     * @deprecated The model is iterable.
     */
    public function toArray(): array
    {
        return $this->_data;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
