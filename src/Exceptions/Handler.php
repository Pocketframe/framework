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
        $statusCode = $e->getCode();
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
        $data = ['message' => $e->getMessage()];

        if (env('APP_DEBUG')) {
            $data += [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ];
        }

        if ($e instanceof ValidationException) {
            $data['errors'] = $e->getErrors();
        }

        return $data;
    }
}
