<?php

namespace Pocketframe\Container;

use Pocketframe\Http\Request\Request;
use Pocketframe\Routing\Router;
use Throwable;

class App
{
    public function __construct(
        protected Container $container,
        protected Router $router
    ) {}

    public function run()
    {
        try {
            // Handle the request through router
            $response = $this->router->dispatch(
                $this->container->get(Request::class)
            );

            // Send response to client
            $response->send();
        } catch (Throwable $e) {
            // Handle exceptions
            $this->container->get('exceptionHandler')->handle($e);
        }
    }
}
