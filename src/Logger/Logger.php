<?php

namespace Pocketframe\Logger;

use Exception;
use Pocketframe\Contracts\LoggerInterface;

class Logger implements LoggerInterface
{
  public function log($message): void
  {
    $exception = new Exception($message);
    $date = date('Y-m-d H:i:s');

    $formattedMessage = "[{$date}] [ERROR] {$exception->getMessage()} in {$exception->getFile()}:{$exception->getLine()}\n{$exception->getTraceAsString()}\n";

    file_put_contents(base_path('store/logs/pocketframe.log'), $formattedMessage, FILE_APPEND);
  }
}
