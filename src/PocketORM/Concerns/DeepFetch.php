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

  /**
   * @param  string        $relationPath  dot-notation path, e.g. "student_registrations.student_class.streams"
   * @param  Closure       $filter        filters to apply to the *root* relation inside the EXISTS and on eager-load
   * @param  array         $columnsMap    either:
   *     • a plain list of columns (['id','status',…]) to apply only on the deepest table, or
   *     • an associative map of path → columnLists, e.g.
   *          [
   *            'student_registrations'                       => ['id','status','term','year','studentId'],
   *            'student_registrations.student_class'         => ['id','class_name','prefix'],
   *            'student_registrations.student_class.streams' => ['id','stream'],
   *          ]
   */
  public function includeWhereHas(
    string $relationPath,
    Closure $filter,
    array $columnsMap = ['*']
  ): static {
    // 1) Filter parents by root
    $root = explode('.', $relationPath, 2)[0];
    $this->whereHas($root, $filter);

    // 2) Build the list of all paths we'll eager-load
    $segments = explode('.', $relationPath);
    $paths = [];
    $acc = '';
    foreach ($segments as $seg) {
      $acc = $acc ? "{$acc}.{$seg}" : $seg;
      $paths[] = $acc;
    }

    // 3) Turn that into the single include() call, extracting columns per path
    $includeSpecs = [];
    foreach ($paths as $path) {
      // determine which columns to select
      if (isset($columnsMap[$path])) {
        $cols = $columnsMap[$path];
      } elseif ($path === end($paths) && array_values($columnsMap) === $columnsMap) {
        // user passed a plain list; apply it only at deepest level
        $cols = $columnsMap;
      } else {
        $cols = ['*'];
      }

      // register the callback only on the root segment
      if ($path === $root) {
        $includeSpecs["{$path}:" . implode(',', $cols)] = $filter;
      } else {
        $includeSpecs["{$path}:" . implode(',', $cols)] = null;
      }
    }

    // 4) Finally register exactly one include() with all of them
    $this->include($includeSpecs);

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
