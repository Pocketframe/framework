<?php

namespace Pocketframe\Console\Commands;

use Pocketframe\Contracts\CommandInterface;

class ServeCommand implements CommandInterface
{
  protected array $args;

  public function __construct(array $args)
  {
    $this->args = $args;
  }

  // Helper function to check if a port is available
  protected function isPortAvailable(string $host, int $port): bool
  {
    $connection = @fsockopen($host, $port, $errno, $errstr, 1);
    if (is_resource($connection)) {
      fclose($connection);
      return false;
    }
    return true;
  }

  public function handle(): void
  {
    // Usage: php pocket serve [port] [host]
    $port = 8000;
    $host = $this->args[1] ?? '127.0.0.1';
    $docRoot = 'public';

    while (!$this->isPortAvailable($host, $port)) {
      echo "Port {$port} is in use. Trying port " . ($port + 1) . "...\n";
      $port++;
    }

    echo "Starting server on http://{$host}:{$port}\n";
    passthru("php -S {$host}:{$port} -t {$docRoot} index.php");
  }
}
