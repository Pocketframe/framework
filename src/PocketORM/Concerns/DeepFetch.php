<?php

namespace Pocketframe\PocketORM\Concerns;

use Closure;
use Pocketframe\PocketORM\Entity\Entity;
use Pocketframe\PocketORM\Essentials\DataSet;
use Pocketframe\PocketORM\Relationships\Bridge;
use Pocketframe\PocketORM\Relationships\HasMultiple;
use Pocketframe\PocketORM\Relationships\HasOne;
use Pocketframe\PocketORM\Relationships\OwnedBy;

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
   * Define relationships to include (with optional columns) and callbacks.
   * Supports:
   *   - ['relation']
   *   - ['relation:col1,col2']
   *   - ['relation' => Closure]
   */
  public function include(array $relations): static
  {
    foreach ($relations as $key => $value) {
      $cols = ['*'];

      // Case: closure provided under a key that may include ":col1,col2"
      if ($value instanceof Closure) {
        // $key might be "relation:col1,col2"
        $def = $key;
        if (str_contains($def, ':')) {
          [$name, $colsStr] = explode(':', $def, 2);
          $cols = array_map('trim', explode(',', $colsStr));
        } else {
          $name = $def;
        }
        $this->includeCallbacks[$name] = $value;

        // Case: string or default (no callback)
      } else {
        // existing logic to handle numeric keys vs. associative, parse ":cols"
        if (is_int($key)) {
          $def = $value;
        } else {
          $def = $key . (is_string($value) ? ":{$value}" : '');
        }
        if (str_contains((string) $def, ':')) {
          [$name, $colsStr] = explode(':', $def, 2);
          $cols = array_map('trim', explode(',', $colsStr));
        } else {
          $name = (string) $def;
        }
      }

      $this->includes[$name] = $cols;
    }

    return $this;
  }

  /**
   * Filter the parent by a relation **and** eager-load that relation
   * with the same filters (plus optional column-selection).
   *
   * @param  string        $relation        The relation path (supports dot notation)
   * @param  Closure       $filter          A closure($query) that adds your where() calls
   * @param  string[]|null $columns         Optional: list of columns to select on the relation
   * @return static
   */
  public function includeWhereHas(string $relationPath, Closure $filter, array $columns = ['*']): static
  {
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
   */
  private function loadRelation(DataSet $records, string $relation, array $columns): void
  {
    $segments = explode('.', $relation);
    $base     = array_shift($segments);
    $items    = $records->all();

    if (empty($items)) {
      return;
    }

    $firstConfig = reset($items)->getRelationshipConfig($base);
    $relationship = new $firstConfig[0](...$this->prepareRelationshipArgs(reset($items), $firstConfig));

    // Apply user callback if exists
    if (isset($this->includeCallbacks[$base])) {
      ($this->includeCallbacks[$base])($relationship->getQueryBuilder());
    }

    $relatedMap = $relationship->deepFetch($items, $columns);

    $lookupKey = ($relationship instanceof Bridge || $relationship instanceof HasMultiple)
      ? 'id'
      : $relationship->getForeignKey();

    foreach ($items as $parent) {
      $key  = $parent->{$lookupKey} ?? null;
      $data = $relatedMap[$key] ?? [];
      $parent->setDeepFetch($base, $this->formatLoadedData($relationship, $data));
    }

    if ($segments) {
      $nextRelation = implode('.', $segments);
      $allChild      = [];

      foreach ($relatedMap as $group) {
        if ($group instanceof DataSet) {
          $allChild = array_merge($allChild, $group->all());
        } elseif (is_array($group)) {
          $allChild = array_merge($allChild, $group);
        } elseif ($group !== null) {
          $allChild[] = $group;
        }
      }

      if ($allChild) {
        $this->loadRelation(new DataSet($allChild), $nextRelation, $columns);
      }
    }
  }

  /**
   * Prepare the constructor args for each relationship type.
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
   */
  private function formatLoadedData($relationship, $data)
  {
    return match (true) {
      $relationship instanceof HasOne,
      $relationship instanceof OwnedBy     => $data,
      $relationship instanceof HasMultiple,
      $relationship instanceof Bridge      => $data instanceof DataSet ? $data : new DataSet($data),
      default                             => null,
    };
  }
}
