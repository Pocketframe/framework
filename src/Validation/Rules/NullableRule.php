<?php

declare(strict_types=1);

namespace Pocketframe\Validation\Rules;

use Pocketframe\Contracts\Rule;

class NullableRule implements Rule
{
  public function isValid(mixed $value): bool
  {
    // If value is null or an empty string, consider it valid.
    return true;
  }

  public function message(string $field): string
  {
    return "The :attribute field may be null.";
  }
}
