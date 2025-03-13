<?php

declare(strict_types=1);

namespace Pocketframe\Validation\Rules;

use Pocketframe\Contracts\Rule;

class DateRule implements Rule
{
  public function isValid(mixed $value): bool
  {
    // Fail if value is empty.
    if (empty($value)) {
      return false;
    }
    // strtotime returns false if the date is not valid.
    return strtotime($value) !== false;
  }

  public function message(string $field): string
  {
    return "The :attribute field must be a valid date.";
  }
}
