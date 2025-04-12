<?php

namespace Pocketframe\Console\Commands;

use Pocketframe\Contracts\CommandInterface;

class CreateBlueprintCommand implements CommandInterface
{
  protected array $args;
  protected string $stubPath;

  public function __construct(array $args)
  {
    $this->args = $args;
    $this->stubPath = base_path('vendor/pocketframe/framework/src/stubs/blueprints');
  }

  public function handle(): void
  {
    $entityName = $this->args[0] ?? null;
    if (!$entityName) {
      echo "Usage: php pocket blueprint:create EntityName\n";
      exit(1);
    }

    $entityFile = base_path("app/Entities/{$entityName}.php");
    if (!file_exists($entityFile)) {
      echo "Entity {$entityName} does not exist. Would you like to create it first? (y/n)\n";
      $answer = trim(strtolower(readline()));
      if ($answer == 'y') {
        passthru("php pocket entity:create {$entityName}");
      } else {
        $answer = trim(strtolower(readline('Continue creating a blueprint? (y/n): ')));
        if ($answer == 'n') {
          exit(1);
        }
      }
    }


    $targetDir = base_path("database/blueprints");
    $blueprintName = ucfirst($entityName) . "Blueprint";
    $targetPath = "{$targetDir}/{$blueprintName}.php";

    if (file_exists($targetPath)) {
      echo "Blueprint already exists: {$targetPath}\n";
      exit(1);
    }

    if (!is_dir($targetDir)) {
      mkdir($targetDir, 0777, true);
    }

    $stub = file_get_contents("{$this->stubPath}/blueprint.stub");
    $content = str_replace(
      ['{{entityName}}', '{{blueprintName}}'],
      [ucfirst($entityName), $blueprintName],
      $stub
    );

    file_put_contents($targetPath, $content);
    echo "💪 Blueprint created: {$targetPath}\n";
  }
}
