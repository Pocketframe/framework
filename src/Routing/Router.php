<?php

namespace Pocketframe\Routing;

use Closure;
use Pocketframe\Container\Container;
use Pocketframe\Http\Request\Request;
use Pocketframe\Http\Response\Response;
use InvalidArgumentException;
use Pocketframe\TemplateEngine\View;
use ReflectionMethod;

class Router
{
  /**
   * Array of registered routes.
   *
   * Each route is stored as an array containing:
   * - method: The HTTP method (GET, POST, etc)
   * - uri: The URI pattern to match
   * - action: The controller action or closure to execute. This can be a string ("Controller@method"),
   *   an array ([Controller::class, 'method']), or a simple method name if a group controller is set.
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
   * - prefix: A URI prefix for all routes in the group
   * - controller: A controller class to use for all routes in the group (if the route action is given as a method name)
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

  /**
   * Array of named routes.
   *
   * @var array
   */
  protected $namedRoutes = [];

  public function __construct(Container $container)
  {
    $this->container = $container;
  }

  /**
   * Register a GET route.
   *
   * @param string $uri The URI pattern to match
   * @param mixed $action The controller action or closure to execute
   * @param array|null $middleware Array of middleware to apply
   * @param string|null $name Optional name for the route
   */
  public function get(string $uri, $action, ?array $middleware = null, ?string $name = null)
  {
    $this->add('GET', $uri, $action, $middleware ?? [], $name);
  }

  /**
   * Register a POST route.
   *
   * @param string $uri
   * @param mixed $action
   * @param array|null $middleware
   * @param string|null $name
   */
  public function post(string $uri, $action, ?array $middleware = null, ?string $name = null)
  {
    $this->add('POST', $uri, $action, $middleware ?? [], $name);
  }

  /**
   * Register a PUT route.
   *
   * @param string $uri
   * @param mixed $action
   * @param array|null $middleware
   * @param string|null $name
   */
  public function put(string $uri, $action, ?array $middleware = null, ?string $name = null)
  {
    $this->add('PUT', $uri, $action, $middleware ?? [], $name);
  }

  /**
   * Register a DELETE route.
   *
   * @param string $uri
   * @param mixed $action
   * @param array|null $middleware
   * @param string|null $name
   */
  public function delete(string $uri, $action, ?array $middleware = null, ?string $name = null)
  {
    $this->add('DELETE', $uri, $action, $middleware ?? [], $name);
  }

  /**
   * Register a group of routes.
   *
   * The attributes can include:
   * - middleware: an array of middleware to apply to the group.
   * - prefix: a URI prefix for all routes in the group.
   * - controller: a default controller class to use for routes in the group.
   *
   * @param array $attributes
   * @param Closure $callback
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
   * @param mixed $middleware
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
   * This method registers a new route with the router.
   *
   * @param string $method The HTTP method to match
   * @param string $uri The URI pattern to match (supports {param} placeholders)
   * @param mixed $action The controller action or closure. It can be:
   *                      - A string like "Controller@method"
   *                      - A simple method name (e.g., "index") if a group controller is set
   *                      - An array like [Controller::class, 'method']
   * @param array $middleware Array of middleware to apply
   * @param string|null $name Optional name for the route
   */
  protected function add($method, $uri, $action, $middleware = [], $name = null)
  {
    // Process group attributes
    foreach ($this->groupStack as $group) {
      // If a prefix is defined, prepend it to the URI.
      if (isset($group['prefix'])) {
        $prefix = '/' . trim($group['prefix'], '/');
        // Ensure the URI begins with a slash.
        $uri = $prefix . ($uri === '/' ? '' : $uri);
      }
      // If a controller is defined and the action is a simple method name,
      // or if the action is an array with an empty controller, fill it in.
      if (isset($group['controller'])) {
        if (is_string($action) && strpos($action, '@') === false) {
          $action = [$group['controller'], $action];
        } elseif (is_array($action) && (empty($action[0]) || $action[0] === '@')) {
          $action[0] = $group['controller'];
        }
      }
    }

    // Merge global middleware and group middleware with route-specific middleware.
    $mergedMiddleware = $this->globalMiddleware;
    foreach ($this->groupStack as $group) {
      if (isset($group['middleware'])) {
        $mergedMiddleware = array_merge($mergedMiddleware, (array)$group['middleware']);
      }
    }
    $mergedMiddleware = array_merge($mergedMiddleware, (array)$middleware);

    // Extract parameter names from the URI (e.g. {id}).
    preg_match_all('/\{([a-z]+)\}/', $uri, $matches);
    $paramNames = $matches[1] ?? [];

    // Convert the URI pattern into a regular expression.
    $pattern = preg_replace('/\{([a-z]+)\}/', '([^/]+)', $uri);

    $routeData = [
      'action' => $action,
      'middleware' => $mergedMiddleware,
      'params' => $paramNames,
      'pattern' => $pattern
    ];

    $this->routes[$method][$uri] = $routeData;

    if ($name) {
      $this->namedRoutes[$name] = ['uri' => $uri, 'params' => $paramNames];
    }
  }

