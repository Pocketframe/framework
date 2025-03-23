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

  public function toArray(): array
  {
    // Convert each record to array
    return array_map(fn($r) => (array)$r, $this->records);
  }

  public function all(): array
  {
    return $this->records;
  }

  public function first(): ?object
  {
    $record = $this->records[0] ?? null;
    return $record ? (object)$record : null;
  }

  public function map(callable $callback): self
  {
    return new self(array_map($callback, $this->records));
  }

  public function filter(callable $callback): self
  {
    return new self(array_filter($this->records, $callback));
  }

  public function pluck(string $property): array
  {
    return array_column($this->records, $property);
  }

  public function count(): int
  {
    return count($this->records);
  }

  public function getIterator(): ArrayIterator
  {
    return new ArrayIterator($this->records);
  }
}
