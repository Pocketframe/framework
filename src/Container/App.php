<?php

namespace Pocketframe\Container;

use Pocketframe\Http\Request\Request;
use Pocketframe\Http\Response\Response;
use Pocketframe\Logger\Logger;
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
            $request = $this->container->get(Request::class);
            $response = $this->router->dispatch($request);

            // Validate response type
            if (!$response instanceof Response) {
                throw new \RuntimeException(
                    'Controller must return a Response instance'
                );
            }

            $response->send();
        } catch (\Throwable $e) {
            $this->container->get('exceptionHandler')->handle($e);
            $this->container->get(Logger::class)->log($e->getMessage());
        }
    }
}
