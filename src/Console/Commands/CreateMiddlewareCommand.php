<?php

namespace Pocketframe\Console\Commands;

use Pocketframe\Contracts\CommandInterface;

class CreateMiddlewareCommand implements CommandInterface
{
  protected array $args;
  protected string $stubPath;

  public function __construct(array $args)
  {
    $this->args = $args;
    $this->stubPath = base_path('vendor/pocketframe/framework/src/stubs/middlewares');
  }

  public function handle(): void
  {
    $middlewareName = $this->args[0] ?? null;
    if (!$middlewareName) {
      echo "Usage: php pocket middleware:create MiddlewareName\n";
      exit(1);
    }

    // Validate middleware name (must be a valid class name)
    if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $middlewareName)) {
      echo "Error: Invalid middleware name '{$middlewareName}'.\n";
      exit(1);
    }

    $targetDir = base_path("app/Middleware");
    $targetPath = $targetDir . "/{$middlewareName}.php";

    if (file_exists($targetPath)) {
      echo "🥳 Middleware already exists: {$targetPath}\n";
      exit(1);
    }

    if (!is_dir($targetDir)) {
      mkdir($targetDir, 0777, true);
    }

    $stubFile = "{$this->stubPath}/middleware.stub";
    if (!file_exists($stubFile)) {
      echo "🤔 Stub file not found: {$stubFile}\n";
      exit(1);
    }

    $template = file_get_contents($stubFile);
    $template = str_replace(
      ['{{namespace}}', '{{middlewareName}}'],
      ['App\\Middleware', $middlewareName],
      $template
    );

    file_put_contents($targetPath, $template);
    echo "💪 Middleware created: {$targetPath}\n";
  }
}
