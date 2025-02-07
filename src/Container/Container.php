<?php

namespace Pocketframe\Container;

class Container
{
    protected $bindings = [];

    public function bind($key, $resolver)
    {
        $this->bindings[$key] = $resolver;
    }

    public function get($key)
    {
        if (!isset($this->bindings[$key])) {
            return new $key();
        }

        $resolver = $this->bindings[$key];
        return $resolver();
    }
}
