<?php

namespace Pocketframe\PocketORM\Concerns;

use Carbon\Carbon;
use Pocketframe\PocketORM\Entity\EntityMapper;
use Pocketframe\PocketORM\QueryEngine\QueryEngine;

/**
 * Trait Trashable
 *
 * Allows any Entity to “trash itself by writing to a custom column.
 *
 * Entities can override these three static properties:
 *
 *   which column to use for trashing (default: `trashed_at`)
 *   protected static string $trashColumn  = 'trashed_at';
 *
 *   what value to write when trashing;
 *   if null, a timestamp will be used (e.g. for date‑based deletes)
 *   protected static $trashValue         = null;
 *
 *   what value to write when restoring;
 *   default is null (e.g. for nulling a datetime), but you can set 1/0, 'active'/'inactive', etc.
 *   protected static $restoreValue       = null;
 */
trait Trashable
{
  /**
   * Get the trash column name from the entity class
   *
   * @return string The trash column name
   */
  public static function getTrashColumn()
  {
    return static::$trashColumn ?? 'trashed_at';
  }

  /**
   * Get the trash value from the entity class
   *
   * @return ?string The trash value or null if not set
   */
  public static function getTrashValue()
  {
    return static::$trashValue ?? null;
  }
  /**
   * Get the restore value from the entity class
   *
   * @return ?string The restore value or null if not set
   */
  public static function getRestoreValue()
  {
    return static::$restoreValue ?? null;
  }

  /**
   * Boot the trait by adding a global scope (named 'excludeTrash')
   * that excludes trashed rows based on your entity's restoreValue.
   */
  public static function bootTrashable(): void
  {
    static::addGlobalScope('excludeTrash', function (QueryEngine $query) {
      // dd([
      //   'msg' => "Running excludeTrash global scope",
      //   'disabled' => $query->getDisabledGlobalScopes(),
      //   'object_hash' => spl_object_hash($query),
      //   'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10),
      // ]);
      $column  = static::getTrashColumn();
      $restore = static::getRestoreValue();

      if (!is_null($restore)) {
        $query->where($column, $restore);
      } else {
        $query->whereNull($column);
      }
    });
  }

  /**
   * Trash this record:
   * • writes static::$trashValue (if set), or
   * • writes a timestamp (Carbon::now()) when static::$trashValue is null.
   */
  public function trash(): self
  {
    $column = static::getTrashColumn();
    $value  = static::getTrashValue() ?? Carbon::now()->toDateTimeString();

    $this->attributes[$column] = $value;
    EntityMapper::persist($this);

    return $this;
  }

  /**
   * Restore this record:
   * writes static::$restoreValue (default null).
   */
  public function restore(): self
  {
    $col = static::getTrashColumn();
    $val = static::getRestoreValue();

    $this->attributes[$col] = $val;
    EntityMapper::persist($this);

    return $this;
  }

  /**
   * Include trashed records in the next query.
   * Call this on a QueryEngine instance.
   */
  public function withTrashed(): self
  {
    // Set the withTrashed flag on the QueryEngine instance
    if ($this instanceof \Pocketframe\PocketORM\QueryEngine\QueryEngine) {
      $this->withTrashed = true;
    }
    return $this;
  }

  /**
   * Restrict the next query to only trashed records.
   * Call this on a QueryEngine instance.
   */
  public function onlyTrashed(): self
  {
    if ($this instanceof \Pocketframe\PocketORM\QueryEngine\QueryEngine) {
      $this->onlyTrashed = true;
    }
    return $this;
  }
}
