<?php

namespace Pocketframe\Middleware;

class Middleware
{
    const MAP = [
        'auth' => Auth::class,
        'guest' => Guest::class,
    ];
}
