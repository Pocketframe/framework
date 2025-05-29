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
 */
trait DeepFetch
{
  /**
   * Tree of include definitions: node => ['columns'=>[], 'callback'=>Closure|null, 'children'=>[]]
   * @var array<string, array>
   */
  private array $includeTree = [];

  /**
   * Register relations to include, with optional columns and callbacks.
   * Supports nested dot-notation or shorthand array syntax.
   * @param array<string, string|Closure|array> $relations
   * @return static
   */
  public function include(array $relations): static
  {
    $baseEntity = method_exists($this, 'getEntity') ? $this->getEntity() : null;
    foreach ($relations as $key => $value) {
      if (is_array($value) && is_string($key)) {
        // shorthand: ['posts' => ['comments','tags']] => include ['posts.comments','posts.tags']
        foreach ($value as $sub) {
          $this->include(["{$key}.{$sub}"]);
        }
      } else {
        $path = is_int($key) ? $value : $key;
        [$name, $cols] = $this->parseIncludeDef($path);
        $callback = $value instanceof Closure ? $value : null;
        $this->registerNode(explode('.', $name), $cols, $callback, $baseEntity);
      }
    }
    return $this;
  }

  /**
   * Apply a whereHas filter and eager-load the relation.
   * @param string $relationPath
   * @param Closure $filter
   * @param string|array $columns
   * @return static
   */
  public function includeWhereHas(string $relationPath, Closure $filter, string|array $columns = ['*']): static
  {
    if (is_string($columns)) {
      [, $columns] = $this->parseIncludeDef("{$relationPath}:{$columns}");
    }
    $root = explode('.', $relationPath, 2)[0];
    $this->whereHas($root, $filter);
    return $this->include([
      $relationPath => function ($q) use ($filter, $columns) {
        $filter($q);
        if ($columns !== ['*']) {
          $q->select($columns);
        }
      }
    ]);
  }

  /**
   * After fetching raw records, apply this to run eager loading.
   * @param DataSet $records
   * @return DataSet
   */
  public function applyEagerLoads(DataSet $records): DataSet
  {
    // If no records, nothing to load
    if (count($records) === 0) {
      return $records;
    }

    foreach ($this->includeTree as $relation => $node) {
      $this->loadNode($records, $relation, $node);
    }
    return $records;
  }

  /**
   * Register a node recursively into includeTree.
   */
  private function registerNode(array $segments, array $columns, ?Closure $callback, $base): void
  {
    $tree = &$this->includeTree;
    $entity = $base;
    while ($segment = array_shift($segments)) {
      if ($entity instanceof Entity && !method_exists($entity, $segment) && !$entity->getRelationshipConfig($segment)) {
        throw new InvalidArgumentException("Relation '{$segment}' not found on " . get_class($entity));
      }
      $tree[$segment]['columns'] = $columns;
      if ($callback) {
        $tree[$segment]['callback'] = $callback;
        $callback = null; // only apply at top-level of this path
      }
      if (!empty($segments)) {
        $tree[$segment]['children'] ??= [];
        // advance entity for next level
        $entity = $entity?->{$segment}() ?? null;
        $tree = &$tree[$segment]['children'];
      }
    }
  }

  /**
   * Load a single node and recurse children.
   */
  private function loadNode(DataSet $parents, string $relation, array $node): void
  {
    $items = $parents->all();
    if (empty($items)) {
      return;
    }
    $sample = reset($items);
    if (method_exists($sample, $relation)) {
      $rel = $sample->{$relation}();
    } else {
      $cfg = $sample->getRelationshipConfig($relation);
      $rel = new $cfg[0](...$this->prepareRelationshipArgs($sample, $cfg));
    }
    $engine = $rel->getQueryEngine()->select($node['columns']);
    if (!empty($node['callback'])) {
      $node['callback']($engine);
    }
    $map = $rel->deepFetchUsingEngine($items, $engine);
    $this->mapResults($items, $relation, $rel, $map);

    if (!empty($node['children'])) {
      $childList = [];
      foreach ($map as $group) {
        if ($group instanceof DataSet) {
          $childList = array_merge($childList, $group->all());
        } elseif (is_array($group)) {
          $childList = array_merge($childList, $group);
        } elseif ($group !== null) {
          $childList[] = $group;
        }
      }
      if ($childList) {
        $childDS = new DataSet($childList);
        foreach ($node['children'] as $child => $childNode) {
          $this->loadNode($childDS, $child, $childNode);
        }
      }
    }
  }

  /**
   * Map fetched related records back onto parent entities.
   */
  private function mapResults(array $parents, string $relation, $rel, array $map): void
  {
    $key = ($rel instanceof Bridge || $rel instanceof HasMultiple) ? 'id' : $rel->getForeignKey();
    foreach ($parents as $p) {
      $idx = $p->{$rel instanceof Bridge ? $rel->getParentKey() : $key} ?? null;
      $data = $map[$idx] ?? [];
      if ($rel instanceof HasOne || $rel instanceof BelongsTo) {
        $p->setDeepFetch($relation, $data ?: null);
      } else {
        $p->setDeepFetch($relation, new DataSet($data));
      }
    }
  }

  /**
   * Parse "relation:col1,col2" syntax.
   */
  private function parseIncludeDef(string $def): array
  {
    if (str_contains($def, ':')) {
      [$n, $s] = explode(':', $def, 2);
      return [$n, array_map('trim', explode(',', $s))];
    }
    return [$def, ['*']];
  }

  /**
   * Prepare constructor args for relationship.
   */
  private function prepareRelationshipArgs(Entity $parent, array $cfg): array
  {
    if ($cfg[0] === Bridge::class) {
      return [$parent, $cfg[1], $cfg[2], $cfg[3], $cfg[4]];
    }
    return [$parent, $cfg[1], $cfg[2] ?? null];
  }
}
