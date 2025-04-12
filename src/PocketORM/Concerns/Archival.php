<?php

namespace Pocketframe\PocketORM\Concerns;

use Pocketframe\Essentials\Utilities\Utilities;
use Pocketframe\PocketORM\Database\DataMapping;

trait Archival
{
  protected bool $archiving = true;

  public static function bootArchival(): void
  {
    static::addGlobalScope(function ($query) {
      $query->whereNull('archived_at');
    });
  }

  public function archive(): void
  {
    $this->attributes['archived_at'] = Utilities::now();
    DataMapping::persist($this);
  }

  public function unarchive(): void
  {
    $this->attributes['archived_at'] = null;
    DataMapping::persist($this);
  }

  public function withArchived(): self
  {
    $this->archiving = false;
    return $this;
  }
}
