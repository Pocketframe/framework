<?php

namespace Pocketframe\PocketORM\Concerns;

use Closure;
use Pocketframe\PocketORM\Entity\Entity;
use Pocketframe\PocketORM\Essentials\DataSet;
use Pocketframe\PocketORM\QueryEngine\QueryEngine;
use Pocketframe\PocketORM\Relationships\Bridge;
use Pocketframe\PocketORM\Relationships\HasMultiple;
use Pocketframe\PocketORM\Relationships\HasOne;
use Pocketframe\PocketORM\Relationships\BelongsTo;

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

  public function includeWhereHas(
    string $relationPath,
    Closure $filter,
    array $columnsMap = ['*']
  ): static {
    // 1) Filter parents by the root relation
    $root = explode('.', $relationPath, 2)[0];
    $this->whereHas($root, $filter);

    // 2) Build every sub-path: [ 'student_registrations',
    //                             'student_registrations.student_class',
    //                             'student_registrations.student_class.streams' ]
    $parts = explode('.', $relationPath);
    $acc   = [];
    $specs = [];

    foreach ($parts as $i => $part) {
      $acc[]   = $part;
      $subPath = implode('.', $acc);

      // decide columns for THIS subPath
      if (isset($columnsMap[$subPath])) {
        $cols = $columnsMap[$subPath];
      } elseif ($subPath === $relationPath && array_values($columnsMap) === $columnsMap) {
        // flat list only applies at deepest level
        $cols = $columnsMap;
      } else {
        // *only* include automatically if it’s root or deepest:
        if ($subPath !== $root && $subPath !== $relationPath) {
          continue;
        }
        $cols = ['*'];
      }

      // only at the root do we re-apply your filter
      $specs["{$subPath}:" . implode(',', $cols)]
        = $subPath === $root
        ? $filter
        : null;
    }

    // 3) Fire a single include() call
    $this->include($specs);

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
  private function loadRelation(DataSet $records, string $relationPath, array $columns): void
  {
    $segments = explode('.', $relationPath);
    $base     = array_shift($segments);
    $nested   = $segments ? implode('.', $segments) : null;
    $items    = $records->all();
    if (empty($items)) {
      return;
    }

    // 1) get config & instantiate handler
    $config       = reset($items)->getRelationshipConfig($base);
    [$type, $relatedClass /*, …rest*/] = $config;
    $relationship = new $type(...$this->prepareRelationshipArgs(reset($items), $config));

    // 2) figure out the actual table name for this relation
    $table = $relatedClass::getTable();

    // 3) rewrite unqualified columns but leave '*' and 'table.*' alone
    $qualified = array_map(function (string $col) use ($table) {
      if ($col === '*') {
        return '*';
      }
      if (preg_match('/^[^\.]+\.\*$/', $col)) {
        return $col;
      }
      if (strpos($col, '.') !== false) {
        return $col;
      }
      return "{$table}.{$col}";
    }, $columns);

    // 4) grab & configure the engine
    $engine = $relationship
      ->getQueryEngine()    // fresh QueryEngine for $relatedClass
      ->select($qualified); // only these columns

    // 5) apply your include‐callback if present
    if (isset($this->includeCallbacks[$relationPath])) {
      ($this->includeCallbacks[$relationPath])($engine);
    } elseif (isset($this->includeCallbacks[$base])) {
      ($this->includeCallbacks[$base])($engine);
    }

    // 6) do the deepFetch with your fully‐configured engine
    $relatedMap = $relationship->deepFetchUsingEngine($items, $engine);

    // 7) map back onto parents
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

    // 8) recurse for nested segments
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
      $relationship instanceof BelongsTo     => $data,
      $relationship instanceof HasMultiple,
      $relationship instanceof Bridge      => $data instanceof DataSet ? $data : new DataSet($data),
      default                             => null,
    };
  }
}
