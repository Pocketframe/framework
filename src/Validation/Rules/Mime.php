<?php

declare(strict_types=1);

namespace Pocketframe\Validation\Rules;

use Pocketframe\Contracts\Rule;
use Pocketframe\Http\Request\UploadedFile;

class Mime implements Rule
{
  protected array $allowed;

  /**
   * Mime constructor.
   *
   * @param array $allowed An array of allowed file extensions (without the dot), e.g. ['jpg', 'png', 'pdf'].
   */
  public function __construct(array $allowed)
  {
    // change allowed extensions to lowercase
    $this->allowed = array_map('strtolower', $allowed);
  }

  /**
   * Check if the file's extension is allowed.
   *
   * @param mixed $value The file upload value, either as an array from $_FILES or an UploadedFile instance.
   * @return bool
   */
  public function isValid(mixed $value): bool
  {
    // If $value is an array (as in $_FILES)
    if (is_array($value) && isset($value['name'])) {
      $extension = strtolower(pathinfo($value['name'], PATHINFO_EXTENSION));
      return in_array($extension, $this->allowed, true);
    }
    // If $value is an instance of UploadedFile, use its method
    if ($value instanceof UploadedFile) {
      $extension = strtolower($value->getClientOriginalExtension());
      return in_array($extension, $this->allowed, true);
    }
    return false;
  }

  /**
   * Return the validation error message.
   *
   * @param string $attribute The field name.
   * @return string
   */
  public function message(string $attribute): string
  {
    $allowedList = implode(', ', $this->allowed);
    return "The :attribute must be a file of type: $allowedList.";
  }
}
