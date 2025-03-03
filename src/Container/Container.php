<?php

namespace Pocketframe\Container;

class Container
{
    protected $bindings = [];
    protected static $instance;

    public static function setInstance(Container $instance): void
    {
        self::$instance = $instance;
    }

    public static function getInstance(): self
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function bind($key, $resolver)
    {
        $this->bindings[$key] = $resolver;
    }

    public function get($key)
    {
        if (!isset($this->bindings[$key])) {
            // If no binding exists, try to resolve the class automatically
            return $this->resolve($key);
        }

        $resolver = $this->bindings[$key];
        return $resolver();
    }

    protected function resolve($key)
    {
        $reflection = new \ReflectionClass($key);

        // Check if the class has a constructor
        if (!$reflection->getConstructor()) {
            return new $key();
        }

        // Resolve constructor parameters
        $parameters = $reflection->getConstructor()->getParameters();
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $dependency = $parameter->getType();

            // Skip if the parameter has no type hint
            if (!$dependency) {
                throw new \RuntimeException("Cannot resolve parameter '{$parameter->getName()}' in class '{$key}'");
            }

            // Handle scalar types (e.g., string, int, array)
            if ($dependency->isBuiltin()) {
                // Check if the scalar dependency is bound in the container
                if (isset($this->bindings[$parameter->getName()])) {
                    $dependencies[] = $this->bindings[$parameter->getName()]();
                } else {
                    throw new \RuntimeException("Cannot resolve scalar parameter '{$parameter->getName()}' in class '{$key}'");
                }
            } else {
                // Resolve class dependencies
                $dependencies[] = $this->get($dependency->getName());
            }
        }

        // Create a new instance with resolved dependencies
        return $reflection->newInstanceArgs($dependencies);
    }
}
