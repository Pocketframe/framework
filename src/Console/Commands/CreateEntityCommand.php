<?php

namespace Pocketframe\Console\Commands;

use Pocketframe\Contracts\CommandInterface;

class CreateEntityCommand implements CommandInterface
{
  protected array $args;
  protected string $stubPath;

  public function __construct(array $args)
  {
    $this->args = $args;
    $this->stubPath = base_path('vendor/pocketframe/framework/src/stubs/entities');
  }

  public function handle(): void
  {
    $entityName = $this->args[0] ?? null;
    if (!$entityName) {
      echo "Usage: php pocket entity:create EntityName\n";
      exit(1);
    }

    $targetDir = base_path("app/Entities");
    $targetPath = $targetDir . "/{$entityName}.php";

    if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

    $stub = file_get_contents("{$this->stubPath}/entity.stub");
    $content = str_replace(
      ['{{namespace}}', '{{entityName}}'],
      ['App\\Entities', $entityName],
      $stub
    );

    file_put_contents($targetPath, $content);
    echo "Entity created: {$targetPath}\n";
  }
}
