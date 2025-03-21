<?php

namespace Pocketframe\Console\Commands;

use Pocketframe\Contracts\CommandInterface;

class CreateSchemaScriptCommand implements CommandInterface
{
  protected array $args;
  protected string $stubPath;

  public function __construct(array $args)
  {
    $this->args = $args;
    $this->stubPath = base_path('vendor/pocketframe/framework/src/stubs/schemas');
  }

  public function handle(): void
  {
    $schemaName = $this->args[0] ?? null;
    $tableName = $this->args[1] ?? null;

    if (!$schemaName || !$tableName) {
      echo "Usage: php pocket schema:create CreatePostsTable posts\n";
      exit(1);
    }

    $timestamp = date('Y_m_d_His');
    $fileName = "{$timestamp}_{$schemaName}.php";
    $targetPath = base_path("database/schemas/{$fileName}");

    $stub = file_get_contents("{$this->stubPath}/schema.stub");
    $content = str_replace(
      ['{{className}}', '{{tableName}}'],
      [$schemaName, $tableName],
      $stub
    );

    file_put_contents($targetPath, $content);
    echo "Schema created: {$targetPath}\n";
  }
}
