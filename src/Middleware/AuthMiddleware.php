<?php

namespace Pocketframe\Middleware;

use Closure;
use Pocketframe\Http\Request\Request;
use Pocketframe\Http\Response\Response;

class AuthMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Check if user is authenticated
        if (!$this->isAuthenticated($request)) {
            return $request->expectsJson()
                ? Response::json(['error' => 'Unauthenticated'], Response::UNAUTHORIZED)
                : redirect('/login');
        }

        return $next($request);
    }

    protected function isAuthenticated(Request $request): bool
    {
        // Check session for user authentication
        return $request->session()->has('user');
    }
}