  /**
   * Generate a URL for a named route.
   *
   * @param string $name
   * @param array $params
   * @return string
   * @throws InvalidArgumentException
   */
  public function route($name, $params = [])
  {
    if (!isset($this->namedRoutes[$name])) {
      throw new InvalidArgumentException("Route {$name} not found.");
    }

    $route = $this->namedRoutes[$name];
    $uri = $route['uri'];

    foreach ($route['params'] as $param) {
      if (!isset($params[$param])) {
        throw new InvalidArgumentException("Missing route parameter: {$param}");
      }
      $uri = str_replace("{{$param}}", $params[$param], $uri);
    }

    return $uri;
  }

  /**
   * Dispatch a request to the router.
   *
   * @param Request $request
   * @return Response
   */
  public function dispatch(Request $request)
  {
    $uri = $request->uri();

    // Serve static files if requested.
    if (preg_match('/\.(css|js|png|jpg|jpeg|gif|svg|woff|woff2|ttf|eot|ico)$/', $uri)) {
      return $this->serveStaticFile($uri);
    }

    $method = $request->method();

    foreach ($this->routes[$method] ?? [] as $route) {
      if (preg_match('#^' . $route['pattern'] . '$#', $uri, $matches)) {
        array_shift($matches);
        $params = array_combine($route['params'], $matches);

        $middlewareStack = array_map(
          fn($class) => $this->container->get($class),
          $route['middleware']
        );

        $coreHandler = function ($request) use ($route, $params) {
          $action = $route['action'];
          if (is_array($action)) {
            [$controllerClass, $method] = $action;
          } else {
            $parts = explode('@', $action);
            $controllerClass = $parts[0];
            $method = $parts[1] ?? '__invoke'; // Default to __invoke
          }
          $controller = $this->container->get($controllerClass);
          return $this->callControllerMethod($controller, $method, $request, $params);
        };

        $pipeline = array_reduce(
          array_reverse($middlewareStack),
          fn($next, $middleware) => fn($req) => $middleware->handle($req, $next),
          $coreHandler
        );

        return $pipeline($request);
      }
    }

    $content = View::renderFile(__DIR__ . '/../resources/views/errors/' . Response::NOT_FOUND . '.view.php');
    return new Response($content, Response::NOT_FOUND, ['Content-Type' => 'text/html']);
  }

  /**
   * Serve static files directly from the public/ directory.
   *
   * @param string $uri
   * @return Response
   */
  protected function serveStaticFile($uri)
  {
    $filePath = BASE_PATH . 'public/' . ltrim($uri, '/');

    if (!file_exists($filePath)) {
      return Response::view('errors/' . Response::NOT_FOUND, [], Response::NOT_FOUND);
    }

    $mimeTypes = [
      'css' => 'text/css',
      'js' => 'application/javascript',
      'png' => 'image/png',
      'jpg' => 'image/jpeg',
      'jpeg' => 'image/jpeg',
      'gif' => 'image/gif',
      'svg' => 'image/svg+xml',
      'woff' => 'font/woff',
      'woff2' => 'font/woff2',
      'ttf' => 'font/ttf',
      'eot' => 'application/vnd.ms-fontobject',
      'ico' => 'image/x-icon'
    ];

    $extension = pathinfo($filePath, PATHINFO_EXTENSION);
    $contentType = $mimeTypes[$extension] ?? 'application/octet-stream';

    header('Content-Type: ' . $contentType);
    readfile($filePath);
    exit;
  }

  /**
   * Call a controller method.
   *
   * @param object $controller The controller instance.
   * @param string $method The method to call.
   * @param Request $request The request object.
   * @param array $params The parameters to pass to the method.
   * @return mixed The result of the controller method call.
   * @throws InvalidArgumentException
   */
  protected function callControllerMethod($controller, string $method, Request $request, array $params)
  {
    $reflection = new ReflectionMethod($controller, $method);
    $args = [];

    foreach ($reflection->getParameters() as $param) {
      $type = $param->getType();
      if ($type && $type->getName() === Request::class) {
        $args[] = $request;
      } elseif (isset($params[$param->getName()])) {
        $args[] = $params[$param->getName()];
      } else {
        throw new InvalidArgumentException("Missing parameter {$param->getName()}");
      }
    }

    return $reflection->invokeArgs($controller, $args);
  }
}
