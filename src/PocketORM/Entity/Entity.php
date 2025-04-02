<?php

namespace Pocketframe\PocketORM\Entity;

use Carbon\Carbon;
use Pocketframe\Essentials\Utilities\StringUtils;
use Pocketframe\PocketORM\Concerns\HasTimeStamps;
use Pocketframe\PocketORM\Database\EntityMapper;
use Pocketframe\PocketORM\Essentials\DataSet;
use Pocketframe\PocketORM\Exceptions\MassAssignmentError;
use Pocketframe\PocketORM\Exceptions\ModelException;
use Pocketframe\PocketORM\Relationships\HasOne;
use Pocketframe\PocketORM\Relationships\HasMultiple;
use Pocketframe\PocketORM\Relationships\OwnedBy;
use Pocketframe\PocketORM\Relationships\Bridge;

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
  protected array $eagerLoaded = [];

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

  /**
   * Returns debug information about the entity.
   *
   * @return array
   */
  public function __debugInfo(): array
  {
    return [
      'attributes' => $this->attributes,
      'eagerLoaded' => $this->eagerLoaded,
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
  public function getEagerLoaded(): array
  {
    return $this->eagerLoaded;
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
    return $this->relationship[$relation] ?? null;
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
  public function setEagerLoaded(string $relation, $data): void
  {
    $this->eagerLoaded[$relation] = $data;
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
    foreach ($attributes as $key => $value) {
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
   * Return the associated table name, defaulting to "classname + s" if not set.
   *
   * @return string
   */
  public static function getTable(): string
  {
    return static::$table ?? strtolower(StringUtils::classBasename(static::class)) . 's';
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
   * Load a relationship if not already cached in eagerLoaded.
   *
   * @param string $relation
   *
   * @return mixed
   */
  protected function loadRelationship(string $relation)
  {
    if (array_key_exists($relation, $this->eagerLoaded)) {
      return $this->eagerLoaded[$relation];
    }

    if (!isset($this->relationship[$relation])) {
      throw new \Exception("Undefined relationship: {$relation}");
    }

    // Relationship config: [RelationshipClass, RelatedEntity, foreignKey?]
    $config        = $this->relationship[$relation];
    $relClass      = $config[0];
    $relatedEntity = $config[1];
    $foreignKey    = $config[2] ?? $this->guessForeignKey();

    $instance = new $relClass($this, $relatedEntity, $foreignKey);
    $this->eagerLoaded[$relation] = $instance;

    return $instance;
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
    return isset($this->attributes['id']) && !is_null($this->attributes['id']);
  }

  /**
   * Convert entity and any loaded relationships to array form.
   *
   * @return array
   */
  public function toArray(): array
  {
    $array = $this->attributes;

    // Convert each loaded relationship to array
    foreach ($this->eagerLoaded as $relation => $value) {
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
