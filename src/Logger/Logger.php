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

    $formattedMessage = "[{$date}] [ERROR] : {$exception->getMessage()} in {$exception->getFile()}:{$exception->getLine()}\n{$exception->getTraceAsString()}\n";

    if (!is_dir('store/logs')) {
      mkdir('store/logs', 0777, true);
    }

    $logFilePath = 'store/logs/pocketframe.log';
    if (!file_exists($logFilePath)) {
      file_put_contents(base_path($logFilePath), $formattedMessage, FILE_APPEND);
    }
  }
}
