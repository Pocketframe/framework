<?php

namespace Pocketframe\PocketORM\Essentials;

use ArrayIterator;
use IteratorAggregate;

// same as collection class
class RecordSet implements IteratorAggregate
{
  private array $records;

  public function __construct(array $records = [])
  {
    $this->records = $records;
  }

  public function all(): array
  {
    return $this->records;
  }

  public function first(): ?object
  {
    return $this->records[0] ?? null;
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

  public function toArray(): array
  {
    return json_decode(json_encode($this->records), true);
  }
}
