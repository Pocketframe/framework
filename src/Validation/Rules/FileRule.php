<?php

declare(strict_types=1);

namespace Pocketframe\Validation\Rules;

use Pocketframe\Contracts\Rule;

class FileRule implements Rule
{
  public function isValid(mixed $value): bool
  {
    // Expecting $value to be an array from $_FILES.
    if (!is_array($value) || !isset($value['error'])) {
      return false;
    }

    // UPLOAD_ERR_OK equals 0.
    return $value['error'] === UPLOAD_ERR_OK;
  }

  public function message(string $field): string
  {
    return "The {$field} must be a valid file.";
  }
}
