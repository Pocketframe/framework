<?php

namespace Pocketframe\Routing;

use Pocketframe\Container\Container;
use Pocketframe\Http\Request\Request;
use Pocketframe\Http\Response\Response;

class Router
{
    protected $routes = [];
    protected $middleware = [];
    protected $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function get($uri, $action, $middleware = [])
    {
        $this->add('GET', $uri, $action, $middleware);
    }

    public function post($uri, $action, $middleware = [])
    {
        $this->add('POST', $uri, $action, $middleware);
    }

    protected function add($method, $uri, $action, $middleware)
    {
        $this->routes[$method][$uri] = [
            'action' => $action,
            'middleware' => $middleware
        ];
    }

    public function dispatch(Request $request)
    {
        $method = $request->method();
        $uri = $request->uri();

        foreach ($this->routes[$method] as $routeUri => $route) {
            $pattern = '#^' . preg_replace('/\{[a-z]+\}/', '([^/]+)', $routeUri) . '$#';

            if (preg_match($pattern, $uri, $matches)) {
                array_shift($matches);

                // Run middleware stack
                foreach ($route['middleware'] as $middlewareClass) {
                    $middleware = $this->container->get($middlewareClass);
                    if (!$middleware->handle($request)) {
                        return $middleware->handle($request);
                    }
                }

                [$controllerClass, $method] = explode('@', $route['action']);
                $controller = $this->container->get($controllerClass);
                return $controller->$method($request, ...$matches);
            }
        }

        return Response::view('errors/404', [], 404);
    }
}
