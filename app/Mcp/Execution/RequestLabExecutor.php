<?php

namespace App\Mcp\Execution;

use App\Mcp\Auth\AuthenticatedUser;

interface RequestLabExecutor
{
    public function execute(
        AuthenticatedUser $user,
        string $requestId,
        string $environmentId,
        string $expectedCollectionId,
        string $expectedMethod,
        array $overrides,
        string $expectedDefinitionDigest,
        ?string $expectedHost = null,
    ): array;
}
