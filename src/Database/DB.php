<?php

namespace Pocketframe\Database;

use Pocketframe\Container\App;
use Pocketframe\Container\Container;

class DB
{
    private static $instance = null;
    private static $container = null;

    public static function setContainer(Container $container)
    {
        self::$container = $container;
    }

    public static function getInstance()
    {
        if (!self::$instance) {
            if (!self::$container) {
                throw new \Exception("Container not set");
            }
            self::$instance = self::$container->get(Database::class);
        }
        return self::$instance;
    }


    public static function __callStatic($method, $args)
    {
        $instance = self::getInstance();
        return call_user_func_array([$instance, $method], $args);
    }
}
