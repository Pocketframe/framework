<?php

declare(strict_types=1);

namespace Pocketframe\Validation\Rules;

use Pocketframe\Contracts\Rule;

class RequiredRule implements Rule
{
  public function isValid(mixed $value): bool
  {
    // Handle file uploads
    if (is_array($value) && isset($value['error'])) {
      return $value['error'] !== UPLOAD_ERR_NO_FILE && $value['error'] === UPLOAD_ERR_OK;
    }

    // Handle regular arrays
    if (is_array($value)) {
      return !empty($value);
    }

    // Handle scalar values
    return isset($value) && trim((string)$value) !== '';
  }

  public function message(string $field): string
  {
    return "The :attribute field is required.";
  }
}
