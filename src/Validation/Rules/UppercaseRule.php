<?php

declare(strict_types=1);

namespace Pocketframe\Validation\Rules;

use Pocketframe\Contracts\Rule;

class UppercaseRule implements Rule
{
  public function isValid(mixed $value): bool
  {
    if (!is_string($value)) {
      return false;
    }
    return $value === strtoupper($value);
  }

  public function message(string $field): string
  {
    return "The :attribute field must be in uppercase.";
  }
}
