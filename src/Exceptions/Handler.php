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
  protected Logger $logger;
  protected ?string $viewPath;

  public function __construct(Logger $logger)
  {
    $this->logger = $logger;

    $container = Container::getInstance()->get('viewPath');
    if (!$container) {
      throw new \RuntimeException('View path not set in the container.');
    }
  }

  public function handle(Throwable $e): void
  {
    $errorId = uniqid('err_', true);
    $statusCode = $this->normalizeStatusCode((int) $e->getCode());

    // Log with context
    $this->logger->log($e, [
      'id' => $errorId,
      'request' => $this->getRequestContext()
    ]);

    if ($this->expectsJson()) {
      $this->sendJsonResponse($e, $statusCode, $errorId);
    } else {
      $this->sendHtmlResponse($e, $statusCode, $errorId);
    }
  }

  protected function sendHtmlResponse(Throwable $e, int $statusCode, string $errorId): void
  {
    // Clear all output buffers
    while (ob_get_level() > 0) {
      ob_end_clean();
    }

    // Start new output buffer
    ob_start();

    try {
      $file = $this->getErrorView($statusCode);
      $data = $this->getErrorData($e) + ['error_id' => $errorId];
      $content = View::renderFile($file, $data);
    } catch (Throwable $fallbackError) {
      $content = "<h1>{$statusCode} Error</h1><p>{$e->getMessage()}</p><small>ID: {$errorId}</small>";
    }

    // Clean and send
    ob_end_clean();
    (new Response($content, $statusCode, [
      'Content-Type' => 'text/html',
      'Cache-Control' => 'no-store'
    ]))->send();
  }

  protected function sendJsonResponse(Throwable $e, int $statusCode, string $errorId): void
  {
    $response = [
      'error' => true,
      'message' => $e->getMessage(),
      'id' => $errorId,
    ];

    if (env('APP_DEBUG') === 'true') {
      $response += [
        'exception' => get_class($e),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $this->formatStackTrace($e->getTrace()),
      ];
    }

    (new Response(json_encode($response, JSON_PRETTY_PRINT), $statusCode, [
      'Content-Type' => 'application/json'
    ]))->send();
  }

  protected function normalizeStatusCode(int $code): int
  {
    return ($code >= 100 && $code <= 599) ? $code : 500;
  }

  protected function expectsJson(): bool
  {
    return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
  }

  protected function getErrorView(int $statusCode): string
  {
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
      'source_metadata' => $sourceInfo['metadata'],
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
        'request' => $this->getRequestContext(),
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

    if (str_contains($file, '/store/framework/views/')) {
      $mapping = $this->parseCompiledViewMetadata($file);
      if ($mapping) {
        $originalFile = $mapping['source_file'];
        $lineOffset = $mapping['line_offset'];
        $originalLine = max(1, $line - $lineOffset);
        $metadata = [
          'compiled_path' => $file,
          'source_file' => $originalFile,
          'line_offset' => $lineOffset
        ];
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
    foreach (array_slice($lines, 0, 5) as $line) {
      if (preg_match('/\/\*\s?Source:\s?(.+?\.view\.php).*?line_offset:\s*(\d+)/', $line, $matches)) {
        return [
          'source_file' => trim($matches[1]),
          'line_offset' => (int)$matches[2]
        ];
      }
    }
    return null;
  }

  private function getCodeSnippet(string $filePath, int $errorLine, int $originalLine, int $lineOffset): array
  {
    $adjustedLine = max(1, $originalLine - $lineOffset);

    $default = [
      'file' => $filePath,
      'line_start' => 0,
      'line_end' => 0,
      'error_line' => $adjustedLine, // Use adjusted line here
      'original_line' => $originalLine,
      'content' => [],
      'context' => ['is_compiled' => str_contains($filePath, '/store/framework/views/')]
    ];

    if (!file_exists($filePath)) {
      return $default;
    }

    $lines = @file($filePath) ?: [];
    $count = count($lines);

    // Use adjusted line for calculations
    if ($adjustedLine < 1 || $adjustedLine > $count) {
      return $default;
    }

    $index = $adjustedLine - 1;
    $actual = $this->findActualErrorLine($lines, $index);

    $start = max(0, $actual - 2);
    $end = min($count - 1, $actual + 2);

    return [
      'file' => $filePath,
      'line_start' => $start + 1,
      'line_end' => $end + 1,
      'error_line' => $adjustedLine,
      'original_line' => $originalLine,
      'content' => array_slice($lines, $start, $end - $start + 1),
      'context' => ['is_compiled' => str_contains($filePath, '/store/framework/views/')]
    ];
  }

  private function findActualErrorLine(array $lines, int $index): int
  {
    $patterns = [
      '/->\w+\(/',
      '/\b(fn|function)\b/',
      '/\[.*\]\s*=>/',
      '/\b(return|throw|new)\b/'
    ];

    for ($i = $index; $i >= 0; $i--) {
      $line = trim($lines[$i] ?? '');
      if ($line === '') continue;

      foreach ($patterns as $pattern) {
        if (preg_match($pattern, $line)) {
          return $i;
        }
      }
    }

    return $index;
  }

  private function formatStackTrace(array $trace): array
  {
    $limit = (int) env('TRACE_DEPTH', 15);
    return array_map(fn($item) => [
      'file' => $item['file'] ?? null,
      'line' => $item['line'] ?? null,
      'call' => $this->formatCall($item),
      'source' => isset($item['file']) ? $this->getCodeSnippet($item['file'], $item['line'], $item['line'], 0) : null
    ], array_slice($trace, 0, $limit));
  }

  private function formatCall(array $item): string
  {
    $call = $item['class'] ?? '';
    $call .= $item['type'] ?? '';
    $call .= $item['function'] ?? '';
    return $call . '()';
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
