<?php

namespace Core\Routing;

use Core\Middleware\Middleware;

class Router
{
    protected $routes = [];

    public function addItemsToArray($method, $uri, $controller)
    {
        $controller = $controller . '.php';
        $this->routes[] = [
            'uri' => $uri,
            'controller' => $controller,
            'method' => $method,
            'middleware' => null,
        ];
        return $this;
    }

    public function get($uri, $controller)
    {
        return $this->addItemsToArray('GET', $uri, $controller);
    }

    public function post($uri, $controller)
    {
        return $this->addItemsToArray('POST', $uri, $controller);
    }

    public function put($uri, $controller)
    {
        return $this->addItemsToArray('PUT', $uri, $controller);
    }

    public function delete($uri, $controller)
    {
        return $this->addItemsToArray('DELETE', $uri, $controller);
    }

    public function only($key)
    {
        $this->routes[array_key_last($this->routes)]['middleware'] = $key;
        return $this;
    }

    public function previousUrl()
    {
        return $_SERVER['HTTP_REFERER'];
    }


    public function route($uri, $method)
    {
        foreach ($this->routes as $route) {
            if ($route['uri'] === $uri && $route['method'] === strtoupper($method)) {
                if ($route['middleware']) {
                    $middleware = Middleware::MAP[$route['middleware']];
                    (new $middleware)->handle();
                }
                return require base_path($route['controller']);
            }
        }

        $this->abort();
    }

    function abort($code = 404)
    {
        http_response_code($code);
        require "views/pages/errors/{$code}.php";
        die();
    }
}
