<?php

namespace Pocketframe\Contracts;

use Throwable;

interface ExceptionHandlerInterface
{
    public function handle(Throwable $e): bool;
}
