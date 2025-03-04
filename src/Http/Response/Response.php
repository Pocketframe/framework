<?php

namespace Pocketframe\Http\Response;

use Pocketframe\Sessions\Session;
use Pocketframe\TemplateEngine\View;
use RuntimeException;

class Response
{
    const OK                    = 200;
    const CREATED               = 201;
    const REDIRECT              = 302;
    const BAD_REQUEST           = 400;
    const UNAUTHORIZED          = 401;
    const FORBIDDEN             = 403;
    const NOT_FOUND             = 404;
    const METHOD_NOT_ALLOWED    = 405;
    const PAGE_EXPIRED          = 419;
    const INTERNAL_SERVER_ERROR = 500;

    protected int $status = self::OK;
    protected array $headers = [];
    protected string $content = '';

    public function __construct(string $content = '', int $status = self::OK, array $headers = [])
    {
        $this->content = $content;
        $this->status = $status;
        $this->headers = $headers;
    }



    /**
     * Render a view template and return a Response object
     *
     * This method takes a view template name, optional data array to pass to the view,
     * and optional status code. It renders the view using the template engine and
     * returns a new Response object containing the rendered content.
     *
     * @param string $view The name of the view template to render
     * @param array $data Optional data array to pass to the view template
     * @param int $status The HTTP status code to set (defaults to 200 OK)
     * @return Response A new Response object containing the rendered view
     */
    public static function view($view, $data = [], $status = self::OK): Response
    {
        $content = View::render($view, $data);
        return new self($content, $status, ['Content-Type' => 'text/html']);
    }

    /**
     * Send a JSON response and exit the script
     *
     * This method sends a JSON response and exits the script.
     *
     * @param array $data The data to encode as JSON
     * @param int $status The HTTP status code to set
     * @return Response The new Response object
     */
    public static function json($data, $status = self::OK): Response
    {
        return new static(
            json_encode($data),
            $status,
            ['Content-Type' => 'application/json']
        );
    }

