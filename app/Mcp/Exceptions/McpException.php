<?php

namespace App\Mcp\Exceptions;

use RuntimeException;

class McpException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly array $details = [],
        public readonly int $httpStatus = 400,
    ) {
        parent::__construct($message);
    }
}
