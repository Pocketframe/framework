<?php

declare(strict_types=1);

namespace Pocketframe\Console\Commands;

use Pocketframe\Contracts\CommandInterface;
use Exception;

class CreateComponentCommand implements CommandInterface
{
  /**
   * @var array Command-line arguments passed to the command.
   */
  protected array $args;

  /**
   * @var string Path to the stubs directory for components.
   */
  protected string $stubPath;

  public function __construct(array $args)
  {
    $this->args = $args;
    // Set the stub path (adjust if needed)
    $this->stubPath = base_path('vendor/pocketframe/framework/src/stubs/components/');
  }

  public function handle(): void
  {
    if (empty($this->args[0])) {
      echo "Usage: php pocket component:create ComponentName [--inline]\n";
      exit(1);
    }

    $componentInput = $this->args[0];
    $parts = explode('/', trim($componentInput, '/'));
    $componentName = ucfirst(array_pop($parts));
    $subDirectory = implode('/', $parts);
    // Convert component name to kebab-case for view files
    $lowerName = strtolower(preg_replace('/(?<!^)([A-Z])/', '-$1', $componentName));

    $componentClassDir = base_path("app/View/Components/" . ($subDirectory ? $subDirectory . '/' : ''));
    $componentViewDir = base_path("resources/views/components/");

    if (!is_dir($componentClassDir)) {
      mkdir($componentClassDir, 0777, true);
    }
    if (!is_dir($componentViewDir)) {
      mkdir($componentViewDir, 0777, true);
    }

    $isInline = in_array('--inline', $this->args, true);

    // Only create the class if not inline
    if (!$isInline) {
      $classStubFile = $this->stubPath . 'component-class/component.class.stub';
      if (!file_exists($classStubFile)) {
        throw new Exception("Component class stub not found: {$classStubFile}");
      }
      $classStub = file_get_contents($classStubFile);
      $classContent = str_replace(
        ['{{namespace}}', '{{componentName}}', '{{viewName}}'],
        [
          'App\\View\\Components' . ($subDirectory ? '\\' . str_replace('/', '\\', $subDirectory) : ''),
          $componentName,
          $lowerName
        ],
        $classStub
      );
      $classFilePath = $componentClassDir . $componentName . ".php";
      if (file_exists($classFilePath)) {
        echo "💡 Component class already exists: {$classFilePath}\n";
        exit(1);
      }
      file_put_contents($classFilePath, $classContent);
      echo "💪 Component class created at: {$classFilePath}\n";
    }

    if ($isInline) {
      $inlineStubFile = $this->stubPath . 'component-view/inline.component.view.stub';
      if (!file_exists($inlineStubFile)) {
        throw new Exception("Inline component view stub not found: {$inlineStubFile}");
      }
      $inlineStub = file_get_contents($inlineStubFile);
      $inlineContent = str_replace(
        ['{{componentName}}', '{{slot}}'],
        [$componentName, '{{ $slot ?? "Default inline content" }}'],
        $inlineStub
      );
      $inlineFilePath = $componentViewDir . $lowerName . ".inline.view.php";
      if (file_exists($inlineFilePath)) {
        echo "💡 Inline component view already exists: {$inlineFilePath}\n";
      } else {
        file_put_contents($inlineFilePath, $inlineContent);
        echo "💪 Inline component view created at: {$inlineFilePath}\n";
      }
    } else {
      $viewStubFile = $this->stubPath . 'component-view/component.view.stub';
      if (!file_exists($viewStubFile)) {
        throw new Exception("Component view stub not found: {$viewStubFile}");
      }
      $viewStub = file_get_contents($viewStubFile);
      $viewContent = str_replace(
        ['{{componentName}}', '{{slot}}'],
        [$componentName, '{{ $slot ?? "Default component content" }}'],
        $viewStub
      );
      $viewFilePath = $componentViewDir . $lowerName . ".view.php";
      if (file_exists($viewFilePath)) {
        echo "💡 Component view already exists: {$viewFilePath}\n";
        exit(1);
      } else {
        file_put_contents($viewFilePath, $viewContent);
        echo "💪 Component view created at: {$viewFilePath}\n";
      }
    }
  }
}
