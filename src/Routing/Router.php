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

    /**
     * Array of registered routes.
     *
     * Each route is stored as an array containing:
     * - method: The HTTP method (GET, POST, etc)
     * - uri: The URI pattern to match
     * - action: The controller action or closure to execute
     * - middleware: Array of middleware to apply
     *
     * @var array
     */
    protected $routes = [];

    /**
     * Stack of route groups.
     *
     * Each group is stored as an array containing:
     * - middleware: Array of middleware to apply
     *
     * @var array
     */
    protected $groupStack = [];

    /**
     * Array of global middleware to apply to all routes.
     *
     * @var array
     */
    protected $globalMiddleware = [];

    /**
     * Container instance.
     *
     * @var Container
     */
    protected $container;


    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Register a GET route.
     *
     * Registers a new route that matches GET requests to the given URI pattern.
     * The route will execute the specified controller action or closure when matched.
     * Optional middleware can be applied to filter the request before reaching the action.
     *
     * @param string $uri The URI pattern to match
     * @param mixed $action The controller action or closure to execute
     * @param array $middleware Array of middleware to apply
     */
    public function get($uri, $action, $middleware = [])
    {
        $this->add('GET', $uri, $action, $middleware);
    }

    /**
     * Register a POST route.
     *
     * Registers a new route that matches POST requests to the given URI pattern.
     * The route will execute the specified controller action or closure when matched.
     * Optional middleware can be applied to filter the request before reaching the action.
     *
     * @param string $uri The URI pattern to match
     * @param mixed $action The controller action or closure to execute
     * @param array $middleware Array of middleware to apply
     */
    public function post($uri, $action, $middleware = [])
    {
        $this->add('POST', $uri, $action, $middleware);
    }

    /**
     * Register a PUT route.
     *
     * Registers a new route that matches PUT requests to the given URI pattern.
     * The route will execute the specified controller action or closure when matched.
     * Optional middleware can be applied to filter the request before reaching the action.
     *
     * @param string $uri The URI pattern to match
     * @param mixed $action The controller action or closure to execute
     * @param array $middleware Array of middleware to apply
     */
    public function put($uri, $action, $middleware = [])
    {
        $this->add('PUT', $uri, $action, $middleware);
    }

    /**
     * Register a DELETE route.
     *
     * Registers a new route that matches DELETE requests to the given URI pattern.
     * The route will execute the specified controller action or closure when matched.
     * Optional middleware can be applied to filter the request before reaching the action.
     *
     * @param string $uri The URI pattern to match
     * @param mixed $action The controller action or closure to execute
     * @param array $middleware Array of middleware to apply
     */
    public function delete($uri, $action, $middleware = [])
    {
        $this->add('DELETE', $uri, $action, $middleware);
    }

    /**
     * Register a group of routes.
     *
     * Registers a group of routes with optional middleware and a callback to define the routes.
     *
     * @param array $attributes Array of attributes for the group
     * @param Closure $callback Callback to define the routes
     */
    public function group(array $attributes, Closure $callback)
    {
        $this->groupStack[] = $attributes;
        $callback($this);
        array_pop($this->groupStack);
    }

    /**
     * Add global middleware to be applied to all routes.
     *
     * @param mixed $middleware The middleware to add
     */
    public function addGlobalMiddleware($middleware)
    {
        $this->globalMiddleware = array_merge(
            $this->globalMiddleware,
            (array)$middleware
        );
    }

    /**
     * Add a route to the router.
     *
     * This method registers a new route with the router. The route is defined by:
     * - An HTTP method (GET, POST, etc) to match against
     * - A URI pattern that can include dynamic parameters in {param} format
     * - A controller action or closure to handle the request
     * - Optional middleware to filter the request
     *
     * The URI pattern supports dynamic parameters like:
     * - /posts/{id} - Matches /posts/1, /posts/2, etc
     * - /users/{name} - Matches /users/john, /users/jane, etc
     *
     * The method will:
     * 1. Merge any global and group middleware with route-specific middleware
     * 2. Extract parameter names from the URI pattern
     * 3. Convert the URI pattern to a regex for matching
     * 4. Store the route configuration for later matching
     *
     * @param string $method The HTTP method to match
     * @param string $uri The URI pattern to match with optional {param} placeholders
     * @param mixed $action The controller action or closure to execute
     * @param array $middleware Array of middleware to apply
     */
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

    /**
     * Dispatch a request to the router.
     *
     * This method matches the incoming request against registered routes and executes
     * the corresponding controller action. It handles parameter extraction from URIs,
     * builds and executes the middleware pipeline, and returns the response.
     * If no matching route is found, it returns a 404 response.
     *
     * @param Request $request The request to dispatch
     * @return Response The response from the router
     */
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

    /**
     * Call a controller method.
     *
     * This method extracts parameters from the request and invokes the specified
     * controller method with the provided parameters. It handles type-checking
     * for parameter types and ensures that required parameters are provided.
     *
     * @param object $controller The controller instance
     * @param string $method The method to call on the controller
     * @param Request $request The request object
     * @param array $params The parameters to pass to the method
     * @return mixed The result of the controller method call
     */
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
