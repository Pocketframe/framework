<?php

namespace Pocketframe\Validation\Rules;

use Pocketframe\Contracts\Rule;

class Required implements Rule
{
  public function isValid(mixed $value): bool
  {
    return isset($value) && trim((string)$value) !== '';
  }

  public function message(string $field): string
  {
    return "The field {$field} is required.";
  }
}
