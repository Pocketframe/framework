<?php

namespace Pocketframe\Console\Commands;

use Pocketframe\Contracts\CommandInterface;

/**
 * Class ClearViewsCommand
 *
 * Clears all compiled view files in store/framework/views.
 *
 * Usage:
 *   php pocket clear:views
 *
 * @package Pocketframe\Console\Commands
 */
class ClearViewsCommand implements CommandInterface
{
  protected array $args;

  public function __construct(array $args)
  {
    $this->args = $args;
  }

  /**
   * Recursively deletes files in a directory.
   *
   * @param string $dir Directory path.
   * @return void
   */
  protected function deleteDirectoryContents(string $dir): void
  {
    if (!is_dir($dir)) {
      return;
    }
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
      $filePath = $dir . DIRECTORY_SEPARATOR . $file;
      if (is_dir($filePath)) {
        $this->deleteDirectoryContents($filePath);
        rmdir($filePath);
      } else {
        unlink($filePath);
      }
    }
  }

  public function handle(): void
  {
    $viewsDir = base_path('store/framework/views');
    if (!is_dir($viewsDir)) {
      echo "Views directory not found: {$viewsDir}\n";
      exit(1);
    }

    $this->deleteDirectoryContents($viewsDir);
    echo "💪 Cached views cleared from: {$viewsDir}\n";
  }
}
