<?php

namespace Pocketframe\Exceptions;

use Exception;

class ValidationException extends PocketframeException
{
    protected $errors;

    public function __construct($errors = [], $message = "Validation failed")
    {
        $this->errors = $errors;
        parent::__construct($message, 422);
    }

    public function getErrors()
    {
        return $this->errors;
    }
}
