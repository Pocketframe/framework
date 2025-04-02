<?php

namespace Pocketframe\PocketORM\Data;

use Faker\Factory;
use Faker\Generator;
use Pocketframe\PocketORM\Entity\Entity;

// same as factory
abstract class Blueprint
{
  /**
   * Faker generator
   */
  private Generator $faker;
  /**
   * Entity class
   */
  protected string $entity;
  /**
   * States
   */
  private array $states = [];


  public function __construct()
  {
    $this->faker = Factory::create();
  }

  /**
   * Describe the entity
   *
   * @param Generator $faker Faker generator
   * @return array Array of attributes
   */
  abstract public function describe(Generator $faker): array;

  /**
   * Add a state to the entity
   *
   * @param string $entityClass Entity class
   * @param callable $state State callback
   * @return self
   */
  public function state(string $entityClass, callable $state): self
  {
    $this->states[$entityClass][] = $state;
    return $this;
  }

  /**
   * Make an entity
   *
   * @param array $overrides Optional overrides for the entity
   * @return array Array of attributes
   */
  public function make(array $overrides = []): array
  {
    return array_merge($this->describe($this->faker), $overrides);
  }

  /**
   * Create an entity
   *
   * @param array $overrides Optional overrides for the entity
   * @return \Pocketframe\PocketORM\Entity\Entity Entity instance
   */
  public function create(array $overrides = []): Entity
  {
    $attributes = $this->make($overrides);
    $entity = new $this->entity;

    // Don't try to set ID manually unless you're forcing it
    if (array_key_exists('id', $attributes)) {
      unset($attributes['id']);
    }

    return $entity->fill($attributes)->save();
  }

  /**
   * Create multiple entities
   *
   * @param int $count Number of entities to create
   * @param array $overrides Optional overrides for the entities
   * @return array Array of created entities
   */
  public function createMany(int $count, array $overrides = []): array
  {
    $entities = [];
    for ($i = 0; $i < $count; $i++) {
      $entities[] = $this->create($overrides);
    }
    return $entities;
  }

  /**
   * Make multiple entities without saving
   *
   * @param int $count Number of entities to make
   * @param array $overrides Optional overrides for the entities
   * @return array Array of entities
   */
  public function makeMany(int $count, array $overrides = []): array
  {
    return array_map(function () use ($overrides) {
      return $this->make($overrides);
    }, range(1, $count));
  }
}
