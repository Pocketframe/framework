<?php

namespace Pocketframe\PocketORM\Relationships;

use Pocketframe\PocketORM\QueryEngine\QueryEngine;

trait RelationshipUtils
{
  /**
   * Chunk query results
   *
   * Chunk a whereIn query for large sets.
   *
   * @param QueryEngine $query
   * @param string $column
   * @param array $values
   * @param int $chunkSize
   * @return array
   */
  protected function chunkedWhereIn(QueryEngine $query, string $column, array $values, int $chunkSize = 500): array
  {
    $results = [];
    foreach (array_chunk($values, $chunkSize) as $chunk) {
      $results = array_merge($results, $query->whereIn($column, $chunk)->get()->all());
    }
    return $results;
  }

  /**
   * Group records by a key, skipping nulls.
   *
   * @param array $records
   * @param string $key
   * @return array
   */
  protected static function groupByKey(array $records, string $key): array
  {
    $grouped = [];
    foreach ($records as $record) {
      $value = $record->{$key} ?? null;
      if ($value !== null) {
        $grouped[$value][] = $record;
      }
    }
    return $grouped;
  }
}
