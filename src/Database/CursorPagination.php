<?php

namespace Pocketframe\Database;

use IteratorAggregate;
use ArrayIterator;
use Traversable;

class CursorPagination implements IteratorAggregate
{
  protected array $data;
  protected ?string $nextCursor;
  protected ?string $prevCursor;
  protected bool $hasNext;
  protected bool $hasPrev;
  protected int $perPage;

  public function __construct(
    array $data,
    ?string $nextCursor,
    ?string $prevCursor,
    int $perPage
  ) {
    $this->data = $data;
    $this->nextCursor = $nextCursor;
    $this->prevCursor = $prevCursor;
    $this->hasNext = $nextCursor !== null;
    $this->hasPrev = $prevCursor !== null;
    $this->perPage = $perPage;
  }

  // Add getters and other methods similar to your Pagination class
  public function data(): array
  {
    return $this->data;
  }
  public function nextCursor(): ?string
  {
    return $this->nextCursor;
  }
  public function prevCursor(): ?string
  {
    return $this->prevCursor;
  }
  public function hasNext(): bool
  {
    return $this->hasNext;
  }
  public function hasPrev(): bool
  {
    return $this->hasPrev;
  }
  public function perPage(): int
  {
    return $this->perPage;
  }

  public function getIterator(): Traversable
  {
    return new ArrayIterator($this->data);
  }
}
