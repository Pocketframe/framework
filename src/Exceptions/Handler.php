<?php

namespace Pocketframe\Exceptions;

use Pocketframe\Container\Container;
use Pocketframe\Contracts\ExceptionHandlerInterface;
use Pocketframe\Http\Response\Response;
use Pocketframe\Logger\Logger;
use Pocketframe\TemplateEngine\View;
use Throwable;

class Handler implements ExceptionHandlerInterface
{
  protected $logger;
  protected $viewPath;

  public function __construct(Logger $logger)
  {
    $this->logger = $logger;
    $this->viewPath = Container::getInstance()->get('viewPath');
  }

  public function handle(Throwable $e): bool
  {
    // Log the error
    $this->logger->log($e);

    // Force code=500 if the exception code is 0 or outside the 400–599 range
    $statusCode = (int)$e->getCode();
    if ($statusCode < 100 || $statusCode > 599) {
      $statusCode = 500;
    }

    try {
      $file = $this->getErrorView($statusCode);
      $content = View::renderFile($file, $this->getErrorData($e));
      $response = new Response($content, $statusCode, ['Content-Type' => 'text/html']);
    } catch (Throwable $fallbackError) {
      // Fallback to a simple error message if rendering fails
      $response = new Response(
        "<h1>{$statusCode} Error</h1><p>{$e->getMessage()}</p>",
        $statusCode,
        ['Content-Type' => 'text/html']
      );
    }

    // Send the response
    $response->send();
    return true;
  }

  protected function getErrorView(int $statusCode): string
  {
    // Point to your vendor error file
    return __DIR__ . '/../resources/views/errors/' . $statusCode . '.view.php';
  }


  protected function getErrorData(Throwable $e): array
  {
    $file = $e->getFile();
    $line = $e->getLine();
    $sourceInfo = $this->resolveSourceMapping($file, $line);

    $data = [
      'message' => $e->getMessage(),
      'file' => $sourceInfo['file'],
      'line' => $sourceInfo['line'],
      'source_metadata' => $sourceInfo['metadata']
    ];

    if (env('APP_DEBUG') === 'true') {
      $data['snippet'] = $this->getCodeSnippet(
        $sourceInfo['file'],
        $sourceInfo['line'],
        $sourceInfo['original_line'],
        $sourceInfo['line_offset']
      );

      $data += [
        'exception' => get_class($e),
        'trace' => $this->formatStackTrace($e->getTrace()),
        'request' => $this->getRequestContext()
      ];
    }

    return $data;
  }



  private function resolveSourceMapping(string $file, int $line): array
  {
    $metadata = [];
    $originalFile = $file;
    $originalLine = $line;
    $lineOffset = 0;

    if (strpos($file, '/store/framework/views/') !== false) {
      $sourceMapping = $this->parseCompiledViewMetadata($file);

      if ($sourceMapping) {
        $originalFile = $sourceMapping['source_file'];
        $lineOffset = $sourceMapping['line_offset'];
        $originalLine = max(1, $line - $lineOffset);
        $metadata = ['compiled_path' => $file];
      }
    }

    return [
      'file' => $originalFile,
      'line' => $originalLine,
      'original_line' => $line,
      'line_offset' => $lineOffset,
      'metadata' => $metadata
    ];
  }

  private function parseCompiledViewMetadata(string $compiledPath): ?array
  {
    $lines = @file($compiledPath);
    if (!$lines) return null;

    // Look for source mapping comment in first 5 lines
    foreach (array_slice($lines, 0, 5) as $i => $line) {
      if (preg_match('/\/\*\s?Source:\s?(.+?\.view\.php).*?\*\//', $line, $matches)) {
        $sourceFile = $matches[1];
        if (file_exists($sourceFile)) {
          return [
            'source_file' => $sourceFile,
            'line_offset' => $i + 1  // Lines are 0-indexed in array
          ];
        }
      }
    }
    return null;
  }

  private function getCodeSnippet(string $filePath, int $errorLine): array
  {
    $defaults = [
      'file' => $filePath,
      'line_start' => 0,
      'line_end' => 0,
      'error_line' => $errorLine,
      'original_line' => $errorLine,
      'content' => [],
      'context' => ['is_compiled' => false]
    ];

    if (!file_exists($filePath)) {
      return $defaults;
    }

    try {
      $lines = @file($filePath) ?: [];
      $lineCount = count($lines);

      if ($lineCount === 0 || $errorLine < 1 || $errorLine > $lineCount) {
        return $defaults;
      }

      // Convert to 0-based index
      $errorIndex = $errorLine - 1;

      // Adjust for chained method errors
      $adjustedIndex = $this->findActualErrorLine($lines, $errorIndex);
      $start = max(0, $adjustedIndex - 2);
      $end = min($lineCount - 1, $adjustedIndex + 2);

      return [
        'file' => $filePath,
        'line_start' => $start + 1,
        'line_end' => $end + 1,
        'error_line' => $adjustedIndex + 1,
        'original_line' => $errorLine,
        'content' => array_slice($lines, $start, $end - $start + 1),
        'context' => [
          'is_compiled' => str_contains($filePath, '/store/framework/views/')
        ]
      ];
    } catch (\Throwable $e) {
      return $defaults;
    }
  }

  private function findActualErrorLine(array $lines, int $reportedIndex): int
  {
    $patterns = [
      '/->\w+\(/',       // Method chaining
      '/\b(fn|function)\b/', // Arrow functions
      '/\[.*\]\s*=>/',   // Array mappings
      '/\b(return|throw|new)\b/' // Control keywords
    ];

    for ($i = $reportedIndex; $i >= 0; $i--) {
      $line = trim($lines[$i] ?? '');
      if ($line === '') continue;

      foreach ($patterns as $pattern) {
        if (preg_match($pattern, $line)) {
          return $i;
        }
      }
    }

    return $reportedIndex;
  }


  private function getFileContext(string $filePath): array
  {
    return [
      'last_modified' => date('Y-m-d H:i:s', filemtime($filePath)),
      'size' => filesize($filePath),
      'is_compiled' => strpos($filePath, '/store/framework/views/') !== false
    ];
  }

  private function formatStackTrace(array $trace): array
  {
    return array_map(function ($item) {
      return [
        'file' => $item['file'] ?? null,
        'line' => $item['line'] ?? null,
        'call' => $this->formatCall($item),
        'source' => isset($item['file']) ? $this->getCodeSnippet($item['file'], $item['line']) : null
      ];
    }, $trace);
  }

  private function formatCall(array $item): string
  {
    $call = '';
    if (isset($item['class'])) {
      $call .= $item['class'] . $item['type'];
    }
    $call .= $item['function'] . '()';
    return $call;
  }

  private function getRequestContext(): array
  {
    return [
      'method' => $_SERVER['REQUEST_METHOD'] ?? null,
      'uri' => $_SERVER['REQUEST_URI'] ?? null,
      'referrer' => $_SERVER['HTTP_REFERER'] ?? null,
      'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
    ];
  }
}
