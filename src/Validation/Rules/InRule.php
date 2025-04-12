<?php

namespace Pocketframe\Validation\Rules;

use Pocketframe\Contracts\Rule;

class InRule implements Rule
{
  private array $allowedValues;

  public function __construct(string $values)
  {
    $this->allowedValues = explode(',', $values);
  }

  public function isValid($value): bool
  {
    return in_array($value, $this->allowedValues, true);
  }

  public function message(string $field): string
  {
    $allowed = implode(', ', $this->allowedValues);
    return "The $field must be one of: $allowed";
  }
}
