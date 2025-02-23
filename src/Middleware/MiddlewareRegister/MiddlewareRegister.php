<?php

namespace Pocketframe\Middleware\MiddlewareRegister;

use Pocketframe\Routing\Router;

class MiddlewareRegister
{
  public function register(Router $router)
  {
    $middlewareConfig = require config_path('middleware');

    // Register global middleware
    foreach ($middlewareConfig['global'] as $middleware) {
      $router->addGlobalMiddleware($middleware);
    }

    // Register middleware groups
    $router->group(['middleware' => $middlewareConfig['groups']['web']], function () use ($router) {
      require routes_path('web');
    });

    $router->group(['middleware' => $middlewareConfig['groups']['api'], 'prefix' => 'api'], function () use ($router) {
      require routes_path('api');
    });
  }
}
