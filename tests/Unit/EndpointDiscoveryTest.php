<?php

namespace Tests\Unit;

use App\Mcp\Knowledge\EndpointDiscovery;
use PHPUnit\Framework\TestCase;

class EndpointDiscoveryTest extends TestCase
{
    public function test_endpoint_names_rank_above_documentation_only_matches(): void
    {
        $requests = [
            [
                'id' => 'documentation-match',
                'name' => 'Fetch record',
                'documentation' => 'Gets a teacher using their national ID.',
                'method' => 'GET',
                'url' => '/records',
                'collection_name' => 'Registration',
            ],
            [
                'id' => 'name-match',
                'name' => 'Get teacher by national ID',
                'documentation' => '',
                'method' => 'GET',
                'url' => '/teachers',
                'collection_name' => 'Registration',
            ],
        ];

        $results = (new EndpointDiscovery())->rank($requests, 'teacher national ID');

        $this->assertSame('name-match', $results[0]['request_id']);
        $this->assertSame('read', $results[0]['side_effect_hint']);
        $this->assertStringContainsString('discovery hint', $results[0]['authority_warning']);
    }

    public function test_non_read_http_methods_are_classified_as_write_hints(): void
    {
        $results = (new EndpointDiscovery())->rank([[
            'id' => 'update',
            'name' => 'Update registration status',
            'documentation' => '',
            'method' => 'PATCH',
            'url' => '/registration',
        ]], 'update registration');

        $this->assertSame('write', $results[0]['side_effect_hint']);
    }
}
