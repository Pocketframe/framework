<?php

namespace Pocketframe\Exceptions\Render;

class ErrorPageRenderer
{
    public static function render($title, $message, $details = null)
    {
        echo <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>{$title}</title>
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
        <h1>{$title}</h1>
        <p>{$message}</p>
HTML;

        if ($details) {
            echo <<<DEBUG
        <pre>{$details}</pre>
DEBUG;
        }

        echo <<<HTML
    </div>
</body>
</html>
HTML;
    }
}
