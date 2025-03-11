<?php

namespace Pocketframe\Validation\Rules;

use Pocketframe\Contracts\Rule;

class EmailRule implements Rule
{
  public function isValid(mixed $value): bool
  {
    return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
  }

  public function message(string $field): string
  {
    return "The :attribute field must be a valid email address.";
  }
}
