<?php

namespace Pocketframe\Console\Commands;

use Pocketframe\Contracts\CommandInterface;
use Pocketframe\Essentials\Utilities\StringUtils;

class CreateEntityCommand implements CommandInterface
{
  protected array $args;
  protected string $stubPath;
  protected string $entityName;
  protected bool $withSchemaScript;
  protected bool $withBlueprint;

  public function __construct(array $args)
  {
    $this->args = $args;
    $this->stubPath = base_path('vendor/pocketframe/framework/src/stubs');
  }

  public function handle(): void
  {
    if (empty($this->args)) {
      $this->showUsage();
      exit(1);
    }

    $this->parseOptions();

    if (!$this->entityName) {
      $this->showUsage();
      exit(1);
    }

    $this->createEntity();

    if ($this->withSchemaScript) $this->createTableScript();
    if ($this->withBlueprint) $this->createBlueprint();
  }

  protected function createEntity(): void
  {
    $targetDir = base_path("app/Entities");
    $targetPath = $targetDir . "/" . ucfirst($this->entityName) . ".php";

    $tableName = $this->withSchemaScript ? $this->extractTableName($this->entityName) : null;

    if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

    $stub = file_get_contents("{$this->stubPath}/entities/entity.stub");
    $content = str_replace(
      ['{{namespace}}', '{{entityName}}', '{{tableName}}'],
      ['App\\Entities', ucfirst($this->entityName), $tableName],
      $stub
    );

    if (file_exists($targetPath)) {
      echo "Entity " . ucfirst($this->entityName) . " already exists.\n";
      exit(1);
    }

    file_put_contents($targetPath, $content);
    echo "💪 Entity created: {$targetPath}\n";
  }

  protected function createTableScript(): void
  {
    $tableName  = $this->extractTableName($this->entityName);
    $timestamp  = date('Y_m_d_His');

    $targetDir  = base_path('database/schemas');
    $fileName   = "{$timestamp}_{$tableName}_table.php";
    $targetPath = "{$targetDir}/{$fileName}";

    if (!is_dir($targetDir)) {
      mkdir($targetDir, 0777, true);
    }

    if (file_exists($targetPath)) {
      echo "Schema script {$this->entityName} already exists.\n";
      exit(1);
    }

    $stub = file_get_contents("{$this->stubPath}/schemas/table.stub");

    $className = $this->extractClassName($this->entityName);

    $content = str_replace(
      ['{{className}}', '{{tableName}}'],
      [$className, $tableName],
      $stub
    );

    file_put_contents($targetPath, $content);
    echo "💪 Schema script created: {$targetPath}\n";
  }

  protected function createBlueprint(): void
  {
    $targetDir = base_path("database/blueprints");
    $blueprintName = ucfirst($this->entityName) . "Blueprint";
    $targetPath = "{$targetDir}/{$blueprintName}.php";

    if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

    if (file_exists($targetPath)) {
      echo "Blueprint {$blueprintName} already exists.\n";
      exit(1);
    }

    $stub = file_get_contents("{$this->stubPath}/blueprints/blueprint.stub");
    $content = str_replace(
      ['{{entityName}}', '{{blueprintName}}'],
      [ucfirst($this->entityName), $blueprintName],
      $stub
    );

    file_put_contents($targetPath, $content);
    echo "💪 Blueprint created: {$targetPath}\n";
  }

  protected function parseOptions(): void
  {
    $this->withSchemaScript = in_array('-s', $this->args) || in_array('--schema', $this->args);
    $this->withBlueprint    = in_array('-b', $this->args) || in_array('--blueprint', $this->args);

    $this->args = array_filter($this->args, fn($arg) => !str_starts_with($arg, '-'));
    $this->entityName = $this->args[0] ?? null;
  }


  protected function showUsage(): void
  {
    echo "Usage: php pocket entity:create EntityName [-s] [-b]\n";
    echo "Options:\n";
    echo "  -s, --schema  Create a table script\n";
    echo "  -b, --blueprint  Create an entity blueprint\n";
  }

  protected function extractClassName(string $schemaName): string
  {
    return StringUtils::plural($this->entityName);
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
