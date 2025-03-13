<?php

declare(strict_types=1);

namespace Pocketframe\Validation\Rules;

use Pocketframe\Contracts\Rule;

class MaxRule implements Rule
{
  protected int|float $max;

  public function __construct(int|float $max)
  {
    $this->max = $max;
  }

  public function isValid(mixed $value): bool
  {
    // If value is numeric, compare the number
    if (is_numeric($value)) {
      return $value <= $this->max;
    }

    // If value is a string, compare its length
    if (is_string($value)) {
      return mb_strlen($value) <= $this->max;
    }

    return false;
  }

  public function message(string $field): string
  {
    return "The :attribute field must not exceed {$this->max}.";
  }
}
