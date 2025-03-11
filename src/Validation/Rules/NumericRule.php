<?php

namespace Pocketframe\Validation\Rules;

use Pocketframe\Contracts\Rule;

class NumericRule implements Rule
{
  public function isValid(mixed $value): bool
  {
    return is_numeric($value);
  }

  public function message(string $field): string
  {
    return "The :attribute field must be a number.";
  }
}
