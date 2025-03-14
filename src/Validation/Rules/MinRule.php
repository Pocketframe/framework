<?php

declare(strict_types=1);

namespace Pocketframe\Validation\Rules;

use Pocketframe\Contracts\Rule;
use Pocketframe\Http\Request\UploadedFile;

class MinRule implements Rule
{
  protected int|float $min;

  /**
   * @param int|float $min The minimum value allowed. For file uploads, assume this is in kilobytes.
   */
  public function __construct(int|float $min)
  {
    $this->min = $min;
  }

  public function isValid(mixed $value): bool
  {
    // If value is numeric, compare as a number.
    if (is_numeric($value)) {
      return $value >= $this->min;
    }

    // If value is a string, compare its length.
    if (is_string($value)) {
      return mb_strlen($value) >= $this->min;
    }

    // If value is an array and represents a file (has a 'size' key), assume file size in bytes.
    if (is_array($value) && isset($value['size'])) {
      // Convert min from kilobytes to bytes.
      return $value['size'] >= ($this->min * 1024);
    }

    // If value is an instance of UploadedFile, use its getSize() method.
    if ($value instanceof UploadedFile) {
      return $value->getSize() >= ($this->min * 1024);
    }

    return false;
  }

  public function message(string $field): string
  {
    return "The :attribute field must be at least {$this->min}.";
  }
}
