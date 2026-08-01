<?php

namespace App\Mcp\Auth;

class AuthenticatedUser
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $email,
        public readonly string $accessToken,
    ) {
    }
}
