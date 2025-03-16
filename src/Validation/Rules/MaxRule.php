<?php

declare(strict_types=1);

namespace Pocketframe\Validation\Rules;

use Pocketframe\Contracts\Rule;
use Pocketframe\Http\Request\UploadedFile;

class MaxRule implements Rule
{
  protected int|float $max;
  protected $value;

  /**
   * @param int|float $max The maximum value allowed. For file uploads, assume this is in kilobytes.
   */
  public function __construct(int|float $max)
  {
    $this->max = $max;
    $this->value = null;
  }

  public function isValid(mixed $value): bool
  {
    $this->value = $value;
    // If value is numeric, compare as a number.
    if (is_numeric($this->value)) {
      return $this->value <= $this->max;
    }

    // If value is a string, compare its length.
    if (is_string($this->value)) {
      return mb_strlen($this->value) <= $this->max;
    }

    // If value is an array and represents a file (has a 'size' key), assume file size in bytes.
    if (is_array($this->value) && isset($this->value['size'])) {
      // Convert max from kilobytes to bytes.
      return $this->value['size'] <= ($this->max * 1024);
    }

    // If value is an instance of UploadedFile, use its getSize() method.
    if ($this->value instanceof UploadedFile) {
      return $this->value->getSize() <= ($this->max * 1024);
    }

    return false;
  }

  public function message(string $field): string
  {
    if ($this->value instanceof UploadedFile) {
      return "The :attribute must not exceed {$this->max} kilobytes.";
    } else {
      return "The :attribute must be at least {$this->max} characters long.";
    }
  }
}
