<?php

declare(strict_types=1);

namespace Pocketframe\Exceptions;

use Exception;

class DatabaseException extends PocketframeException
{
    public function __construct(string $message, int $code = 500, string $errorType = 'database', ?Exception $previous = null)
    {
        parent::__construct($message, $code, 'Database', $previous);
    }
}
