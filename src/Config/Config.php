<?php

declare(strict_types=1);

namespace Pocketframe\Config;

class Config
{
    protected static array $configs = [];

    /**
     * Load all configuration files once.
     */
    protected static function load(): void
    {
        if (!empty(self::$configs)) {
            return;
        }

        $configPath = base_path('config/');

        if (!is_dir($configPath)) {
            throw new \Exception("Config directory does not exist: $configPath");
        }

        foreach (glob($configPath . '*.php') as $file) {
            $name = basename($file, '.php');
            self::$configs[$name] = require $file;
        }
    }

    /**
     * Get a configuration value using dot notation.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        self::load();

        // Use dot notation to traverse the array
        $keys = explode('.', $key);
        $value = self::$configs;

        foreach ($keys as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}

