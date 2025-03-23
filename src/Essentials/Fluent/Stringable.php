<?php

namespace Pocketframe\Essentials\Fluent;

use Pocketframe\Essentials\Utilities\StringUtils;

class Stringable
{
  protected $value;

  public function __construct(string $value)
  {
    $this->value = $value;
  }

  // Fluent methods (chainable)
  public function slug(string $separator = '-'): self
  {
    $this->value = StringUtils::slug($this->value, $separator);
    return $this;
  }

  public function truncate(int $limit, string $end = '...'): self
  {
    $this->value = StringUtils::truncate($this->value, $limit, $end);
    return $this;
  }

  public function camelCase(): self
  {
    $this->value = StringUtils::camelCase($this->value);
    return $this;
  }

  // Terminate the chain and get the result
  public function toString(): string
  {
    return $this->value;
  }

  // Alias for toString()
  public function get(): string
  {
    return $this->value;
  }
}
