<?php

use Core\Http\Response;
use Core\Sessions\Session;

if (!function_exists('base_path')) {
    function base_path($path)
    {
        return BASE_PATH . $path;
    }
}

if (!function_exists('view')) {
    function view($path, $attributes = [])
    {
        extract($attributes);
        require base_path("views/pages/{$path}.view.php");
    }
}

if (!function_exists('urlIs')) {
    function urlIs($value)
    {
        return $_SERVER['REQUEST_URI'] === $value;
    }
}

if (!function_exists('abort')) {
    function abort($code = Response::NOT_FOUND)
    {
        http_response_code($code);
        require "views/pages/errors/{$code}.php";
        die();
    }
}

if (!function_exists('authorize')) {
    function authorize($condition, $status = Response::FORBIDDEN)
    {
        if (!$condition) {
            abort(Response::FORBIDDEN);
        }
    }
}

if (!function_exists('redirect')) {
    function redirect($path)
    {
        header("Location: {$path}");
        die();
    }
}

if (!function_exists('old')) {
    function old($key, $default = null)
    {
        return Session::get('old')[$key] ?? $default;
    }
}

if (!function_exists('env')) {
    function env($key, $default = null)
    {
        return $_ENV[$key] ?? $default;
    }
}


if (!function_exists('numberToWords')) {
    function numberToWords($value)
    {
        $words = new NumberFormatter("en", NumberFormatter::SPELLOUT);
        return ucwords($words->format($value));

    }
}

if(!function_exists('csrfToken')){
    function csrfToken(){
        if(empty($_SESSION['csrf_token'])){
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if(!function_exists('validateCsrfToken')){
    function validateCsrfToken($token){
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}
