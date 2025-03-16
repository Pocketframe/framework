<?php

declare(strict_types=1);

namespace Pocketframe\Validation\Rules;

use Pocketframe\Contracts\Rule;

class SometimesRule implements Rule
{
  public function isValid(mixed $value): bool
  {
    // This rule always returns true.
    // Its purpose is to signal that if the field is absent, other validations are skipped.
    return true;
  }

  public function message(string $field): string
  {
    // Should never be triggered.
    return "";
  }
}
