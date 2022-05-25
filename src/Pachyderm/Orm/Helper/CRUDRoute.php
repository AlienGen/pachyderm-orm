<?php

namespace Pachyderm\Orm\Helper;

use Pachyderm\Orm\Model;

class CRUDRoute
{
    protected static $dispatcher = NULL;

    public static function init($dispatcher)
    {
        self::$dispatcher = $dispatcher;
    }

    public static function route($name, Model $model, $prefix = '/')
    {
        $plural = self::plural($name);

        /**
         * Listing
         */
        self::$dispatcher->get($prefix . $plural, function () use ($model, $plural) {
            $entities = $model::pagination($_GET);
            return [200, ['success' => true, $plural => $entities]];
        });

        /**
         * Read one
         */
        self::$dispatcher->get($prefix . $name . '/{id}', function ($id) use ($model, $name) {
            $entity = $model::find($id);
            return [200, ['success' => true, $name => $entity]];
        });

        /**
         * Create
         */
        self::$dispatcher->post($prefix . $name, function ($data) use ($model, $name) {
            $entity = $model::create($data);
            return [201, ['success' => true, $name => $entity]];
        });

        /**
         * Update
         */
        self::$dispatcher->put($prefix . $name . '/{id}', function ($id, $data) use ($model, $name) {
            $entity = $model::find($id);
            $entity->set($data);
            $entity->save();
            return [201, ['success' => true, $name => $entity]];
        });

        /**
         * Delete
         */
        self::$dispatcher->delete($prefix . $name . '/{id}', function ($id) use ($model, $name) {
            $entity = $model::find($id);
            $entity->delete();
            return [201, ['success' => true]];
        });
    }

    private static function plural($name)
    {
        return $name;
    }
}
