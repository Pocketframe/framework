<?php

declare(strict_types=1);

namespace Pocketframe\Validation\Rules;

use Pocketframe\Contracts\Rule;

class StringRule implements Rule
{
  public function isValid(mixed $value): bool
  {
    return is_string($value);
  }

  public function message(string $field): string
  {
    return "The :attribute field must be a string.";
  }
}
