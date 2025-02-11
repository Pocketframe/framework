<?php

namespace Pocketframe\Middleware;

use Closure;
use Pocketframe\Http\Request\Request;
use Pocketframe\Contracts\MiddlewareInterface;
use Pocketframe\Http\Response\Response;

class CsrfMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->method() === 'POST' || $request->method() === 'PUT' || $request->method() === 'DELETE') {
            $token = $request->post('_token');

            if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
                return Response::view('errors/' . Response::PAGE_EXPIRED, [], Response::PAGE_EXPIRED);
            }
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $next($request);
    }
}
