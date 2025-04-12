<?php

namespace Pocketframe\PocketORM\Concerns;

use Carbon\Carbon;
use Pocketframe\PocketORM\Database\EntityMapper;
use Pocketframe\PocketORM\Schema\Schema;

trait Trashable
{
  protected bool $withTrashed = false;

  public static function bootTrashable(): void
  {
    static::addGlobalScope(function ($query) {
      $model = new static;
      $column = $model->getTrashColumn();
      $restoreValue = $model->getRestoreValue();

      if ($restoreValue === null) {
        $query->whereNull($column);
      } else {
        $query->where($column, $restoreValue);
      }
    });
  }

  public function trash(): self
  {
    $table = static::getTable();
    $trashColumn = static::$trashColumn;

    if (Schema::tableHasColumn($table, $trashColumn)) {
      $this->attributes[$trashColumn] = Carbon::now()->toDateTimeString();
      EntityMapper::persist($this);
    }
    return $this;
  }


  public function restore(): self
  {
    $table = static::getTable();
    $trashColumn = static::$trashColumn;

    if (Schema::tableHasColumn($table, $trashColumn)) {
      $this->attributes[$trashColumn] = null;
      EntityMapper::persist($this);
    }
    return $this;
  }

  public function withTrashed(): self
  {
    $this->withTrashed = true;
    static::addGlobalScope(function ($query) {
      // Remove the default scope to include trashed records
    });
    return $this;
  }

  public function onlyTrashed(): self
  {
    $column = $this->getTrashColumn();
    $trashValue = $this->getTrashValue();

    static::addGlobalScope(function ($query) use ($column, $trashValue) {
      if ($trashValue === null) {
        $query->whereNotNull($column);
      } else {
        $query->where($column, $trashValue);
      }
    });
    return $this;
  }

  protected function getTrashColumn(): string
  {
    return property_exists($this, 'trashColumn') ? $this->trashColumn : 'trashed_at';
  }

  protected function getTrashValue()
  {
    return property_exists($this, 'trashValue') ? $this->trashValue : null;
  }

  protected function getRestoreValue()
  {
    return property_exists($this, 'restoreValue') ? $this->restoreValue : null;
  }
}
