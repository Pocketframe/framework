<?php

declare(strict_types=1);

namespace Pocketframe\Validation\Rules;

use Pocketframe\Contracts\Rule;

class ArrayRule implements Rule
{
  public function isValid($value): bool
  {
    return is_array($value);
  }

  public function message(string $field): string
  {
    return "The {$field} must be an array.";
  }
}
