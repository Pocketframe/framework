<?php

namespace Pocketframe\Exceptions;

use PDOException;
use Pocketframe\Contracts\ExceptionHandlerInterface;
use Pocketframe\Http\Response\Response;
use Throwable;

class PDOExceptionHandler implements ExceptionHandlerInterface
{
    public function handle(Throwable $e): bool
    {
        if ($e instanceof PDOException && $e->getCode() == 'HY000') {
            http_response_code(Response::INTERNAL_SERVER_ERROR);
            echo <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>Database Error</title>
    <style>
        body {
            margin: 0;
            padding: 2rem;
            min-height: auto;
            display: flex;
            flex-direction: column;
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
        <h1>Database Error</h1>
        <p>A database error occurred. Please try again later.</p>
HTML;

            if ('debug') {
                echo <<<DEBUG
        <pre>
        <div>Error: {$e->getMessage()}</div>
        <div>File: {$e->getFile()}:{$e->getLine()}</div>
        <div>Stack Trace: {$e->getTraceAsString()}</div>
</pre>
DEBUG;
            }

            echo <<<HTML
    </div>
</body>
</html>
HTML;
            return true;
        }
        return false;
    }
}
