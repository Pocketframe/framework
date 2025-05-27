<?php

namespace Pocketframe\PocketORM\Concerns;

use Closure;
use InvalidArgumentException;
use Pocketframe\PocketORM\Entity\Entity;
use Pocketframe\PocketORM\Essentials\DataSet;
use Pocketframe\PocketORM\Relationships\Bridge;
use Pocketframe\PocketORM\Relationships\HasMultiple;
use Pocketframe\PocketORM\Relationships\HasOne;
use Pocketframe\PocketORM\Relationships\BelongsTo;

/**
 * Trait DeepFetch
 *
 * Advanced eager-loading and relationship filtering for PocketORM.
 *
 * Usage examples:
 *   $query->include(['posts', 'tags:id,name']);
 *   $query->include(['posts' => function($q){ $q->where(...); }]);
 *   $query->include(['posts.comments' => function($q){ ... }]);
 *   $query->includeWhereHas('posts', fn($q) => $q->where('status', 'published'), 'id,title');
 */
trait DeepFetch
{
  /**
   * Stores includes with their optional column lists.
   * @var array<string, string[]>
   */
  private array $includes = [];

  /**
   * Stores user callbacks for specific relations.
   * @var array<string, Closure>
   */
  private array $includeCallbacks = [];

  /**
   * Parse include definition into name and columns.
   *
   * @param string $def
   * @return array{name: string, cols: string[]}
   */
  private function parseIncludeDef(string $def): array
  {
    if (str_contains($def, ':')) {
      [$name, $colsStr] = explode(':', $def, 2);
      $cols = array_map('trim', explode(',', $colsStr));
    } else {
      $name = $def;
      $cols = ['*'];
    }
    return [$name, $cols];
  }

  /**
   * Define relationships to include (with optional columns and callbacks).
   *
   * Usage:
   *   ->include(['posts', 'tags:id,name'])
   *   ->include(['posts' => function($q){ $q->where(...); }])
   *   ->include(['posts.comments' => function($q){ ... }])
   *
   * @param array<string, string|Closure> $relations
   * @return static
   * @throws InvalidArgumentException If a relation does not exist
   */
  public function include(array $relations): static
  {
    // Attempt to use a sample entity for relation existence checks
    $sampleEntity = method_exists($this, 'getModel') ? $this->getModel() : null;

    foreach ($relations as $key => $value) {
      [$name, $cols] = $this->parseIncludeDef(is_int($key) ? $value : $key);

      // Error handling: check if the relation exists on the model (if possible)
      if ($sampleEntity instanceof Entity) {
        if (!method_exists($sampleEntity, $name) && !$sampleEntity->getRelationshipConfig($name)) {
          throw new InvalidArgumentException("Relation '$name' does not exist on " . get_class($sampleEntity));
        }
      }

      if ($value instanceof Closure) {
        $this->includeCallbacks[$name] = $value;
      }
      $this->includes[$name] = $cols;
    }
    return $this;
  }

  /**
   * Filter the parent by a relation **and** eager-load that relation
   * with the same filters (plus optional column-selection).
   *
   * Usage:
   *   ->includeWhereHas('posts', fn($q) => $q->where('status', 'published'), 'id,title')
   *
   * @param string        $relationPath The relation path (supports dot notation)
   * @param Closure       $filter       A closure($query) that adds your where() calls
   * @param string[]|string $columns    Optional: columns to select on the relation
   * @return static
   */
  public function includeWhereHas(string $relationPath, Closure $filter, string|array $columns = ['*']): static
  {
    // Allow columns as a string, e.g. 'title,slug'
    if (is_string($columns)) {
      [, $columns] = $this->parseIncludeDef($relationPath . ':' . $columns);
    }

    // 1) Separate the first segment from the rest
    if (str_contains($relationPath, '.')) {
      [$root, $rest] = explode('.', $relationPath, 2);
    } else {
      $root = $relationPath;
      $rest = null;
    }

    // 2) Apply the filter only to the ROOT relation
    $this->whereHas($root, $filter);

    // 3) Eager‐load the full path:
    //    - For the root, we reapply the same filter (and select cols).
    //    - For the nested rest (if any), we eager-load it without reapplying root filters.
    $this->include([
      // root segment
      $root => function ($q) use ($filter, $columns) {
        $filter($q);
        if ($columns !== ['*']) {
          $q->select($columns);
        }
      },
      // nested (pass through the remainder of the path)
      $relationPath => null,
    ]);

    return $this;
  }

