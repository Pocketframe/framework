<?php

namespace Pocketframe\PocketORM\Database;

class DataSafe
{
  public static function guard(callable $callback): mixed
  {
    Connection::beginTransaction();

    try {
      $result = $callback();
      Connection::commit();
      return $result;
    } catch (\Throwable $e) {
      Connection::rollBack();
      throw $e;
    }
  }
}
