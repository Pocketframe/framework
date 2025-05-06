<?php

use Pocketframe\Routing\Router;
use Pocketframe\Http\Request\Request;
use Pocketframe\Http\Response\Response;
use Pocketframe\Container\Container;
use function Pest\Faker\faker;

beforeEach(function () {
    $this->container = Mockery::mock(Container::class);
    $this->router = new Router($this->container);
});

it('registers a GET route', function () {
    $this->router->get('/hello', fn () => 'world');
    expect(true)->toBeTrue();
});

it('registers a route with name and resolves route URL with params', function () {
    $this->router->get('/user/{id}', fn () => 'user', [], 'user.profile');
    $url = $this->router->route('user.profile', ['id' => 5]);
    expect($url)->toBe('/user/5');
});

it('throws if route name is not found', function () {
    $this->router->route('missing');
})->throws(InvalidArgumentException::class);

it('throws if named route is missing parameters', function () {
    $this->router->get('/post/{id}', fn () => '', [], 'post.show');
    $this->router->route('post.show', []);
})->throws(InvalidArgumentException::class);

it('dispatches a route and calls the controller method', function () {
    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->shouldReceive('uri')->andReturn('/greet');
    $mockRequest->shouldReceive('method')->andReturn('GET');

    $controller = new class {
        public function hello() {
            return new Response('Hello World');
        }
    };

    $this->container->shouldReceive('get')->with(get_class($controller))->andReturn($controller);

    $this->router->get('/greet', [get_class($controller), 'hello']);
    $response = $this->router->dispatch($mockRequest);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->content())->toBe('Hello World');
});

it('applies middleware in dispatch pipeline', function () {
    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->shouldReceive('uri')->andReturn('/mid');
    $mockRequest->shouldReceive('method')->andReturn('GET');

    $controller = new class {
        public function handle() {
            return new Response('Done');
        }
    };

    $middleware = new class {
        public function handle($request, $next) {
            return $next($request);
        }
    };

    $this->container->shouldReceive('get')->with(get_class($middleware))->andReturn($middleware);
    $this->container->shouldReceive('get')->with(get_class($controller))->andReturn($controller);

    $this->router->get('/mid', [get_class($controller), 'handle'], [get_class($middleware)]);
    $response = $this->router->dispatch($mockRequest);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->content())->toBe('Done');
});

it('applies route group with prefix and controller', function () {
    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->shouldReceive('uri')->andReturn('/api/ping');
    $mockRequest->shouldReceive('method')->andReturn('GET');

    $controller = new class {
        public function ping() {
            return new Response('pong');
        }
    };

    $this->container->shouldReceive('get')->with(get_class($controller))->andReturn($controller);

    $this->router->group([
        'prefix' => 'api',
        'controller' => get_class($controller)
    ], function ($router) {
        $router->get('/ping', 'ping');
    });

    $response = $this->router->dispatch($mockRequest);
    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->content())->toBe('pong');
});
