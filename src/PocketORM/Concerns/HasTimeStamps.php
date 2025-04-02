<?php

namespace Pocketframe\PocketORM\Concerns;

use Carbon\Carbon;

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
    if ($this->timestamps) {
      $time = $this->freshTimeStamp();

      if (!$this->exists() && !isset($this->attributes['created_at'])) {
        $this->attributes['created_at'] = $time;
      }

      $this->attributes['updated_at'] = $time;
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
