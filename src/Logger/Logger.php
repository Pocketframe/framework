<?php

namespace Pocketframe\Logger;

use Pocketframe\Contracts\LoggerInterface;

class Logger implements LoggerInterface
{
    public function log($message): void
    {
        $date = date('Y-m-d H:i:s');
        $formattedMessage = "[$date] $message" . PHP_EOL;
        file_put_contents(base_path('log/pocketframe.log'), $formattedMessage, FILE_APPEND);
    }
}
