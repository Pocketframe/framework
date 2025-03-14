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
    $filename = $this->hashName();
    // Build the storage path.
    $path = rtrim($directory, '/') . '/' . $filename;
    $storage = new Storage($disk);
    $contents = file_get_contents($this->getRealPath());
    if (!$storage->put($path, $contents)) {
      throw new \Exception('Failed to store the uploaded file.');
    }
    return $path;
  }
}
