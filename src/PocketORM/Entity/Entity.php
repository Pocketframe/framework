<?php

namespace Pocketframe\PocketORM\Entity;

use Carbon\Carbon;
use Pocketframe\Essentials\Utilities\StringUtils;
use Pocketframe\PocketORM\Concerns\HasTimeStamps;
use Pocketframe\PocketORM\Database\EntityMapper;
use Pocketframe\PocketORM\Database\QueryEngine;
use Pocketframe\PocketORM\Essentials\DataSet;
use Pocketframe\PocketORM\Exceptions\MassAssignmentError;
use Pocketframe\PocketORM\Exceptions\ModelException;
use Pocketframe\PocketORM\Relationships\HasOne;
use Pocketframe\PocketORM\Relationships\HasMultiple;
use Pocketframe\PocketORM\Relationships\OwnedBy;
use Pocketframe\PocketORM\Relationships\Bridge;
use Pocketframe\PocketORM\Schema\Schema;

/**
 * Base Active Record–style Entity class.
 *
 * Features:
 * - 'relationship' array for defining relationships (HasOne, HasMultiple, OwnedBy, Bridge)
 * - fillable/guarded attributes
 * - timestamp handling via HasTimeStamps
 * - eager loading cache
 * - uses EntityMapper for persistence (create/update/delete)
 */
abstract class Entity
{
  use HasTimeStamps;

  /**
   * If not set, defaults to snake-cased class name + 's'
   * (e.g., Category -> categories)
   */
  protected static string $table;

  /**
   * Attributes for this entity (database columns).
   *
   * @var array
   */
  public array $attributes = [];

  /**
   * Relationship definitions.
   *
   * @var array
   *
   * Example:
   * protected array $relationship = [
   *   'profile' => [Entity::HAS_ONE, Profile::class, 'user_id'],
   *   'posts'   => [Entity::HAS_MULTIPLE, Post::class, 'author_id'],
   *   'role'    => [Entity::OWNED_BY, Role::class, 'role_id'],
   *   'groups'  => [Entity::BRIDGE, Group::class, 'user_groups', 'user_id', 'group_id']
   * ];
   */
  protected array $relationship = [];

  /**
   * Mass assignable attributes.
   *
   * @var array
   */
  protected array $fillable = [];

  /**
   * Attributes that cannot be mass assigned.
   *
   * @var array
   */
  protected array $guarded = ['id'];

  /**
   * Cache of loaded relationships.
   *
   * @var array
   */
  protected array $deepFetch = [];

  /**
   * Trash column name
   *
   * @var string
   */
  protected static string $trashColumn = 'trashed_at';

  // Relationship type constants for convenience
  const HAS_ONE      = HasOne::class;
  const HAS_MULTIPLE = HasMultiple::class;
  const OWNED_BY     = OwnedBy::class;
  const BRIDGE       = Bridge::class;

  /**
   * Initialize the entity and mass assign attributes.
   *
   * @param array $attributes
   *
   * @return void
   */
  public function __construct(array $attributes = [])
  {
    $this->fill($attributes);
  }

  /**
   * Magic getter to retrieve attributes or relationships.
   *
   * @param string $name
   *
   * @return mixed
   */
  public function __get(string $name)
  {
    // 1. Return attribute if it exists
    if (array_key_exists($name, $this->attributes)) {
      return $this->attributes[$name];
    }

    // 2. Check if it's a defined relationship
    if (isset($this->relationship[$name])) {
      return $this->loadRelationship($name);
    }

    // 3. If it's a date attribute, return as Carbon
    if (in_array($name, $this->dates)) {
      return $this->getDateValue($name);
    }

    throw new ModelException("Undefined property: {$name}");
  }

  /**
   * Magic setter, respecting guarded attributes.
   *
   * @param string $name
   *
   * @param mixed $value
   *
   * @return void
   */
  public function __set(string $name, $value)
  {
    if (in_array($name, $this->guarded)) {
      throw new MassAssignmentError(static::class, $name);
    }
    $this->attributes[$name] = $value;
  }

  public function __isset(string $name): bool
  {
    return array_key_exists($name, $this->attributes);
  }

  /**
   * Returns debug information about the entity.
   *
   * @return array
   */
  public function __debugInfo(): array
  {
    return [
      'attributes' => $this->attributes,
      'deepFetch' => $this->deepFetch,
      'relationships' => array_keys($this->relationship)
    ];
  }

  /**
   * Returns the eager loaded data for a specific relationship.
   *
   * @param string $relation The name of the relationship.
   *
   * @return mixed The eager loaded data for the relationship.
   */
  public function getDeepFetch(): array
  {
    return $this->deepFetch;
  }


