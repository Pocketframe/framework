<?php

namespace Pocketframe\Contracts;

interface Rule
{
  public function isValid($value): bool;

  /**
   * Returns the default error message for this rule.
   *
   * @param string $attribute The field name.
   * @return string
   */
  public function message(string $attribute): string;
}
