<?php

namespace Pocketframe\Container;

class Container
{
  /** @var array<string, callable> */
  protected array $bindings = [];
  /** @var array<string, object> */
  protected array $instances = [];
  /**
   * @var Container
   */
  protected static $instance;

  /**
   * Set the container instance
   *
   * This method will set the container instance.
   *
   * @param Container $instance
   * @return void
   */
  public static function setInstance(Container $instance): void
  {
    self::$instance = $instance;
  }

  /**
   * Get the container instance
   *
   * This method will return the container instance.
   *
   * @return Container
   */
  public static function getInstance(): self
  {
    if (!self::$instance) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  /**
   * Bind a shared singleton instance
   *
   * This method will bind a resolver for a class or interface.
   *
   * @param string $key
   * @param callable $resolver
   * @return void
   */
  public function singleton($key, $resolver): void
  {
    $this->bind($key, $resolver, true);
  }

  /**
   * Store an existing instance as a singleton
   *
   * This method will store an existing instance as a singleton.
   *
   * @param string $key
   * @param object $instance
   * @return void
   */
  public function instance($key, $instance): void
  {
    $this->instances[$key] = $instance;
  }

  /**
   * Bind a resolver for a class or interface
   *
   * This method will bind a resolver for a class or interface.
   *
   * @param string $key
   * @param callable $resolver
   * @return void
   */
  public function bind($key, $resolver, bool $shared = false): void
  {
    $this->bindings[$key] = [
      'resolver' => $resolver,
      'shared' => $shared,
    ];
  }


  /**
   * Resolve a class or interface
   *
   * This method will automatically resolve a class or interface if no binding exists.
   * It will check if the class has a constructor and if so, it will resolve its parameters
   * and pass them to the constructor.
   *
   * @param string $key
   * @return object
   */
  public function get($key)
  {
    // Return existing singleton instance
    if (isset($this->instances[$key])) {
      return $this->instances[$key];
    }

    // Resolve binding
    $object = $this->resolveBinding($key);

    // Store singleton instance if marked as shared
    if (isset($this->bindings[$key]) && $this->bindings[$key]['shared']) {
      $this->instances[$key] = $object;
    }

    return $object;
  }

  /**
   * Resolve a binding
   *
   * This method will resolve a binding for a class or interface.
   * It will check if the class has a constructor and if so, it will resolve its parameters
   * and pass them to the constructor.
   *
   * @param string $key
   * @return object
   */
  protected function resolveBinding($key)
  {
    if (isset($this->bindings[$key])) {
      return $this->bindings[$key]['resolver']();
    }

    // Auto-resolve unbound classes
    return $this->resolve($key);
  }

  /**
   * Resolve a class or interface
   *
   * This method will automatically resolve a class or interface if no binding exists.
   * It will check if the class has a constructor and if so, it will resolve its parameters
   * and pass them to the constructor.
   *
   * @param string $key
   * @return object
   */
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
