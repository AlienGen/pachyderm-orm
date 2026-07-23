<?php

namespace Pachyderm\Orm\Helper;

use Pachyderm\Dispatcher;
use Pachyderm\Orm\Model;

class CRUDRoute
{
    protected static $dispatcher = NULL;

    public static function init(Dispatcher $dispatcher): void
    {
        self::$dispatcher = $dispatcher;
    }

    /**
     * Register CRUD routes for a model.
     *
     * WARNING: Without an $authorize callback and without model $fillable/$guarded,
     * these endpoints are fully open and subject to mass assignment.
     *
     * @param callable|null $authorize Optional gate: fn(string $action, mixed ...$args): bool
     *                                  Actions: list|read|create|update|delete
     */
    public static function route(string $name, string $model, string $prefix = '/', ?callable $authorize = null): void
    {
        if (self::$dispatcher === NULL) {
            throw new \Exception('CRUDRoute must be initialized!');
        }

        if (!is_subclass_of($model, Model::class)) {
            throw new \Exception('"model" parameter must be a Model class!');
        }

        $plural = self::pluralize($name);
        $deny = [403, ['success' => false]];

        /**
         * Listing
         */
        self::$dispatcher->get($prefix . $plural, function () use ($model, $authorize, $deny) {
            if ($authorize !== null && !$authorize('list')) {
                return $deny;
            }
            $entities = $model::pagination($_GET)->get();
            return [200, ['success' => true, 'items' => $entities, 'total' => $entities->totalRecords()]];
        });

        /**
         * Read one
         */
        self::$dispatcher->get($prefix . $name . '/{id}', function ($id) use ($model, $name, $authorize, $deny) {
            if ($authorize !== null && !$authorize('read', $id)) {
                return $deny;
            }
            $entity = $model::find($id);
            return [200, ['success' => true, $name => $entity]];
        });

        /**
         * Create
         */
        self::$dispatcher->post($prefix . $name, function ($data) use ($model, $name, $authorize, $deny) {
            if ($authorize !== null && !$authorize('create', $data)) {
                return $deny;
            }
            $entity = $model::create($data);
            return [201, ['success' => true, $name => $entity]];
        });

        /**
         * Update
         */
        self::$dispatcher->put($prefix . $name . '/{id}', function ($id, $data) use ($model, $name, $authorize, $deny) {
            if ($authorize !== null && !$authorize('update', $id, $data)) {
                return $deny;
            }
            $entity = $model::find($id);
            $entity->set($data);
            $entity->save();
            return [200, ['success' => true, $name => $entity]];
        });

        /**
         * Delete
         */
        self::$dispatcher->delete($prefix . $name . '/{id}', function ($id) use ($model, $name, $authorize, $deny) {
            if ($authorize !== null && !$authorize('delete', $id)) {
                return $deny;
            }
            $entity = $model::find($id);
            $entity->delete();
            return [200, ['success' => true]];
        });
    }

    private static function pluralize($name): string
    {
        $last_letter = strtolower($name[strlen($name) - 1]);
        if ($last_letter == 's') {
            return $name . 'es';
        }
        if ($last_letter == 'y') {
            return substr($name, 0, -1) . 'ies';
        }
        return $name . 's';
    }
}
