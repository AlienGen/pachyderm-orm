<?php

namespace Stub;

class DbMock
{
    private static $_storage = array();

    /** @var list<array<string, mixed>>|null */
    private static $_nextResults = null;

    public static function escape($value)
    {
        return $value;
    }

    public static function insert($table, $data)
    {
        if (!isset(self::$_storage[$table])) {
            self::$_storage[$table] = [];
        }

        $id = count(self::$_storage[$table]) + 1;

        self::$_storage[$table][$id] = $data;

        return $id;
    }

    public static function update($table, $data, $where): void
    {
    }

    public static function delete($table, $primaryKey, $id): void
    {
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    public static function setNextResults(array $rows): void
    {
        self::$_nextResults = $rows;
    }

    public static function query($sql)
    {
        if ($sql == 'SELECT FOUND_ROWS() AS total;') {
            if (self::$_nextResults !== null) {
                return [['total' => count(self::$_nextResults)]];
            }
            $table = reset(self::$_storage);
            return [
                ['total' => is_array($table) ? count($table) : 0]
            ];
        }

        if (self::$_nextResults !== null) {
            $rows = self::$_nextResults;
            self::$_nextResults = null;
            return $rows;
        }

        $table = reset(self::$_storage);
        if (!is_array($table) || $table === []) {
            return [];
        }

        $object = reset($table);
        if ($object === false) {
            return [];
        }

        return [
            $object
        ];
    }

    public static function clear(): void
    {
        self::$_storage = [];
        self::$_nextResults = null;
    }
}
