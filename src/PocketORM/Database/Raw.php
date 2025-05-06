<?php

namespace Pocketframe\PocketORM\Database;

class Raw
{
  public string $value;
  public function __construct(string $value)
  {
    $this->value = $value;
  }
}
