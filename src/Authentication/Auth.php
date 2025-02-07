<?php

namespace Pocketframe\Middleware;

use Pocketframe\Contracts\MiddlewareInterface;

class Auth implements MiddlewareInterface
{
    public function handle()
    {
        if (!$_SESSION['user'] ?? false) {
            header('Location: /');
            exit();
        }
    }
}
