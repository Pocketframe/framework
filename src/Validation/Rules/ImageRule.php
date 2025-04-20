<?php

declare(strict_types=1);

namespace Pocketframe\Validation\Rules;

use Pocketframe\Contracts\Rule;

class ImageRule implements Rule
{
  public function isValid(mixed $value): bool
  {
    // Expect $value as an array from $_FILES.
    if (!is_array($value) || !isset($value['type'])) {
      return false;
    }

    // Allowed image MIME types.
    $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    return in_array($value['type'], $allowedMimeTypes, true);
  }

  public function message(string $field): string
  {
    return "The {$field} must be a valid image.";
  }
}
