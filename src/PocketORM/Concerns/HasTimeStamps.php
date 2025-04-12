<?php

namespace Pocketframe\PocketORM\Concerns;

use Carbon\Carbon;
use Pocketframe\PocketORM\Schema\Schema;

trait HasTimeStamps
{
  protected array $dates = ['created_at', 'updated_at'];
  protected bool $timestamps = true;

  public function freshTimeStamp(): Carbon
  {
    return Carbon::now();
  }

  protected function updateTimeStamps(): void
  {
    $table = static::getTable();

    if (!$this->exists() && Schema::tableHasColumn($table, 'created_at')) {
      $this->attributes['created_at'] = Carbon::now();
    }

    if (Schema::tableHasColumn($table, 'updated_at')) {
      $this->attributes['updated_at'] = Carbon::now();
    }
  }

  protected function convertDates(): void
  {
    foreach ($this->dates as $date) {
      if (isset($this->attributes[$date])) {
        $this->attributes[$date] = Carbon::parse($this->attributes[$date]);
      }
    }
  }
}
