<?php

declare(strict_types=1);

namespace Pocketframe\Console\Commands;

class ServeCommand
{
  public function handle(): void
  {
    $host = '127.0.0.1';
    $port = '8000';
    $root = '.';
    $index = 'index.php';

    echo "Starting server at http://{$host}:{$port}\n";
    echo "Press Ctrl+C to stop the server.\n";

    $command = sprintf('php -S %s:%s -t %s %s', $host, $port, $root, $index);
    passthru($command);
  }
}
