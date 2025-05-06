<?php

namespace Pocketframe\PocketORM\Database;

use Pocketframe\PocketORM\Entity\Entity;
use Pocketframe\PocketORM\Entity\HookDispatcher;
use Pocketframe\PocketORM\Exceptions\PersistenceFailureError;
use Pocketframe\PocketORM\Schema\Schema;

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
    return (new QueryEngine($entityClass))
      ->where('id', '=', $id)
      ->first();
  }


  /**
   * Persist an entity (insert or update).
   */
  public static function persist(Entity $entity): Entity
  {
    try {
      $entityClass = get_class($entity);
      $data = self::prepareData($entity);

      if ($entity->exists()) {
        HookDispatcher::trigger('updating', $entity);
        self::performUpdate($entityClass, $entity->id, $data);
        HookDispatcher::trigger('updated', $entity);
      } else {
        HookDispatcher::trigger('creating', $entity);
        $id = self::performInsert($entityClass, $data);
        $entity->attributes['id'] = $id;
        HookDispatcher::trigger('created', $entity);
      }

      HookDispatcher::trigger('saved', $entity);
      return $entity;
    } catch (PersistenceFailureError $e) {
      throw new PersistenceFailureError(
        "Persist failed: " . $e->getMessage(),
        $e->getCode(),
        $e->getEntityClass(),
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
  public static function insertBatch(string $entityClass, array $entities): void
  {
    $table = $entityClass::getTable();
    $data = array_map(fn($e) => self::prepareData($e), $entities);

    (new QueryEngine($table))->insertBatch($data);
  }

  /**
   * Remove an entity from the database.
   */
  public static function erase(Entity $entity): Entity
  {
    (new QueryEngine($entity::getTable()))
      ->where('id', '=', $entity->id)
      ->delete();
    return $entity;
  }

  /**
   * Prepare data for insertion or update (format dates, etc.).
   */
  private static function prepareData(Entity $entity): array
  {
    $table = $entity::getTable();
    $data = [];

    foreach ($entity->getFillableAttributes() as $key => $value) {
      $data[$key] = self::formatValue($value);
    }

    // Add system-managed timestamps only if column exists
    $systemFields = ['created_at', 'updated_at', 'trashed_at'];
    foreach ($systemFields as $field) {
      if (Schema::tableHasColumn($table, $field) && array_key_exists($field, $entity->attributes) && !isset($data[$field])) {
        $data[$field] = self::formatValue($entity->attributes[$field]);
      }
    }

    return $data;
  }

  private static function formatValue($value)
  {
    return $value instanceof \DateTimeInterface
      ? $value->format('Y-m-d H:i:s')
      : $value;
  }

  private static function performUpdate(string $entityClass, $id, array $data): void
  {
    (new QueryEngine($entityClass))
      ->where('id', '=', $id)
      ->update($data);
  }

  private static function performInsert(string $entityClass, array $data): int
  {
    $query = new QueryEngine($entityClass::getTable());
    $id = $query->insert($data);

    // Verify ID is numeric
    if (!is_numeric($id)) {
      throw new PersistenceFailureError(
        "Insert failed to return valid ID",
        0,
        $entityClass,
        ['data' => $data]
      );
    }

    return (int) $id;
  }
}
