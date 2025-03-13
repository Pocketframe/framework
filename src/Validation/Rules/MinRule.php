<?php

declare(strict_types=1);

namespace Pocketframe\Validation\Rules;

use Pocketframe\Contracts\Rule;

class MinRule implements Rule
{
  protected int|float $min;

  public function __construct(int|float $min)
  {
    $this->min = $min;
  }

  public function isValid(mixed $value): bool
  {
    // If value is numeric, compare the number
    if (is_numeric($value)) {
      return $value >= $this->min;
    }

    // If value is a string, compare its length
    if (is_string($value)) {
      return mb_strlen($value) >= $this->min;
    }

    return false;
  }

  public function message(string $field): string
  {
    return "The :attribute field must be at least {$this->min}.";
  }
}
