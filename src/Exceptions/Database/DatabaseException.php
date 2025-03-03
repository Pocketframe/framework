<?php

declare(strict_types=1);

namespace Pocketframe\Exceptions;

class DatabaseException extends PocketframeException
{
    public function __construct($message = "Failed to connect to the database")
    {
        parent::__construct($message, 500);
    }
}
