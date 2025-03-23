<?php

namespace Pocketframe\PocketORM\Concerns;

use Pocketframe\Essentials\Utilities\StringUtils;
use Pocketframe\PocketORM\Database\EntityMapper;

trait Trashable
{
  protected bool $withTrashed = false;

  public static function bootTrashable(): void
  {
    static::addGlobalScope(function ($query) {
      $query->whereNull('trashed_at');
    });
  }

  public function trash(): void
  {
    $this->attributes['trashed_at'] = StringUtils::now();
    EntityMapper::persist($this);
  }

  public function restore(): void
  {
    $this->attributes['trashed_at'] = null;
    EntityMapper::persist($this);
  }

  public function withTrashed(): self
  {
    $this->withTrashed = true;
    return $this;
  }

  public function onlyTrashed(): self
  {
    static::addGlobalScope(function ($query) {
      $query->whereNotNull('trashed_at');
    });
    return $this;
  }
}