  public function getIntegerColumns(): array
  {
    $integerColumns = ['id']; // Always cast 'id' as integer

    foreach ($this->relationship as $relation => $config) {
      $relationshipType = $config[0];

      switch ($relationshipType) {
        case self::BRIDGE:
          // Bridge relationships define parentKey and relatedKey
          $integerColumns[] = $config[3]; // parentKey (e.g., category_id)
          $integerColumns[] = $config[4]; // relatedKey (e.g., tag_id)
          break;
        case self::HAS_ONE:
        case self::HAS_MULTIPLE:
        case self::OWNED_BY:
          // Third element is the foreign key
          $integerColumns[] = $config[2];
          break;
      }
    }

    return array_unique($integerColumns);
  }

  /**
   * Initialize relationships.
   *
   * This method is called when the entity is first loaded from the database.
   *
   * @return void
   */
  public function initializeRelationships(): void
  {
    foreach (array_keys($this->relationship) as $relation) {
      $this->$relation;
    }
  }

  /**
   * Get the relationship configuration for a given relationship.
   *
   * @param string $relation The name of the relationship.
   * @return array|null The relationship configuration, or null if the relationship is not defined.
   */
  public function getRelationshipConfig(string $relation): ?array
  {
    $config = $this->relationship[$relation] ?? null;

    if ($config && $config[0] === Bridge::class && count($config) < 5) {
      throw new \InvalidArgumentException(
        "Invalid Bridge configuration for {$relation}. " .
          "Expected format: [Bridge::class, RelatedClass, PivotTable, ParentKey, RelatedKey]"
      );
    }

    return $config;
  }

  /**
   * Set eager loaded data for a specific relationship.
   *
   * This method is used to set the eager loaded data for a relationship.
   *
   * @param string $relation The name of the relationship.
   * @param mixed $data The data to set.
   * @return void
   */
  public function setDeepFetch(string $relation, $data): void
  {
    $this->deepFetch[$relation] = $data;
  }

  /**
   * Mass assign attributes, throwing error for unfillable keys.
   *
   * @param array $attributes
   *
   * @return self
   */
  public function fill(array $attributes): self
  {
    $table = static::getTable();
    $trashColumn = static::$trashColumn;
    foreach ($attributes as $key => $value) {
      if ($key === $trashColumn && Schema::tableHasColumn($table, $trashColumn)) {
        $this->attributes[$key] = $value;
        continue;
      }

      if (!in_array($key, $this->fillable) && !empty($this->fillable)) {
        throw new MassAssignmentError(static::class, $key);
      }

      $this->attributes[$key] = $value;
    }
    return $this;
  }

  /**
   * Return only fillable attributes (for inserts/updates).
   *
   * Returns only fillable attributes, which are the attributes that can be mass
   * assigned to the entity. If the fillable attribute is empty, all attributes
   * are returned.
   *
   * @return array
   */
  public function getFillableAttributes(): array
  {
    if (empty($this->fillable)) {
      // If fillable is empty, allow all
      return $this->attributes;
    }
    return array_intersect_key($this->attributes, array_flip($this->fillable));
  }

  /**
   * Get the table name for the entity.
   *
   * Returns the table name for the entity. If the table name is not set, it is
   * generated from the class name.
   *
   * @return string
   */
  public static function getTable(): string
  {
    if (!isset(static::$table)) {
      static::$table = self::generateTableName();
    }
    return static::$table;
  }

  /**
   * Generate the table name from the class name.
   *
   * Returns the table name by pluralizing the class name. This is used when the
   * table name is not explicitly set.
   *
   * @return string
   */
  private static function generateTableName(): string
  {
    $className = (new \ReflectionClass(static::class))->getShortName();
    return strtolower(StringUtils::plural($className));
  }


  /**
   * For date attributes, convert to Carbon instance.
   *
   * @param string $key
   *
   * @return ?Carbon
   */
  protected function getDateValue(string $key): ?Carbon
  {
    if (isset($this->attributes[$key])) {
      return Carbon::parse($this->attributes[$key]);
    }
    return null;
  }

  /**
   * Load a relationship if not already cached in deepFetch.
   *
   * @param string $relation
   *
   * @return mixed
   */
  protected function loadRelationship(string $relation)
  {
    if (array_key_exists($relation, $this->deepFetch)) {
      return $this->deepFetch[$relation];
    }

    if (!isset($this->relationship[$relation])) {
      throw new \Exception("Undefined relationship: {$relation}");
    }

    $config = $this->relationship[$relation];
    $relClass = $config[0];
    $relatedEntity = $config[1];

    // Check if the relationship is a Bridge
    if ($relClass === Bridge::class) {
      // Extract parameters specific to Bridge
      $pivotTable = $config[2];
      $parentKey = $config[3];
      $relatedKey = $config[4];
      $instance = new Bridge($this, $relatedEntity, $pivotTable, $parentKey, $relatedKey);
    } else {
      // Handle other relationships (HasOne, HasMultiple, OwnedBy)
      $foreignKey = $config[2] ?? $this->guessForeignKey();
      $instance = new $relClass($this, $relatedEntity, $foreignKey);
    }

    $this->deepFetch[$relation] = $instance;
    return $instance;
  }

