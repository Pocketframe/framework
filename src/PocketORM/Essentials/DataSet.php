<?php

namespace Pocketframe\PocketORM\Essentials;

use ArrayIterator;
use IteratorAggregate;

class DataSet implements IteratorAggregate
{
  private array $records;

  public function __construct(array $records = [])
  {
    $this->records = $records;
  }

  /**
   * Convert the dataset to an array.
   *
   * @return array
   */
  public function toArray(): array
  {
    return array_map(fn($r) => (array)$r, $this->records);
  }

  /**
   * Get all records.
   *
   * @return array
   */
  public function all(): array
  {
    return $this->records;
  }

  /**
   * Get the first record.
   *
   * @return ?object
   */
  public function first(): ?object
  {
    $record = $this->records[0] ?? null;
    return $record ? (object)$record : null;
  }

  /**
   * Map the dataset to a new dataset.
   *
   * @param callable $callback
   * @return self
   */
  public function map(callable $callback): self
  {
    return new self(array_map($callback, $this->records));
  }

  /**
   * Filter the dataset.
   *
   * @param callable $callback
   * @return self
   */
  public function filter(callable $callback): self
  {
    return new self(array_filter($this->records, $callback));
  }

  /**
   * Pluck a property from the dataset.
   *
   * @param string $property
   * @return array
   */
  public function pluck(string $property): array
  {
    return array_column($this->records, $property);
  }

  /**
   * Get the count of records.
   *
   * @return int
   */
  public function count(): int
  {
    return count($this->records);
  }

  /**
   * Get an iterator for the dataset.
   *
   * @return ArrayIterator
   */
  public function getIterator(): ArrayIterator
  {
    return new ArrayIterator($this->records);
  }
}
