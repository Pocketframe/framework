<?php

namespace Pocketframe\Console\Commands;

use Pocketframe\Contracts\CommandInterface;

class CreateDataPlanterCommand implements CommandInterface
{
  protected array $args;
  protected string $stubPath;

  public function __construct(array $args)
  {
    $this->args = $args;
    $this->stubPath = base_path('vendor/pocketframe/framework/src/stubs/planters');
  }

  public function handle(): void
  {
    $planterName = $this->args[0] ?? null;
    if (!$planterName) {
      echo "Usage: php pocket planter:create UserPlanter\n";
      exit(1);
    }

    $targetPath = base_path("database/planters/{$planterName}.php");

    if (!is_dir(base_path("database/planters"))) mkdir(base_path("database/planters"), 0777, true);

    $stub = file_get_contents("{$this->stubPath}/planter.stub");
    $content = str_replace(
      ['{{planterName}}'],
      [$planterName],
      $stub
    );

    file_put_contents($targetPath, $content);
    echo "Data planter created: {$targetPath}\n";
  }
}
