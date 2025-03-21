<?php

namespace Pocketframe\PocketORM\Entity;

use Carbon\Carbon;
use Pocketframe\Essentials\Utilities\Utilities;
use Pocketframe\PocketORM\Concerns\HasTimestamps;
use Pocketframe\PocketORM\Database\DataMapping;
use Pocketframe\PocketORM\Exceptions\MassAssignmentError;
use Pocketframe\PocketORM\Exceptions\ModelException;
use Pocketframe\PocketORM\Relationships\Bridge;
use Pocketframe\PocketORM\Relationships\HasMultiple;
use Pocketframe\PocketORM\Relationships\HasSingle;
use Pocketframe\PocketORM\Relationships\LinkedTo;

abstract class Entity
{
  use HasTimestamps;

  protected static string $table;
  protected array $attributes = [];
  protected array $links = [];
  protected array $fillable = [];
  protected array $guarded = ['id'];

  protected array $dates = ['created_at', 'updated_at'];
  protected bool $timestamps = true;

  // Relationship type constants
  const HAS_SINGLE = HasSingle::class;
  const HAS_MULTIPLE = HasMultiple::class;
  const LINKED_TO = LinkedTo::class;
  const BRIDGE = Bridge::class;


  public function __construct(array $attributes = [])
  {
    $this->fill($attributes);
  }

  public function __get(string $name)
  {
    if (array_key_exists($name, $this->attributes)) {
      return $this->attributes[$name];
    }

    if (isset($this->links[$name])) {
      return $this->link($name);
    }

    if (in_array($name, $this->dates)) {
      return $this->getDateValue($name);
    }

    throw new ModelException("Undefined property: {$name}");
  }

  protected function getDateValue(string $key): ?Carbon
  {
    if (isset($this->attributes[$key])) {
      return Carbon::parse($this->attributes[$key]);
    }
    return null;
  }

  public function __set(string $name, $value)
  {
    if (in_array($name, $this->guarded)) {
      throw new MassAssignmentError(static::class, $name);
    }

    $this->attributes[$name] = $value;
  }

  public function fill(array $attributes): self
  {
    foreach ($attributes as $key => $value) {
      if (!in_array($key, $this->fillable)) {
        throw new MassAssignmentError(static::class, $key);
      }
      $this->attributes[$key] = $value;
    }
    return $this;
  }

  public function getFillableAttributes(): array
  {
    return array_intersect_key(
      $this->attributes,
      array_flip($this->fillable)
    );
  }

  public static function getTable(): string
  {
    return static::$table ?? strtolower(Utilities::classBasename(static::class)) . 's';
  }


  public function link(string $relation)
  {
    if (!isset($this->links[$relation])) {
      throw new \Exception("Undefined relationship: {$relation}");
    }

    if (array_key_exists($relation, $this->eagerLoaded)) {
      return $this->eagerLoaded[$relation];
    }

    $config = $this->links[$relation];
    $relationshipClass = $config[0];
    $relatedClass = $config[1];
    $foreignKey = $config[2] ?? null;

    return new $relationshipClass(
      $this,
      $relatedClass,
      $foreignKey ?? $this->guessForeignKey()
    );
  }

  protected function guessForeignKey(): string
  {
    return strtolower(Utilities::classBasename(static::class)) . '_id';
  }


  public function save(): void
  {
    HookDispatcher::trigger('saving', $this);

    if ($this->exists()) {
      HookDispatcher::trigger('updating', $this);
      $this->updateTimestamps();
      DataMapping::persist($this);
      $this->convertDates();
      HookDispatcher::trigger('updated', $this);
    } else {
      HookDispatcher::trigger('creating', $this);
      $this->updateTimestamps();
      DataMapping::persist($this);
      $this->convertDates();
      HookDispatcher::trigger('created', $this);
    }

    HookDispatcher::trigger('saved', $this);
  }

  public function delete(): void
  {
    HookDispatcher::trigger('deleting', $this);
    DataMapping::erase($this);
    HookDispatcher::trigger('deleted', $this);
  }

  public function exists(): bool
  {
    return isset($this->attributes['id']) && !is_null($this->attributes['id']);
  }
}
