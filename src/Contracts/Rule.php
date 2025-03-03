<?php

namespace Pocketframe\Contracts;

interface Rule
{
  public function isValid($value): bool;

  /**
   * Returns the error message for this rule.
   * @return string|string[]
   */
  public function message(): string|array;
}
