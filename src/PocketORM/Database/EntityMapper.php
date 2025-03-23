<?php

namespace Pocketframe\PocketORM\Database;

use Pocketframe\PocketORM\Entity\Entity;
use Pocketframe\PocketORM\Entity\HookDispatcher;
use Pocketframe\PocketORM\Exceptions\PersistenceFailureError;

/**
 * Maps entities to the database (Active Record style).
 */
class EntityMapper
{
  /**
   * Find an entity by ID.
   */
  public static function find(string $entityClass, $id): ?Entity
  {
    return (new QueryEngine($entityClass::getTable()))
      ->where('id', '=', $id)
      ->first();
  }

  /**
   * Persist an entity (insert or update).
   */
  public static function persist(Entity $entity): void
  {
    try {
      $table = $entity::getTable();
      $data = self::prepareData($entity);

      if ($entity->exists()) {
        HookDispatcher::trigger('updating', $entity);
        self::performUpdate($entity, $table, $data);
        HookDispatcher::trigger('updated', $entity);
      } else {
        HookDispatcher::trigger('creating', $entity);
        $id = self::performInsert($table, $data);
        $entity->attributes['id'] = $id;
        HookDispatcher::trigger('created', $entity);
      }

      HookDispatcher::trigger('saved', $entity);
    } catch (PersistenceFailureError $e) {
      throw new PersistenceFailureError(
        "Persist failed: " . $e->getMessage(),
        $e->getCode(),
        $e->getModelClass(),
        $e->getContext()
      );
    }
  }

  public static function create(Entity $entity): int
  {
    $table = $entity::getTable();
    $data  = self::prepareData($entity);
    return (new QueryEngine($table))->insert($data);
  }

  public static function update(Entity $entity): int
  {
    if (!$entity->exists()) {
      throw new \Exception("Cannot update an entity without an ID.");
    }
    $table = $entity::getTable();
    $data  = self::prepareData($entity);
    return (new QueryEngine($table))
      ->where('id', '=', $entity->id)
      ->update($data);
  }


  /**
   * Insert multiple entities in a batch.
   */
  // public static function insertBatch(string $entityClass, array $entities): void
  // {
  //   $table = $entityClass::getTable();
  //   $data = array_map(fn($e) => $e->attributes, $entities);

  //   (new QueryEngine($table))->insertBatch($data);
  // }

  /**
   * Remove an entity from the database.
   */
  public static function erase(Entity $entity): void
  {
    (new QueryEngine($entity::getTable()))
      ->where('id', '=', $entity->id)
      ->delete();
  }

  /**
   * Prepare data for insertion or update (format dates, etc.).
   */
  private static function prepareData(Entity $entity): array
  {
    $data = [];
    foreach ($entity->getFillableAttributes() as $key => $value) {
      $data[$key] = $value instanceof \DateTimeInterface
        ? $value->format('Y-m-d H:i:s')
        : $value;
    }
    return $data;
  }

  private static function performUpdate(Entity $entity, string $table, array $data): void
  {
    (new QueryEngine($table))
      ->where('id', '=', $entity->id)
      ->update($data);
  }

  private static function performInsert(string $table, array $data): int
  {
    return (new QueryEngine($table))
      ->insert($data);
  }
}
