<?php

namespace Tests\Unit;

use App\Mcp\Exceptions\McpException;
use App\Mcp\Execution\RolloutPolicy;
use Tests\TestCase;

class RolloutPolicyTest extends TestCase
{
    public function test_exact_reviewed_uat_scope_is_required(): void
    {
        $allowed = config('mcp.rollout.issue_license');
        (new RolloutPolicy())->assertIssueLicense($allowed);

        $this->expectException(McpException::class);
        (new RolloutPolicy())->assertIssueLicense(array_merge($allowed, ['environment_id' => '00000000-0000-4000-8000-000000000000']));
    }
}
