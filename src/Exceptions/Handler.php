<?php

namespace Pocketframe\Exceptions;

use Pocketframe\Contracts\ExceptionHandlerInterface;
use Pocketframe\Exceptions\Render\ErrorPageRenderer;
use Pocketframe\Http\Response\Response;
use Pocketframe\Logger\Logger;
use Throwable;

class Handler implements ExceptionHandlerInterface
{

    public function handle(Throwable $e): bool
    {
        error_log($e->getMessage());
        if (env('APP_DEBUG') === 'true') {
            $errorDetails = [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'trace'   => $e->getTraceAsString(),
            ];

            try {
                Response::view('errors/500', $errorDetails, 500);
            } catch (Throwable $fallbackError) {
                http_response_code(500);
                $details = null;
                if (config('app.debug')) {
                    $details = <<<DETAILS
<div>Error: {$fallbackError->getMessage()}</div>
<div>File: {$fallbackError->getFile()}:{$fallbackError->getLine()}</div>
<div>Stack Trace: {$fallbackError->getTraceAsString()}</div>
DETAILS;
                }
                ErrorPageRenderer::render('500 Internal Server Error', 'An error occurred while rendering the error page', $details);
                exit;
            }
            exit;
        }

        // In production, show a generic error page
        Response::view('errors/500', ['message' => 'An internal error occurred.'], 500);
        return false;
    }
}
