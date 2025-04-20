<?php

namespace Pocketframe\Console\Commands;

use Pocketframe\Contracts\CommandInterface;

class CreateSessionTableCommand implements CommandInterface
{
  protected string $stubPath;

  public function __construct()
  {
    $this->stubPath = base_path('vendor/pocketframe/framework/src/stubs/schemas');
  }

  public function handle(): void
  {
    $timestamp = date('Y_m_d_His');
    $fileName = "{$timestamp}_sessions_table.php";
    $targetPath = base_path("database/schemas/{$fileName}");

    if (!is_dir(base_path('database/schemas'))) {
      mkdir(base_path('database/schemas'), 0777, true);
    }

    if (file_exists($targetPath)) {
      echo "Table script sessions already exists.\n";
      exit(1);
    }

    $stub = file_get_contents("{$this->stubPath}/session-table.stub");
    $content = str_replace(
      ['{{className}}', '{{tableName}}'],
      ['Sessions', 'sessions'],
      $stub
    );

    file_put_contents($targetPath, $content);
    echo "💪 Table script created: {$targetPath}\n";
  }
}
