<?php

namespace Pocketframe\Console\Commands;

use Pocketframe\Contracts\CommandInterface;

/**
 * Class AddKeyCommand
 *
 * Generates a new application key and writes it to the .env file.
 *
 * Usage:
 *   php pocket add:key
 *
 * @package Pocketframe\Console\Commands
 */
class AddKeyCommand implements CommandInterface
{
  protected array $args;

  public function __construct(array $args)
  {
    $this->args = $args;
  }

  /**
   * Generate a random key.
   *
   * @param int $length Length of the key.
   * @return string The generated key.
   */
  protected function generateRandomKey(int $length = 32): string
  {
    return base64_encode(random_bytes($length));
  }

  /**
   * Write the generated key to the .env file.
   *
   * @param string $key The new application key.
   * @return bool True on success, false on failure.
   */
  protected function writeKeyToEnv(string $key): bool
  {
    $envPath = BASE_PATH . '.env';
    if (!file_exists($envPath)) {
      echo "Error: .env file not found at {$envPath}\n";
      return false;
    }

    $envContents = file_get_contents($envPath);
    // If an APP_KEY exists, replace it; otherwise, append it.
    if (preg_match('/^APP_KEY=.*$/m', $envContents)) {
      $envContents = preg_replace('/^APP_KEY=.*$/m', 'APP_KEY=' . $key, $envContents);
    } else {
      $envContents .= "\nAPP_KEY=" . $key . "\n";
    }

    return file_put_contents($envPath, $envContents) !== false;
  }

  public function handle(): void
  {
    $key = $this->generateRandomKey();
    if ($this->writeKeyToEnv($key)) {
      echo "💪 New application key generated and added to .env: {$key}\n";
    } else {
      echo "Error: Failed to write the new key to the .env file.\n";
    }
  }
}