    /**
     * Send the response and exit the script
     *
     * This method sends the response and exits the script.
     *
     * @return void
     */
    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }
        echo $this->content;
    }

    /**
     * Redirect to a specific URL
     *
     * This method redirects to a specific URL and returns a new Response object.
     *
     * @param string $url The URL to redirect to
     * @param int $status The HTTP status code to set
     * @return self The new Response object
     */
    public static function redirect(string $url, int $status = self::REDIRECT, array $sessionData = []): self
    {
        unset($_SESSION['old']);

        return new static('', $status, ['Location' => $url]);
    }

    /**
     * Attach session data to the response
     *
     * @param string $key The session key
     * @param string $value The session value
     * @return self
     */
    public function withSession(string $key, string $value): self
    {
        Session::flash($key, $value);
        return $this;
    }

    /**
     * Send a text response and exit the script
     *
     * This method sends a text response and exits the script.
     *
     * @param string $text The text to send
     * @param int $status The HTTP status code to set
     * @return self The new Response object
     */
    public static function text(string $text, int $status = self::OK): self
    {
        return new static($text, $status, ['Content-Type' => 'text/plain']);
    }

    /**
     * Send a no content response and exit the script
     *
     * This method sends a no content response and exits the script.
     *
     * @return self The new Response object
     */
    public static function noContent(): self
    {
        return new static('', 204);
    }

    /**
     * Send a file response and exit the script
     *
     * This method sends a file response and exits the script.
     *
     * @param string $path The path to the file to send
     * @param string $name The name of the file to send
     * @param array $headers The headers to send
     * @return self The new Response object
     */
    public static function file(string $path, string $name = null, array $headers = []): self
    {
        if (!file_exists($path)) {
            throw new RuntimeException("File not found: $path");
        }

        $headers = array_merge([
            'Content-Type' => mime_content_type($path),
            'Content-Length' => filesize($path),
            'Content-Disposition' => sprintf('attachment; filename="%s"', $name ?? basename($path))
        ], $headers);

        return new static(file_get_contents($path), self::OK, $headers);
    }

    /**
     * Stream a response and exit the script
     *
     * This method streams a response and exits the script. It takes a callback function, a status code, and an array of headers.
     *
     * @param callable $callback The callback function to stream
     * @param int $status The HTTP status code to set
     * @param array $headers The headers to send
     * @return self The new Response object
     */
    public static function stream(callable $callback, int $status = self::OK, array $headers = []): self
    {
        return new static($callback(), $status, $headers);
    }

    /**
     * Set a header for the response
     *
     * This method sets a header for the response.
     *
     * @param string $name The name of the header
     * @param string $value The value of the header
     * @return self The new Response object
     */
    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Get the headers for the response
     *
     * This method returns the headers for the response.
     *
     * @return array The headers for the response
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Set a cookie for the response
     *
     * This method sets a cookie for the response.
     *
     * @param string $name The name of the cookie
     * @param string $value The value of the cookie
     * @param int $expire The expiration time of the cookie
     * @param string $path The path of the cookie
     * @param string $domain The domain of the cookie
     * @param bool $secure Whether the cookie is secure
     * @param bool $httponly Whether the cookie is HTTP only
     * @return self The new Response object
     */
    public function setCookie(
        string $name,
        string $value,
        int $expire = 0,
        string $path = '/',
        string $domain = '',
        bool $secure = false,
        bool $httponly = false
    ): self {
        setcookie($name, $value, $expire, $path, $domain, $secure, $httponly);
        return $this;
    }

    /**
     * Cache a response for a specific number of minutes
     *
     * This method caches a response for a specific number of minutes.
     *
     * @param int $minutes The number of minutes to cache the response
     * @return self The new Response object
     */
    public function cacheFor(int $minutes): self
    {
        return $this->setHeader('Cache-Control', 'public, max-age=' . ($minutes * 60));
    }

    /**
     * No cache response
     *
     * This method sets the response to no cache.
     *
     * @return self The new Response object
     */
    public function noCache(): self
    {
        return $this
            ->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0')
            ->setHeader('Pragma', 'no-cache')
            ->setHeader('Expires', '0');
    }

    /**
     * JSONP response
     *
     * This method returns a JSONP response.
     *
     * @param array $data The data to encode as JSON
     * @param string $callbackParam The name of the callback parameter
     * @return self The new Response object
     */
    public function jsonp(array $data, string $callbackParam = 'callback'): self
    {
        $callback = $_GET[$callbackParam] ?? 'callback';
        $content = "/**/$callback(" . json_encode($data) . ");";

        return new static(
            $content,
            self::OK,
            ['Content-Type' => 'application/javascript']
        );
    }

    /**
     * Pretty JSON response
     *
     * This method returns a pretty JSON response.
     *
     * @param array $data The data to encode as JSON
     * @param int $status The HTTP status code to set
     * @return self The new Response object
     */
    public function prettyJson(array $data, int $status = self::OK): self
    {
        return new static(
            json_encode($data, JSON_PRETTY_PRINT),
            $status,
            ['Content-Type' => 'application/json']
        );
    }

    /**
     * With header
     *
     * This method returns a new Response object with a header.
     *
     * @param string $name The name of the header
     * @param string $value The value of the header
     * @return self The new Response object
     */
    public function withHeader(string $name, string $value): self
    {
        return new static($this->content, $this->status, array_merge($this->headers, [$name => $value]));
    }

    /**
     * Check if the response is OK
     *
     * This method checks if the response is OK.
     *
     * @return bool True if the response is OK, false otherwise
     */
    public function isOk(): bool
    {
        return $this->status >= self::OK && $this->status < self::REDIRECT;
    }

    /**
     * Check if the response is a redirect
     *
     * This method checks if the response is a redirect.
     *
     * @return bool True if the response is a redirect, false otherwise
     */
    public function isRedirect(): bool
    {
        return $this->status >= self::REDIRECT && $this->status < self::BAD_REQUEST;
    }

    /**
     * Check if the response is a client error
     *
     * This method checks if the response is a client error.
     *
     * @return bool True if the response is a client error, false otherwise
     */
    public function isClientError(): bool
    {
        return $this->status >= self::BAD_REQUEST && $this->status < self::INTERNAL_SERVER_ERROR;
    }

    /**
     * Check if the response is a server error
     *
     * This method checks if the response is a server error.
     *
     * @return bool True if the response is a server error, false otherwise
     */
    public function isServerError(): bool
    {
        return $this->status >= self::INTERNAL_SERVER_ERROR;
    }

    /**
     * Attachment
     *
     * This method returns a new Response object with an attachment header.
     *
     * @param string $filename The filename of the attachment
     * @return self The new Response object
     */
    public function attachment(string $filename): self
    {
        return $this->setHeader('Content-Disposition', sprintf('attachment; filename="%s"', $filename));
    }


    /**
     * Fallback error handler
     *
     * This method handles fallback errors by displaying an error page.
     *
     * @param string $message The error message to display
     * @return void
     */
    private static function fallbackError(string $message)
    {
        http_response_code(self::INTERNAL_SERVER_ERROR);
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
