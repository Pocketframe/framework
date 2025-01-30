<?php

namespace Core\Middleware;

class Middleware
{
    const MAP = [
        'auth' => Auth::class,
        'guest' => Guest::class,
    ];
}
