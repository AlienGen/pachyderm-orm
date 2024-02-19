<?php

namespace Stub;

class DbMock {

    private static $_storage = array();

    public static function escape($value) {
        return $value;
    }

    public static function insert($table, $data) {

        if(!isset(self::$_storage[$table])) {
            self::$_storage[$table] = [];
        }

        $id = count(self::$_storage[$table]) + 1;

        self::$_storage[$table][$id] = $data;

        return $id;
    }

    public static function query($sql) {

        $table = reset(self::$_storage);
        if($sql == 'SELECT FOUND_ROWS() AS total;') {
            return [
                ['total' => count($table)]
            ];
        }

        $object = reset($table);

        return [
            $object
        ];
    }
}
