<?php

declare(strict_types=1);

namespace Pocketframe\Validation\Rules;

use Pocketframe\Contracts\Rule;

class LowercaseRule implements Rule
{
  public function isValid(mixed $value): bool
  {
    if (!is_string($value)) {
      return false;
    }
    return $value === strtolower($value);
  }

  public function message(string $field): string
  {
    return "The :attribute field must be in lowercase.";
  }
}
