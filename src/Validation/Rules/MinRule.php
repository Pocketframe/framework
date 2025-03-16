<?php

declare(strict_types=1);

namespace Pocketframe\Validation\Rules;

use Pocketframe\Contracts\Rule;
use Pocketframe\Http\Request\UploadedFile;

class MinRule implements Rule
{
  protected int|float $min;
  protected $value;

  /**
   * @param int|float $min The minimum value allowed. For file uploads, assume this is in kilobytes.
   */
  public function __construct(int|float $min)
  {
    $this->min = $min;
    $this->value = null;
  }

  public function isValid(mixed $value): bool
  {
    $this->value = $value;
    // If value is numeric, compare as a number.
    if (is_numeric($this->value)) {
      return $this->value >= $this->min;
    }

    // If value is a string, compare its length.
    if (is_string($this->value)) {
      return mb_strlen($this->value) >= $this->min;
    }

    // If value is an array and represents a file (has a 'size' key), assume file size in bytes.
    if (is_array($this->value) && isset($this->value['size'])) {
      // Convert min from kilobytes to bytes.
      return $this->value['size'] >= ($this->min * 1024);
    }

    // If value is an instance of UploadedFile, use its getSize() method.
    if ($this->value instanceof UploadedFile) {
      return $this->value->getSize() >= ($this->min * 1024);
    }

    return false;
  }

  public function message(string $field): string
  {
    if ($this->value instanceof UploadedFile) {
      return "The :attribute must not exceed {$this->min} kilobytes.";
    } else {
      return "The :attribute must be at least {$this->min} characters long.";
    }
  }
}
