<?php

namespace Pocketframe\PocketORM\Data;

// same as factory
abstract class Blueprint
{
  private Generator $faker;
  protected string $entity;
  private array $states = [];


  public function __construct()
  {
    $this->faker = Factory::create();
  }

  abstract public function describe(): array;

  public function state(string $entityClass, callable $state): self
  {
    $this->states[$entityClass][] = $state;
    return $this;
  }

  public function make(array $overrides = []): array
  {
    return array_merge($this->describe($this->faker), $overrides);
  }


  public function create(array $overrides = [])
  {
    $attributes = $this->make($overrides);
    return (new $this->entity)->fill($attributes)->save();
  }
}
