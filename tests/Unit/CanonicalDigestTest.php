<?php

namespace Tests\Unit;

use App\Mcp\Execution\CanonicalDigest;
use PHPUnit\Framework\TestCase;

class CanonicalDigestTest extends TestCase
{
    public function test_object_key_order_does_not_change_the_digest(): void
    {
        $digest = new CanonicalDigest();

        $this->assertSame(
            $digest->make(['workspace_id' => 'a', 'arguments' => ['status' => 'Endorsement-Complete', 'qr_link' => 'q']]),
            $digest->make(['arguments' => ['qr_link' => 'q', 'status' => 'Endorsement-Complete'], 'workspace_id' => 'a']),
        );
    }

    public function test_list_order_remains_significant(): void
    {
        $digest = new CanonicalDigest();

        $this->assertNotSame(
            $digest->make(['request_ids' => ['lookup', 'update']]),
            $digest->make(['request_ids' => ['update', 'lookup']]),
        );
    }
}
