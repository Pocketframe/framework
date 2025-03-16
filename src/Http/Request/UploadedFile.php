<?php

declare(strict_types=1);

namespace Pocketframe\Http\Request;

use Pocketframe\Storage\Storage;

class UploadedFile
{
  protected array $file;

  public function __construct(array $file)
  {
    $this->file = $file;
  }

  /**
   * Get the original filename of the uploaded file.
   *
   * @return string The original name of the uploaded file, or an empty string if not available.
   */
  public function getClientOriginalName(): string
  {
    return $this->file['name'] ?? '';
  }

  /**
   * Get the temporary path of the uploaded file.
   *
   * @return string The temporary file path of the uploaded file, or an empty string if not available.
   */
  public function getRealPath(): string
  {
    return $this->file['tmp_name'] ?? '';
  }

  /**
   * Get the MIME type of the uploaded file.
   *
   * @return string The MIME type of the uploaded file, or an empty string if not available.
   */
  public function getClientMimeType(): string
  {
    return $this->file['type'] ?? '';
  }

  /**
   * Get the file extension of the original uploaded file.
   *
   * @return string The file extension of the uploaded file, or an empty string if no extension exists.
   */
  public function getClientOriginalExtension(): string
  {
    return pathinfo($this->getClientOriginalName(), PATHINFO_EXTENSION);
  }

  /**
   * Get the size of the uploaded file in bytes.
   *
   * @return int The size of the uploaded file, or 0 if no size is available.
   */
  public function getSize(): int
  {
    return (int)($this->file['size'] ?? 0);
  }

  /**
   * Generate a unique filename for the uploaded file.
   *
   * @return string A unique filename incorporating timestamp, hashed original filename, and original extension.
   */
  public function hashName(): string
  {
    $extension = $this->getClientOriginalExtension();
    return time() . '_' . sha1($this->getClientOriginalName() . microtime(true)) . ($extension ? '.' . $extension : '');
  }

  /**
   * Store the uploaded file on a specified disk and directory.
   *
   * @param string $directory The target directory (e.g. 'uploads')
   * @param string $disk The disk name (e.g. 'public')
   * @return string The file path where the file was stored.
   */
  public function store(string $directory, string $disk = 'local'): string
  {
    // Validate directory
    if (empty($directory)) {
      throw new \InvalidArgumentException('Storage directory cannot be empty.');
    }

    // Generate filename
    $filename = $this->hashName();
    if (empty($filename)) {
      throw new \RuntimeException('Failed to generate valid filename.');
    }

    // Build the storage path.
    $path = rtrim($directory, '/') . '/' . $filename;

    // Validate uploaded file
    $tempPath = $this->getRealPath();
    if (empty($tempPath) || !is_uploaded_file($tempPath)) {
      throw new \RuntimeException('Invalid file upload.');
    }

    // Read file contents
    $contents = file_get_contents($tempPath);
    if ($contents === false) {
      throw new \RuntimeException('Could not read file contents.');
    }

    $storage = new Storage($disk);

    // Store file
    $storage = new Storage($disk);
    if (!$storage->put($path, $contents)) {
      throw new \RuntimeException("Failed to store file at: $path");
    }

    return $path;
  }
}
