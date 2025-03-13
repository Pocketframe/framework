<?php

namespace Pocketframe\Console\Commands;

use Pocketframe\Contracts\CommandInterface;

/**
 * Class CreateViewCommand
 *
 * Generates a new view file.
 *
 * Usage:
 *   php pocket view:create viewName
 *
 * The view file will be created under resources/views, preserving any subdirectories.
 * If a stub file is available at vendor/pocketframe/framework/src/stubs/views/view.stub,
 * its content will be used as the basis for the new view.
 *
 * @package Pocketframe\Console\Commands
 */
class CreateViewCommand implements CommandInterface
{
  /**
   * @var array Command-line arguments.
   */
  protected array $args;

  /**
   * @var string Path to the stubs directory for views.
   */
  protected string $stubPath;

  /**
   * CreateViewCommand constructor.
   *
   * @param array $args Command-line arguments.
   */
  public function __construct(array $args)
  {
    $this->args = $args;
    $this->stubPath = base_path('vendor/pocketframe/framework/src/stubs/views');
  }

  /**
   * Handle the command execution.
   *
   * @return void
   */
  public function handle(): void
  {
    // Ensure a view name is provided.
    $viewInput = $this->args[0] ?? null;
    if (!$viewInput) {
      echo "Usage: php pocket view:create viewName\n";
      exit(1);
    }

    // Determine the path for the view file.
    // This supports subdirectories if viewInput contains slashes.
    $viewPath = base_path("resources/views/{$viewInput}.view.php");

    // Ensure the target directory exists.
    $directory = dirname($viewPath);
    if (!is_dir($directory)) {
      mkdir($directory, 0777, true);
    }

    // Load stub if available, else default content.
    $stubFile = "{$this->stubPath}/view.stub";
    if (file_exists($stubFile)) {
      $template = file_get_contents($stubFile);
      // Optionally replace placeholders in the stub.
      $template = str_replace(
        ['{{viewName}}', '{{layout}}'],
        [$viewInput, $layout ?? ''],
        $template
      );
    }

    // Write the template to the view file.
    file_put_contents($viewPath, $template);
    echo "💪 View created: {$viewPath}\n";
  }
}
