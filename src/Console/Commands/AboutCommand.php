<?php

namespace Pocketframe\Console\Commands;

use Pocketframe\Contracts\CommandInterface;

class AboutCommand implements CommandInterface
{
  public function __construct(array $args)
  {
    // No arguments required for this command.
  }

  /**
   * Formats a line with dots between the label and value.
   *
   * @param string $label The label (e.g., "DB Driver:")
   * @param string $value The value (e.g., "mysql")
   * @param int $totalWidth The total width of the output line
   * @return string The formatted line
   */
  private function formatLine(string $label, string $value, int $totalWidth = 70): string
  {
    // Remove ANSI codes for proper length calculation
    $cleanLabel = preg_replace('/\033\[[0-9;]*m/', '', $label);
    $cleanValue = preg_replace('/\033\[[0-9;]*m/', '', $value);

    $dotsCount = $totalWidth - strlen($cleanLabel) - strlen($cleanValue);
    if ($dotsCount < 0) {
      $dotsCount = 2;
    }
    return $label . str_repeat(".", $dotsCount) . " " . $value;
  }

  public function handle(): void
  {
    // Attempt to get the version:
    $version = getenv('POCKETFRAME_VERSION')
      ?: trim(shell_exec('git describe --tags --abbrev=0 2>/dev/null'))
      ?: (file_exists(BASE_PATH . '/VERSION') ? trim(file_get_contents(BASE_PATH . '/VERSION')) : 'Unknown');

    $frameworkName = "Pocketframe";
    $description = "{$frameworkName} is a lightweight yet powerful PHP framework designed for building modern web applications with speed and flexibility. With its intuitive structure and modular approach, {$frameworkName} empowers developers to create scalable and high-performance applications effortlessly.";
    $documentationUrl = "https://pocketframe.github.io/docs/";
    $phpVersion = PHP_VERSION;
    $os = php_uname('s') . " " . php_uname('r');

    // ANSI color codes for styling the output.
    $blue   = "\033[34m";
    $yellow = "\033[33m";
    $green  = "\033[32m";
    $reset  = "\033[0m";

    // Retrieve application configuration.
    // Assuming a config file (e.g., config/app.php) returns an array.
    $appConfig = config('app', []);
    $appName   = $appConfig['app_name'] ?? getenv('APP_NAME') ?? 'Pocketframe';
    $env       = $appConfig['env'] ?? getenv('APP_ENV') ?? 'production';
    $debug     = $appConfig['debug'] ?? getenv('APP_DEBUG') ?? false;
    // Timezone is set via date_default_timezone_set, retrieve current timezone:
    $timezone  = date_default_timezone_get();

    // Retrieve database configuration.
    // Assuming a config file (e.g., config/database.php) returns an array.
    $dbConfig = config('database', []);

    $dbDriver   = $dbConfig['database']['driver'] ?? getenv('DB_CONNECTION');
    $dbHost     = $dbConfig['database']['host'] ?? getenv('DB_HOST');
    $dbPort     = $dbConfig['database']['port'] ?? getenv('DB_PORT');
    $dbDatabase = $dbConfig['database']['dbname'] ?? getenv('DB_DATABASE');
    $dbUsername = $dbConfig['database']['username'] ?? getenv('DB_USERNAME');
    $dbEngine   = $dbConfig['database']['engine'] ?? getenv('DB_ENGINE');

    // Display framework information.
    echo "\n\n\n";
    echo "{$yellow}About {$frameworkName}:{$reset}\n";
    echo "\n";
    echo "{$blue}Version:{$reset} {$version}\n";
    echo "\n";
    echo "{$blue}Description:{$reset}\n\n";
    echo "{$description}\n";
    echo "\n";
    echo "{$blue}Documentation:{$reset} {$documentationUrl}\n";
    echo "\n";
    echo "{$blue}PHP Version:{$reset} {$phpVersion}\n";
    echo "{$blue}Operating System:{$reset} {$os}\n\n";

    // Display application information.
    echo "{$yellow}Application Information:{$reset}\n";
    echo $this->formatLine("App Name:", "{$appName}", 100) . "\n";
    echo $this->formatLine("Environment:", "{$env}", 100) . "\n";
    echo $this->formatLine("Debug Mode:", ($debug ? "Enabled" : "Disabled"), 100) . "\n";
    echo $this->formatLine("Timezone:", "{$timezone}", 100) . "\n\n";

    // Display database configuration.
    echo "{$yellow}Database Configuration:{$reset}\n";
    echo $this->formatLine("DB Driver:", "{$dbDriver}", 100) . "\n";
    echo $this->formatLine("DB Host:", "{$dbHost}", 100) . "\n";
    echo $this->formatLine("DB Port:", "{$dbPort}", 100) . "\n";
    echo $this->formatLine("DB Database:", "{$dbDatabase}", 100) . "\n";
    echo $this->formatLine("DB Username:", "{$dbUsername}", 100) . "\n";
    echo $this->formatLine("DB Engine:", "{$dbEngine}", 100) . "\n";
    echo "\n\n";
  }
}
