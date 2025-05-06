<?php

namespace Pocketframe\PocketORM\Entity;

use Carbon\Carbon;
use Pocketframe\Essentials\Utilities\StringUtils;
use Pocketframe\PocketORM\Concerns\HasTimeStamps;
use Pocketframe\PocketORM\Entity\EntityMapper;
use Pocketframe\PocketORM\Essentials\DataSet;
use Pocketframe\PocketORM\Exceptions\EntityException;
use Pocketframe\PocketORM\Exceptions\MassAssignmentError;
use Pocketframe\PocketORM\QueryEngine\QueryEngine;
use Pocketframe\PocketORM\Relationships\HasOne;
use Pocketframe\PocketORM\Relationships\HasMultiple;
use Pocketframe\PocketORM\Relationships\OwnedBy;
use Pocketframe\PocketORM\Relationships\Bridge;
use Pocketframe\PocketORM\Relationships\RelationshipNotDefinedException;
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
   * Global scopes
   *
   * @var array
   */
  protected static array $globalScopes = [];

  /**
   * Whether the entity has been booted
   *
   * @var array
   */
  protected static array $booted = [];

  /**
   * Relationship definitions.
   *
   * @var array
   *
   * Example:
   * protected array $relationship = [
   *   'profile' => [Entity::HAS_ONE, Profile::class, 'user_id'],
   *   'posts'   => [Entity::HAS_MULTIPLE, Post::class, 'post_id'],
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

  /**
   * Trash value
   * Default will be timestamp
   *
   * @var mixed
   */
  protected static $trashValue = null;

  /**
   * Restore value
   * Default will be null
   *
   * @var mixed
   */
  protected static $restoreValue = null;

  /**
   * Whether the entity is immutable
   *
   * @var bool
   */
  protected bool $immutable = false;

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
   * @example: $entity = new Entity(['id' => 1, 'name' => 'John']);
   */
  public function __construct(array $attributes = [])
  {
    $this->fill($attributes);
  }

  /**
   * Magic getter to retrieve attributes or relationships.
   *
   * This method is used to retrieve attributes or relationships.
   * It throws an error if the property is undefined.
   *
   * @param string $name
   * @return mixed
   */
  public function __get(string $name)
  {
    // 1) Already loaded?
    if (array_key_exists($name, $this->deepFetch)) {
      return $this->deepFetch[$name];
    }

    // 2) Actual attribute?
    if (array_key_exists($name, $this->attributes)) {
      return $this->attributes[$name];
    }

    // 3) Relationship?
    if (isset($this->relationship[$name])) {
      $handler = $this->loadRelationship($name);

      // If it’s a BELONGS-TO or HAS-ONE, immediately resolve to a single model:
      if (
        $handler instanceof \Pocketframe\PocketORM\Relationships\OwnedBy
        || $handler instanceof \Pocketframe\PocketORM\Relationships\HasOne
      ) {
        $resolved = $handler->resolve();
      } else {
        // For HasMultiple or Bridge, get back a DataSet
        $resolved = $handler->get();
      }

      // Cache it & return it
      return $this->deepFetch[$name] = $resolved;
    }

    // 4) Date or error
    if (in_array($name, $this->dates, true)) {
      return $this->getDateValue($name);
    }

    throw new EntityException("Undefined property: {$name}");
  }


  /**
   * Magic setter, respecting guarded attributes.
   *
   * This method is used to set the value of an attribute.
   * It throws an error if the attribute is guarded.
   *
   * @param string $name
   * @param mixed $value
   *
   * @return void
   */
  public function __set(string $name, $value)
  {
    if (in_array($name, $this->guarded)) {
      throw new MassAssignmentError(static::class, $name);
    }

    if ($this->immutable) {
      throw new \RuntimeException("Cannot modify immutable entity property '$name'");
    }

    $this->attributes[$name] = $value;
  }

  /**
   * Check if an attribute is set.
   *
   * This method is used to check if an attribute is set.
   *
   * @param string $name
   * @return bool
   */
  public function __isset(string $name): bool
  {
    return array_key_exists($name, $this->attributes);
  }

  /**
   * Returns debug information about the entity.
   *
   * This method returns an array of debug information about the entity.
   *
   * @return array
   */
  public function __debugInfo(): array
  {
    return [
      'attributes'    => $this->attributes,
      'deepFetch'     => $this->deepFetch,
      'globalScopes'  => $this->globalScopes,
      'booted'        => $this->booted,
      'fillable'      => $this->fillable,
      'guarded'       => $this->guarded,
      'immutable'     => $this->immutable,
      'relationships' => array_keys($this->relationship)
    ];
  }

  /**
   * Make the entity immutable.
   *
   * This method is used to make the entity immutable.
   * Immutable means that once the entity is created,
   * its attributes cannot be changed.
   *
   * @return void
   */
  public function makeImmutable(): void
  {
    $this->immutable = true;
  }

  /**
   * Returns a new entity with updated attributes.
   *
   * This method returns a new entity with the specified attributes updated.
   *
   * @param array $changes
   * @return static
   */
  public function withUpdated(array $changes): static
  {
    $clone = clone $this;
    foreach ($changes as $k => $v) {
      $clone->attributes[$k] = $v;
    }
    return $clone;
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

  /**
   * Get the integer columns.
   *
   * This method returns the integer columns for the entity.
   *
   * @return array
   */
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
   *
   * @return array|null The relationship configuration, or null if the relationship is not defined.
   */
  public function getRelationshipConfig(string $relation): ?array
  {
    $config = $this->relationship[$relation] ?? null;

    if (!isset($config)) {
      throw new RelationshipNotDefinedException($relation, static::class);
    }

    if ($config && $config[0] === Entity::BRIDGE && count($config) < 5) {
      throw new \InvalidArgumentException(
        "Invalid Bridge configuration for {$relation}. " .
          "Expected format: [Entity::BRIDGE, RelatedClass, PivotTable, ParentKey, RelatedKey]"
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
   * This method is used to mass assign attributes to the entity.
   * It throws an error for unfillable keys.
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
   * @return array<string, mixed>
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
   * Add a global scope.
   *
   * This method adds a global scope to the entity. Global scopes are callbacks
   * that are run on the query builder before it is executed. They are great for
   * specifying a common set of constraints that an entity should always have.
   *
   * @param string $name
   * @param callable $callable
   * @return void
   */
  public static function addGlobalScope(string $name, callable $callable): void
  {
    // dd("bootTrashable called for " . static::class);
    static::$globalScopes[$name] = $callable;
  }

  /**
   * Boot the entity.
   *
   * This method is used to boot the entity. It is called when the entity is first
   * loaded from the database. It is great for setting up default values,
   * initializing relationships, and other tasks that need to be done when the
   * entity is first loaded.
   *
   * @return void
   */
  public static function bootIfNotBooted(): void
  {
    if (!isset(static::$booted[static::class])) {
      static::$booted[static::class] = true;
      if (method_exists(static::class, 'bootTrashable')) {
        static::bootTrashable();
      }
    }
  }

  /**
   * Remove a global scope.
   *
   * This method removes a global scope from the entity. Global scopes are callbacks
   * that are run on the query builder before it is executed. They are great for
   * specifying a common set of constraints that an entity should always have.
   *
   * @param string $name
   * @return void
   */
  public static function removeGlobalScope(string $name): void
  {
    unset(static::$globalScopes[$name]);
  }

  /**
   * Get all global scopes.
   *
   * This method returns all global scopes that have been added to the entity.
   *
   * @return array<callable, string>
   */
  public static function getGlobalScopes(): array
  {
    return static::$globalScopes;
  }

  /**
   * Run boot methods.
   *
   * This method runs all boot methods for the entity. Boot methods are
   * methods that are called when the entity is first loaded from the database.
   * They are great for setting up default values, initializing relationships,
   * and other tasks that need to be done when the entity is first loaded.
   *
   * @return void
   */
  public static function runBootMethods(): void
  {
    $class = static::class;
    if (!isset(self::$booted[$class])) {
      self::$booted[$class] = true;
      foreach (get_class_methods($class) as $method) {
        if (str_starts_with($method, 'boot') && is_callable([$class, $method])) {
          forward_static_call([$class, $method]);
        }
      }
    }
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
   * This method is used to convert a date attribute to a Carbon instance.
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
   * This method is used to load a relationship for a given relationship name.
   * If the relationship is not defined, an exception is thrown.
   * If the relationship is not already loaded, it is loaded.
   *
   * @param string $relation
   *
   * @return mixed
   */
  protected function loadRelationship(string $relation)
  {
    if (!isset($this->relationship[$relation])) {
      throw new \Exception("Undefined relationship: {$relation}");
    }

    [$relClass, $relatedEntity, $key1, $key2] = $this->relationship[$relation] + [null, null];
    $foreignKey = $key2 ?? $this->guessForeignKey();

    // Instantiate handler (no caching)
    if ($relClass === Bridge::class) {
      [$pivotTable, $parentKey, $relatedKey] = [$key1, $key2, null];
      $handler = new Bridge($this, $relatedEntity, $pivotTable, $parentKey, $relatedKey);
    } else {
      $handler = new $relClass($this, $relatedEntity, $foreignKey);
    }

    return $handler;
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
   * This method is used to guess the foreign key for a relationship.
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
   * Save batch of entities via EntityMapper.
   *
   * @param array $entities
   *
   * @return void
   * @example: Entity::saveBatch(Profile::class, [
   *   new Profile(['name' => 'John']),
   *   new Profile(['name' => 'Jane'])
   * ]);
   */
  // public static function saveBatch(string $entityClass, array $entities): void
  // {
  //   EntityMapper::insertBatch($entityClass, $entities);
  // }

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
   * Delete one or many records by their primary key.
   *
   * @param int|int[] $ids
   * @return int  Number of rows deleted
   */
  public static function destroy(int|array $ids): int
  {
    $ids = is_array($ids) ? $ids : [$ids];

    // build a QueryEngine for this entity’s table
    $deleted = QueryEngine::for(static::class)
      ->whereIn('id', $ids)
      ->delete();

    return $deleted;
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

  /**
   * Get all records.
   *
   * This method returns all records from the database.
   *
   * Example:
   * $posts = Post::all();
   *
   * @return array
   */
  public static function all(): array
  {
    $query = new QueryEngine(static::class);
    $records = $query->get();
    return $records->toArray();
  }

  /**
   * Check if the dataset is empty.
   *
   * This method returns true if the dataset is empty, false otherwise.
   *
   * Example:
   * $posts = Post::all();
   * if ($posts->isEmpty()) {
   *   echo 'No posts found';
   * }
   *
   * @return bool
   */
  public function isEmpty(): bool
  {
    return empty($this->records);
  }

  /**
   * Get the last record.
   *
   * This method returns the last record in the dataset.
   *
   * Example:
   * $posts = Post::all();
   * $lastPost = $posts->last();
   *
   * @return ?object
   */
  public function last(): ?object
  {
    $record = end($this->records);
    return $record ? (object)$record : null;
  }

  /**
   * Iterate over the dataset.
   *
   * This method allows you to iterate over the dataset using a foreach loop.
   *
   * Example:
   * $posts = Post::all();
   * foreach ($posts as $post) {
   *   echo $post->title;
   * }
   *
   * @param callable $callback
   * @return void
   */
  public function each(callable $callback): void
  {
    foreach ($this->records as $key => $record) {
      $callback($record, $key);
    }
  }

  /**
   * Sort the dataset by a specific field.
   *
   * This method allows you to sort the dataset in ascending or descending order.
   *
   * Example:
   * $posts = Post::all();
   * $posts = $posts->sortBy('created_at');
   *
   * The above example would sort the posts in ascending order by the created_at field.
   * If you wanted to sort in descending order, you would pass the second argument as true.
   *
   * $posts = Post::all();
   * $posts = $posts->sortBy('created_at', true);
   *
   * @param string $field
   * @param bool $descending
   * @return self
   */
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
   * Convert entity and any loaded relationships to array form.
   *
   * This method is useful for converting an entity and any
   * loaded relationships to a form that can be easily
   * serialized or stored in a cache.
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
