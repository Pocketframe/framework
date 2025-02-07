<?php

namespace Pocketframe\Exceptions;

use Pocketframe\Http\Response\Response;
use Throwable;

class Handler
{
    public static function handle(Throwable $e)
    {
        error_log($e->getMessage());

        try {
            Response::view('errors/500', [
                'error' => $e
            ], 500);
        } catch (Throwable $fallbackError) {
            http_response_code(500);
            echo <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>500 Internal Server Error</title>
    <style>
        body {
            margin: 0;
            padding: 2rem;
            min-height: auto;
            display: flex;
            flex-direction: column;
            /* align-items: center; */
            justify-content: center;
            font-family: system-ui, sans-serif;
            background: #f8fafc;
            text-align: center;
        }
        
        .error-container {
            max-width: auto;
            padding: 2rem;
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        h1 {
            color: #dc2626;
            margin: 0 0 1rem 0;
        }
        
        pre {
            white-space: pre-wrap;
            word-wrap: break-word;
            background: #f1f5f9;
            padding: 1rem;
            border-radius: 4px;
            text-align: left;
            max-width: 100%;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <h1>500 Internal Server Error</h1>
        <p>An error occurred while rendering the error page</p>
HTML;

            if ('debug') {
                echo <<<DEBUG
        <pre>
        <div>Error: {$fallbackError->getMessage()}</div>
        <div>File: {$fallbackError->getFile()}:{$fallbackError->getLine()}</div>
        <div>Stack Trace: {$fallbackError->getTraceAsString()}</div>
</pre>
DEBUG;
            }

            echo <<<HTML
    </div>
</body>
</html>
HTML;
            exit;
        }
    }
}
