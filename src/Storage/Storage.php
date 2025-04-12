<?php

declare(strict_types=1);

namespace Pocketframe\Storage;

use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;

class Storage
{

  protected Filesystem $filesystem;

  protected array $config;

  protected string $disk;

  /**
   * Create a new Storage instance.
   *
   * @param string|null $disk The disk name (defaults to the configured default disk)
   * @throws \Exception
   */
  public function __construct(?string $disk = null)
  {
    $this->disk = $disk ?? config('filesystems.default', 'local');
    $disks = config('filesystems.disks', []);
    if (!isset($disks[$this->disk])) {
      throw new \Exception("Disk [{$this->disk}] is not configured.");
    }
    $this->config = $disks[$this->disk];
    $adapter = new LocalFilesystemAdapter($this->config['root']);
    $this->filesystem = new Filesystem($adapter);
  }

  /**
   * Store file contents at the given path.
   *
   * @param string $path
   * @param string $contents
   * @return bool
   */
  public function put(string $path, string $contents): bool
  {
    try {
      $this->filesystem->write($path, $contents);
      return true;
    } catch (\Exception $e) {
      return false;
    }
  }

  /**
   * Get file contents from the given path.
   *
   * @param string $path
   * @return string|null
   */
  public function get(string $path): ?string
  {
    try {
      return $this->filesystem->read($path);
    } catch (\Exception $e) {
      return null;
    }
  }

  /**
   * Delete a file at the given path.
   *
   * @param string $path
   * @return bool
   */
  public function delete(string $path): bool
  {
    try {
      $this->filesystem->delete($path);
      return true;
    } catch (\Exception $e) {
      return false;
    }
  }

  /**
   * Check if a file exists at the given path.
   *
   * @param string $path
   * @return bool
   */
  public function exists(string $path): bool
  {
    return $this->filesystem->fileExists($path);
  }

  /**
   * Retrieve a public URL for a given path on the storage disk.
   *
   * @param string $path
   * @return string|null
   */
  public function url(string $path): ?string
  {
    if (isset($this->config['url'])) {
      return rtrim($this->config['url'], '/') . '/' . ltrim($path, '/');
    }
    return null;
  }

  /**
   * Create a symbolic link for the public disk.
   *
   * This method creates a symlink from your public directory to the configured public disk root.
   */
  public static function linkPublic(): void
  {
    // Get filesystem settings
    $disks = config('filesystems.disks', []);
    if (!isset($disks['public'])) {
      throw new \Exception("Public disk not configured.");
    }

    $publicDiskConfig = $disks['public'];
    $target = $publicDiskConfig['root'];
    $link = base_path('public/store');

    // Ensure the target directory exists
    if (!is_dir($target)) {
      echo "Target directory does not exist: $target\n";
      mkdir($target, 0755, true);
    }

    // Ensure the parent directory of the symlink exists
    $linkParentDir = dirname($link);
    if (!is_dir($linkParentDir)) {
      echo "Creating missing parent directory: $linkParentDir\n";
      mkdir($linkParentDir, 0755, true);
    }

    // Create symlink (or junction) if it doesn't exist
    if (!file_exists($link)) {
      // Check if running on Windows
      if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        // On Windows, create a junction using the mklink command
        exec("mklink /J \"{$link}\" \"{$target}\"", $output, $returnVar);
        if ($returnVar === 0) {
          echo "Public storage linked successfully.\n";
        } else {
          echo "Failed to create public storage link.\n";
        }
      } else {
        if (symlink($target, $link)) {
          echo "Public storage linked successfully.\n";
        } else {
          echo "Failed to create public storage link.\n";
        }
      }
    } else {
      echo "Public storage link already exists.\n";
    }
  }
}
