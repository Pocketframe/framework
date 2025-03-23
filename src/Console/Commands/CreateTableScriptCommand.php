<?php

namespace Pocketframe\Console\Commands;

use Pocketframe\Contracts\CommandInterface;
use Pocketframe\Essentials\Utilities\StringUtils;
use Pocketframe\Essentials\Utilities\Utilities;

class CreateTableScriptCommand implements CommandInterface
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

    if (!$schemaName) {
      echo "Usage: php pocket schema:create CreatePostTable\n";
      exit(1);
    }

    // Extract the correct class name (e.g., "ProductCategory")
    $className = $this->extractClassName($schemaName);

    // Generate the table name from the class name (e.g., "product_categories")
    $tableName = $this->extractTableName($className);

    $timestamp = date('Y_m_d_His');
    $fileName = "{$timestamp}_{$tableName}_table.php";
    $targetPath = base_path("database/schemas/{$fileName}");

    if (!is_dir(base_path('database/schemas'))) {
      mkdir(base_path('database/schemas'), 0777, true);
    }

    if (file_exists($targetPath)) {
      echo "Table script {$schemaName} already exists.\n";
      exit(1);
    }

    $stub = file_get_contents("{$this->stubPath}/table.stub");
    $content = str_replace(
      ['{{className}}', '{{tableName}}'],
      [$className, $tableName],
      $stub
    );

    file_put_contents($targetPath, $content);
    echo "💪 Table script created: {$targetPath}\n";
  }

  protected function extractClassName(string $schemaName): string
  {
    $className = preg_replace('/^Create|Table$/', '', $schemaName);
    return StringUtils::plural($className);
  }

  protected function extractTableName(string $tableName): string
  {
    // Remove 'Create' and 'Table' from the schema name
    $tableName = preg_replace('/^Create|Table$/', '', $tableName);

    // Convert PascalCase to snake_case
    $snakeCase = preg_replace('/(?<!^)([A-Z])/', '_$1', $tableName);
    $snakeCase = strtolower($snakeCase);

    // Pluralize the snake_case string
    return StringUtils::plural($snakeCase);
  }
}
