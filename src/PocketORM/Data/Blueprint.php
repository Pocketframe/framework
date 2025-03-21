<?php

namespace Pocketframe\PocketORM\Data;

// same as factory
class Blueprint
{
  private \Faker\Generator $faker;
  private array $definitions = [];
  private array $states = [];


  public function __construct()
  {
    $this->faker = Factory::create();
  }

  public function define(string $modelClass, callable $attributes): self
  {
    $this->definitions[$modelClass] = $attributes;
    return $this;
  }

  public function state(string $modelClass, callable $state): self
  {
    $this->states[$modelClass][] = $state;
    return $this;
  }

  public function make(string $modelClass, array $overrides = []): object
  {
    $attributes = array_merge(
      $this->applyDefinitions($modelClass),
      $overrides
    );

    return new $modelClass($attributes);
  }

  private function applyDefinitions(string $modelClass): array
  {
    $attributes = call_user_func($this->definitions[$modelClass], $this->faker);

    foreach ($this->states[$modelClass] ?? [] as $state) {
      $attributes = array_merge($attributes, $state($this->faker));
    }

    return $attributes;
  }
}
