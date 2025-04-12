<?php

namespace Pocketframe\PocketORM\Schema;

class SchemaRecord
{
  public function __construct(
    public readonly string $name,
    public readonly string $path,
    public readonly string $appliedAt
  ) {}
}
