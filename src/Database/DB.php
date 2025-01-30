<?php

namespace Core\Database;

use Core\Container\App;

class DB
{
    private static $instance = null;

    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = App::resolve(Database::class);
        }
        return self::$instance;
    }


    public static function __callStatic($method, $args)
    {
        $instance = self::getInstance();
        return call_user_func_array([$instance, $method], $args);
    }
}