  /**
   * Fetch base records and eager-load each include with its columns.
   *
   * @return DataSet
   */
  public function get(): DataSet
  {
    $records = parent::get();
    foreach ($this->includes as $rel => $cols) {
      $this->loadRelation($records, $rel, $cols);
    }
    return $records;
  }

  /**
   * Load a single relationship (supports dot notation).
   *
   * @param DataSet $records
   * @param string $relationPath
   * @param string[] $columns
   * @return void
   */
  private function loadRelation(DataSet $records, string $relationPath, array $columns): void
  {
    $segments = explode('.', $relationPath);
    $base     = array_shift($segments);
    $nested   = $segments ? implode('.', $segments) : null;
    $items    = $records->all();

    if (empty($items)) {
      return;
    }

    // Instantiate the relationship handler
    $entity = reset($items);

    if (method_exists($entity, $base)) {
      // Use method-based relationship
      $relationship = $entity->$base();
    } else {
      // Fallback to array-based
      $config = $entity->getRelationshipConfig($base);
      $relationship = new $config[0](...$this->prepareRelationshipArgs($entity, $config));
    }

    // 1) Start a fresh engine and select desired columns
    $engine = $relationship->getQueryEngine()->select($columns);

    // 2) Apply callback: full-path callback takes precedence over base
    if (isset($this->includeCallbacks[$relationPath])) {
      ($this->includeCallbacks[$relationPath])($engine);
    } elseif (isset($this->includeCallbacks[$base])) {
      ($this->includeCallbacks[$base])($engine);
    }

    // 3) Fetch related records using the filtered engine
    $relatedMap = $relationship->deepFetchUsingEngine($items, $engine);

    // 4) Determine lookup key and map results back to parents
    $lookupKey = ($relationship instanceof Bridge || $relationship instanceof HasMultiple)
      ? 'id'
      : $relationship->getForeignKey();

    foreach ($items as $parent) {
      $key  = $parent->{$lookupKey} ?? null;
      $data = $relatedMap[$key] ?? [];

      if ($relationship instanceof HasOne || $relationship instanceof BelongsTo) {
        $parent->setDeepFetch($base, $data ?: null);
      } else {
        $parent->setDeepFetch($base, new DataSet($data));
      }
    }

    // 5) Recurse for nested relations
    if ($nested) {
      $childRecords = [];
      foreach ($relatedMap as $group) {
        if ($group instanceof DataSet) {
          $childRecords = array_merge($childRecords, $group->all());
        } elseif (is_array($group)) {
          $childRecords = array_merge($childRecords, $group);
        } elseif ($group !== null) {
          $childRecords[] = $group;
        }
      }

      if (!empty($childRecords)) {
        $this->loadRelation(new DataSet($childRecords), $nested, $columns);
      }
    }
  }

  /**
   * Prepare the constructor args for each relationship type.
   *
   * @param Entity $parent
   * @param array $config
   * @return array
   */
  private function prepareRelationshipArgs(Entity $parent, array $config): array
  {
    if ($config[0] === Bridge::class) {
      return [$parent, $config[1], $config[2], $config[3], $config[4]];
    }
    return [$parent, $config[1], $config[2] ?? null];
  }

  /**
   * Format loaded data based on relationship.
   *
   * @param mixed $relationship
   * @param mixed $data
   * @return mixed
   */
  private function formatLoadedData($relationship, $data)
  {
    return match (true) {
      $relationship instanceof HasOne,
      $relationship instanceof BelongsTo     => $data,
      $relationship instanceof HasMultiple,
      $relationship instanceof Bridge        => $data instanceof DataSet ? $data : new DataSet($data),
      default                               => null,
    };
  }
}
