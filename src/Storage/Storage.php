<?php

declare(strict_types=1);

namespace Pocketframe\Storage;

use League\Flysystem\DirectoryListing;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;

class Storage
{

  /**
   * @var Filesystem The filesystem adapter for the storage
   */
  protected Filesystem $filesystem;

  /**
   * @var array The configuration for the storage
   */
  protected array $config;

  /**
   * @var string The disk name for the storage
   */
  protected string $disk;

  /**
   * @var bool Whether to throw an exception on error
   */
  protected bool $throwOnError = false;

  /**
   * Create a new Storage instance.
   *
   * @param string|null $disk The disk name (defaults to the configured default disk)
   * @throws \Exception
   */
  public function __construct(?string $disk = null, ?Filesystem $filesystem = null)
  {
    $this->disk = $disk ?? config('filesystems.default', 'local');
    $disks = config('filesystems.disks', []);
    if (!isset($disks[$this->disk])) {
      throw new \Exception("Disk [{$this->disk}] is not configured.");
    }
    $this->config = $disks[$this->disk];
    $adapter = new LocalFilesystemAdapter($this->config['root']);
    $this->filesystem = $filesystem ?? new Filesystem($adapter);
  }

  /**
   * Set whether to throw an exception on error
   *
   * @param bool $value Whether to throw an exception on error
   * @return static
   */
  public function throwExceptions(bool $value = true): static
  {
    $this->throwOnError = $value;
    return $this;
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
      if ($this->throwOnError) throw $e;
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
      if ($this->throwOnError) throw $e;
      return null;
    }
  }

  /**
   * Get the full file system path for the given relative path.
   *
   * This method concatenates the disk root from the configuration with the
   * provided relative path.
   *
   * @param string $relativePath The path relative to the disk root.
   * @return string The fully resolved file system path.
   */
  public function path(string $relativePath): string
  {
    // Ensure the root directory ends with a directory separator
    $root = rtrim($this->config['root'] ?? '', DIRECTORY_SEPARATOR);
    // Ensure the relative path does not start with a directory separator
    // to avoid double separators in the final path
    // and concatenate the root with the relative path
    // to form the full path.
    // This is important for Windows compatibility.
    // The ltrim function removes any leading directory separators from the relative path.
    $relativePath = ltrim($relativePath, DIRECTORY_SEPARATOR);
    // Concatenate the root and relative path
    // using the directory separator for the current operating system.
    return $root . DIRECTORY_SEPARATOR . $relativePath;
  }

  /**
   * Copy a file from one path to another.
   *
   * @param string $from The source path
   * @param string $to The destination path
   * @return bool
   */
  public function copy(string $from, string $to): bool
  {
    try {
      $this->filesystem->copy($from, $to);
      return true;
    } catch (\Exception $e) {
      if ($this->throwOnError) throw $e;
      return false;
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
      if ($this->throwOnError) throw $e;
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
      return rtrim(config('app.url'), '/') . '/store/app/' . ltrim($path, '/');
    }
    return null;
  }

  /**
   * Get a new Storage instance for a different disk.
   *
   * @param string $disk The name of the disk to use
   * @return static A new Storage instance
   */
  public function disk(string $disk): static
  {
    return new self($disk);
  }

  /**
   * Switch the storage disk.
   *
   * @param string $disk The name of the disk to use
   * @return void
   */
  public function switchDisk(string $disk): void
  {
    $this->__construct($disk);
  }


  /**
   * Create a directory at the given path.
   *
   * @param string $path
   * @return bool
   */
  public function makeDirectory(string $path): bool
  {
    try {
      $this->filesystem->createDirectory($path);
      return true;
    } catch (\Exception $e) {
      if ($this->throwOnError) throw $e;
      return false;
    }
  }

  /**
   * Delete a directory at the given path.
   *
   * @param string $path
   * @return bool
   */
  public function deleteDirectory(string $path): bool
  {
    try {
      $this->filesystem->deleteDirectory($path);
      return true;
    } catch (\Exception $e) {
      if ($this->throwOnError) throw $e;
      return false;
    }
  }

  /**
   * List the contents of a directory.
   *
   * @param string $path
   * @param bool $deep
   * @return DirectoryListing
   */
  public function listContents(string $path, bool $deep = false): DirectoryListing
  {
    return $this->filesystem->listContents($path, $deep);
  }


  /**
   * List all directories.
   *
   * @return array
   */
  public function directories(): array
  {
    return $this->filesystem->listContents('', true)
      ->filter(fn($item) => $item['type'] === 'dir')
      ->map(fn($item) => $item['path'])
      ->toArray();
  }


  /**
   * List all files.
   *
   * @return array
   */
  public function files(): array
  {
    return $this->filesystem->listContents('', true)->filter(fn($item) => $item['type'] === 'file')->map(fn($item) => $item['path'])->toArray();
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
    $link = base_path(config('filesystems.public_link', 'public/store'));

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
