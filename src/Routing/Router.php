<?php

namespace Pocketframe\Routing;

use Closure;
use Pocketframe\Container\Container;
use Pocketframe\Http\Request\Request;
use Pocketframe\Http\Response\Response;
use InvalidArgumentException;
use ReflectionMethod;

class Router
{
    protected $routes = [];
    protected $groupStack = [];
    protected $globalMiddleware = [];
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

    public function put($uri, $action, $middleware = [])
    {
        $this->add('PUT', $uri, $action, $middleware);
    }

    public function delete($uri, $action, $middleware = [])
    {
        $this->add('DELETE', $uri, $action, $middleware);
    }

    public function group(array $attributes, Closure $callback)
    {
        $this->groupStack[] = $attributes;
        $callback($this);
        array_pop($this->groupStack);
    }

    public function addGlobalMiddleware($middleware)
    {
        $this->globalMiddleware = array_merge(
            $this->globalMiddleware,
            (array)$middleware
        );
    }

    protected function add($method, $uri, $action, $middleware = [])
    {
        $mergedMiddleware = $this->globalMiddleware;

        foreach ($this->groupStack as $group) {
            if (isset($group['middleware'])) {
                $mergedMiddleware = array_merge(
                    $mergedMiddleware,
                    (array)$group['middleware']
                );
            }
        }

        $mergedMiddleware = array_merge($mergedMiddleware, (array)$middleware);

        preg_match_all('/\{([a-z]+)\}/', $uri, $matches);
        $paramNames = $matches[1] ?? [];

        $this->routes[$method][$uri] = [
            'action' => $action,
            'middleware' => $mergedMiddleware, // Fixed: Use merged middleware
            'params' => $paramNames,
            'pattern' => preg_replace('/\{([a-z]+)\}/', '([^/]+)', $uri)
        ];
    }

    public function dispatch(Request $request)
    {
        $method = $request->method();
        $uri = $request->uri();

        foreach ($this->routes[$method] ?? [] as $route) {
            if (preg_match('#^' . $route['pattern'] . '$#', $uri, $matches)) {
                array_shift($matches);
                $params = array_combine($route['params'], $matches);

                $middlewareStack = array_map(
                    fn($class) => $this->container->get($class),
                    $route['middleware']
                );

                $coreHandler = function ($request) use ($route, $params) {
                    [$controllerClass, $method] = explode('@', $route['action']);
                    $controller = $this->container->get($controllerClass);
                    return $this->callControllerMethod(
                        $controller,
                        $method,
                        $request,
                        $params
                    );
                };

                $pipeline = array_reduce(
                    array_reverse($middlewareStack),
                    fn($next, $middleware) => fn($req) => $middleware->handle($req, $next),
                    $coreHandler
                );

                return $pipeline($request);
            }
        }

        return Response::view('errors/' . Response::NOT_FOUND, [], Response::NOT_FOUND);
    }

    protected function callControllerMethod(
        $controller,
        string $method,
        Request $request,
        array $params
    ) {
        $reflection = new ReflectionMethod($controller, $method);
        $args = [];

        foreach ($reflection->getParameters() as $param) {
            $type = $param->getType();

            if ($type && $type->getName() === Request::class) {
                $args[] = $request;
            } elseif (isset($params[$param->getName()])) {
                $args[] = $params[$param->getName()];
            } else {
                throw new InvalidArgumentException(
                    "Missing parameter {$param->getName()}"
                );
            }
        }

        return $reflection->invokeArgs($controller, $args);
    }
}