  /**
   * Get the relationship handler for a given relationship.
   *
   * This method is used to retrieve the relationship handler for a specific
   * relationship. If the relationship is not defined, an exception is thrown.
   * If the relationship is not already loaded, it is loaded.
   *
   * @param string $relation The name of the relationship.
   *
   * @return mixed The relationship handler.
   */
  public function getRelationshipHandler(string $relation)
  {
    if (!isset($this->relationship[$relation])) {
      throw new \InvalidArgumentException("Undefined relationship: {$relation}");
    }

    if (!isset($this->deepFetch[$relation])) {
      $this->loadRelationship($relation);
    }

    return $this->deepFetch[$relation];
  }

  /**
   * Handle dynamic method calls.
   *
   * This method is used to handle dynamic method calls to the entity. If the
   * method is a relationship, it is retrieved using the getRelationshipHandler
   * method. If the method is a regular method, it is called with the provided
   * arguments. If the method is undefined, a BadMethodCallException is thrown.
   *
   * @param string $name The name of the method to call.
   * @param array $arguments The arguments to pass to the method.
   *
   * @return mixed The result of the method call.
   */
  public function __call(string $name, array $arguments)
  {
    // Check if this is a relationship method call
    if (isset($this->relationship[$name])) {
      return $this->getRelationshipHandler($name);
    }

    // Handle other magic methods or throw error
    if (method_exists($this, $name)) {
      return $this->$name(...$arguments);
    }

    throw new \BadMethodCallException(
      "Undefined method: " . static::class . "::$name"
    );
  }

  /**
   * Default guess for foreign key: "classname_id".
   *
   * @return string
   */
  protected function guessForeignKey(): string
  {
    return strtolower(StringUtils::classBasename(static::class)) . '_id';
  }

  /**
   * Save the entity (insert or update) via EntityMapper.
   *
   * @return self
   */
  public function save(): self
  {
    // Update timestamps if needed (HasTimeStamps trait)
    $this->updateTimeStamps();
    return EntityMapper::persist($this);
  }

  /**
   * Delete the entity from the DB via EntityMapper.
   *
   * @return self
   */
  public function delete(): self
  {
    return EntityMapper::erase($this);
  }

  /**
   * Check if the entity has a primary key set (exists in DB).
   *
   * @return bool
   */
  public function exists(): bool
  {
    return isset($this->attributes['id']) && $this->attributes['id'] !== null;
  }

  public static function all(): array
  {
    $query = new QueryEngine(static::class);
    $records = $query->get();
    return $records->toArray();
  }

  public function isEmpty(): bool
  {
    return empty($this->records);
  }

  public function last(): ?object
  {
    $record = end($this->records);
    return $record ? (object)$record : null;
  }

  public function each(callable $callback): void
  {
    foreach ($this->records as $key => $record) {
      $callback($record, $key);
    }
  }

  public function sortBy(string $field, bool $descending = false): self
  {
    $records = $this->records;
    usort($records, function ($a, $b) use ($field, $descending) {
      $valueA = is_object($a) ? $a->$field : $a[$field];
      $valueB = is_object($b) ? $b->$field : $b[$field];
      return $descending ? $valueB <=> $valueA : $valueA <=> $valueB;
    });

    return new self($records);
  }
  /**
   * Get the values of a specific column from the records.
   *
   * @param string $column The name of the column to retrieve.
   *
   * @return array An array of column values.
   */
  // public function getColumn(string $column): array
  // {
  //   return array_map(function ($item) use ($column) {
  //     return is_object($item) ? $item->{$column} : $item[$column];
  //   }, $this->attributes);
  // }

  /**
   * Convert entity and any loaded relationships to array form.
   *
   * @return array
   */
  public function toArray(): array
  {
    $array = $this->attributes;

    // Convert each loaded relationship to array
    foreach ($this->deepFetch as $relation => $value) {
      if ($value instanceof self) {
        $array[$relation] = $value->toArray();
      } elseif ($value instanceof DataSet) {
        $array[$relation] = $value->toArray();
      } else {
        // Possibly a relationship object that hasn't fetched data yet
        $array[$relation] = (string)$value;
      }
    }
    return $array;
  }
}
