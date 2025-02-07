<?php

namespace Pocketframe\Http\Request;

class Request
{
    public function method()
    {
        return $_SERVER['REQUEST_METHOD'];
    }

    public function uri()
    {
        return parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    }

    public function all()
    {
        return array_merge($_GET, $_POST);
    }

    public function get($key, $default = null)
    {
        return $_GET[$key] ?? $default;
    }

    public function post($key, $default = null)
    {
        return $_POST[$key] ?? $default;
    }

    public function sanitize($input)
    {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
}
