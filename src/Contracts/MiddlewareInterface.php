<?php

namespace Pocketframe\Contracts;

use Closure;
use Pocketframe\Http\Request\Request;
use Pocketframe\Http\Response\Response;

interface MiddlewareInterface
{
    public function handle(Request $request, Closure $next): Response;
}
