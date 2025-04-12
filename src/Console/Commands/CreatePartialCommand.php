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
    $this->stubPath = base_path('vendor/pocketframe/framework/src/stubs/partials/');
  }

  public function handle(): void
  {
    if (empty($this->args[0])) {
      echo "Usage: php pocket partial:create PartialName\n";
      exit(1);
    }

    // Normalize directory separators
    $partialName = str_replace('\\', '/', $this->args[0]);
    $partialNameTrimmed = trim($partialName, '/');
    $baseName = basename($partialNameTrimmed);
    $lowerName = strtolower($baseName);

    // Windows-compatible path construction
    $partialDir = base_path('resources/views/partials/' . dirname($partialNameTrimmed) . DIRECTORY_SEPARATOR);
    $this->createDirectory($partialDir);

    $filePath = $partialDir . $lowerName . '.view.php';
    $this->createPartialFile($filePath, $partialNameTrimmed);
  }

  protected function createDirectory(string $path): void
  {
    if (!is_dir($path) && !mkdir($path, 0755, true) && !is_dir($path)) {
      throw new Exception("Failed to create directory: {$path}");
    }
  }

  protected function createPartialFile(string $path, string $name): void
  {
    $stubFile = $this->stubPath . 'partial.stub';
    $this->validateStub($stubFile);

    $content = str_replace(
      '{{partialName}}',
      $name,
      file_get_contents($stubFile)
    );

    $this->writeFile($path, $content, 'Partial');
  }

  protected function validateStub(string $path): void
  {
    if (!file_exists($path)) {
      throw new Exception("Stub file not found: {$path}");
    }
  }

  protected function writeFile(string $path, string $content, string $type): void
  {
    if (file_exists($path)) {
      echo "💡 {$type} already exists: {$path}\n";
      exit(1);
    }

    if (file_put_contents($path, $content) === false) {
      throw new Exception("Failed to write {$type} file: {$path}");
    }

    echo "💪 {$type} created: {$path}\n";
  }
}
