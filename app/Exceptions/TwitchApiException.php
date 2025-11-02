<?php

namespace App\Exceptions;

use Exception;

class TwitchApiException extends Exception
{
    protected $code;

    public function __construct(string $message = "", int $code = 500)
    {
        parent::__construct($message, $code);
        $this->code = $code;
    }

    public function render($request)
    {
        return response()->json([
            'error' => $this->getMessage()
        ], $this->code);
    }
} 