<?php

namespace Pocketframe\Http\Response;

class Response
{
    const NOT_FOUND = 404;
    const FORBIDDEN = 403;
    const INTERNAL_SERVER_ERROR = 500;
    const REDIRECT = 302;
    const OK = 200;
    const CREATED = 201;
    const BAD_REQUEST = 400;
    const UNAUTHORIZED = 401;
    const METHOD_NOT_ALLOWED = 405;

    public static function view($view, $data = [], $status = 200)
    {
        http_response_code($status);
        $viewPath = base_path("resources/views/{$view}.view.php");
        if (!file_exists($viewPath)) {
            self::fallbackError("resources/iew [{$view}] not found");
        }
        extract($data);
        require $viewPath;
        exit;
    }

    public static function json($data, $status = 200)
    {
        header('Content-Type: application/json');
        http_response_code($status);
        echo json_encode($data);
        exit;
    }

    private static function fallbackError(string $message)
    {
        http_response_code(500);
        echo "<!DOCTYPE html>
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
    <div class=\"error-container\">
        <h1>500 Internal Server Error</h1>
        <p>An error occurred while rendering the error page</p>";

        if ('debug') {
            echo "<pre>
        <div>Error:" . $message . "</div>
        <div>File:" . (new \Exception())->getFile() . ":" . (new \Exception())->getLine() . "</div>
        <div>Stack Trace:" . (new \Exception())->getTraceAsString() . "</div>
        </pre>";
        }
        echo "</div>
</body>
</html>";
        exit;
    }
}
