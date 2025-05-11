<?php

namespace Pocketframe\PocketORM\Concerns;

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
   *
   * @var array<string, string[]>
   */
  private array $includes = [];

  /**
   * Define relationships to include (with optional columns).
   */
  public function include(array $relations): static
  {
    foreach ($relations as $relation) {
      if (str_contains($relation, ':')) {
        [$name, $cols] = explode(':', $relation, 2);
        $this->includes[$name] = array_map('trim', explode(',', $cols));
      } else {
        $this->includes[$relation] = ['*'];
      }
    }
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

    $items = $records->all();
    if (empty($items)) {
      return;
    }

    // Instantiate relationship on the first record
    $firstConfig = reset($items)->getRelationshipConfig($base);
    $relationship = new $firstConfig[0](...$this->prepareRelationshipArgs(reset($items), $firstConfig));

    // deepFetch with selected columns
    $relatedMap = $relationship->deepFetch($items, $columns);

    // Decide lookup key
    $lookupKey = ($relationship instanceof Bridge || $relationship instanceof HasMultiple)
      ? 'id'
      : $relationship->getForeignKey();

    // Attach results
    foreach ($items as $parent) {
      $key = $parent->{$lookupKey} ?? null;
      $data = $relatedMap[$key] ?? [];
      $parent->setDeepFetch($base, $this->formatLoadedData($relationship, $data));
    }

    // If nested, recurse into the next segment(s)
    if ($segments) {
      $nextRelation = implode('.', $segments);
      $allChild = [];
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
      // [Bridge, RelatedClass, pivotTable, parentKey, relatedKey]
      return [$parent, $config[1], $config[2], $config[3], $config[4]];
    }
    // [HasOne/HasMultiple/OwnedBy, RelatedClass, foreignKey]
    return [$parent, $config[1], $config[2] ?? null];
  }

  /**
   * Format loaded data based on relationship.
   */
  private function formatLoadedData($relationship, $data)
  {
    return match (true) {
      $relationship instanceof HasOne,
      $relationship instanceof OwnedBy => $data,
      $relationship instanceof HasMultiple,
      $relationship instanceof Bridge     => $data instanceof DataSet ? $data : new DataSet($data),
      default                          => null,
    };
  }
}
