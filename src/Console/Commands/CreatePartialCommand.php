#!/usr/bin/env php
<?php

declare(strict_types=1);

namespace Pocketframe\Console\Commands;

use Pocketframe\Contracts\CommandInterface;
use Exception;

class CreatePartialCommand implements CommandInterface
{
  protected array $args;
  protected string $stubPath;

  public function __construct(array $args)
  {
    $this->args = $args;
    // Set the stub path for partials
    $this->stubPath = base_path('vendor/pocketframe/framework/src/stubs/partials/');
  }

  public function handle(): void
  {
    if (empty($this->args[0])) {
      echo "Usage: php pocket partial:create PartialName\n";
      exit(1);
    }
    $partialName = $this->args[0];
    $partialNameTrimmed = trim($partialName, '/');
    $lowerName = strtolower(basename($partialNameTrimmed));

    // Allow subdirectories under resources/views/partials
    $partialDir = base_path("resources/views/partials/" . dirname($partialNameTrimmed) . '/');
    if (!is_dir($partialDir)) {
      mkdir($partialDir, 0777, true);
    }

    // Append .view.php if not present.
    $partialFilePath = $partialDir . $lowerName . ".view.php";
    if (file_exists($partialFilePath)) {
      echo "💡 Partial already exists: {$partialFilePath}\n";
      exit(1);
    }

    $stubFile = $this->stubPath . 'partial.stub';
    if (!file_exists($stubFile)) {
      throw new Exception("Partial stub not found: {$stubFile}");
    }
    $stub = file_get_contents($stubFile);
    $stub = str_replace('{{partialName}}', $partialNameTrimmed, $stub);
    file_put_contents($partialFilePath, $stub);
    echo "💪 Partial created: {$partialFilePath}\n";
  }
}
