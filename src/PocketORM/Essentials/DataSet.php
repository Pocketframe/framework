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
    return array_map(function ($record) {
      return is_object($record) ? get_object_vars($record) : $record;
    }, $this->records);
  }

  /**
   * Static factory method for building a new DataSet instance.
   *
   * @param array $records
   * @return self
   */
  public static function for(array $records): self
  {
    return new self($records);
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
   * @param string $column
   * @return array
   */
  public function pluck(string $column): array
  {
    return $this->getColumn($column);
  }


  public function getColumn(string $column): array
  {
    return array_map(function ($item) use ($column) {
      return is_object($item) ? $item->{$column} : $item[$column];
    }, $this->records);
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
