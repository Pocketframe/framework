<?php

namespace Pocketframe\Exceptions;

use Pocketframe\Contracts\ExceptionHandlerInterface;
use Pocketframe\Exceptions\Render\ErrorPageRenderer;
use Pocketframe\Http\Response\Response;
use Throwable;

class Handler implements ExceptionHandlerInterface
{
    public function handle(Throwable $e): bool
    {
        error_log($e->getMessage());

        try {
            Response::view('errors/500', [
                'error' => $e
            ], 500);
        } catch (Throwable $fallbackError) {
            http_response_code(500);
            $details = null;
            if ('debug') {
                $details = <<<DETAILS
<div>Error: {$fallbackError->getMessage()}</div>
<div>File: {$fallbackError->getFile()}:{$fallbackError->getLine()}</div>
<div>Stack Trace: {$fallbackError->getTraceAsString()}</div>
DETAILS;
            }
            ErrorPageRenderer::render('500 Internal Server Error', 'An error occurred while rendering the error page', $details);
            exit;
            return true;
        }
        return false;
    }
}
