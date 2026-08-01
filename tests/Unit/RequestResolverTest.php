<?php

namespace Tests\Unit;

use App\Mcp\Exceptions\McpException;
use App\Mcp\Resolution\RequestResolver;
use Tests\TestCase;

class RequestResolverTest extends TestCase
{
    public function test_rejects_an_environment_from_another_collection(): void
    {
        $this->expectException(McpException::class);
        $this->expectExceptionMessage('does not belong to the request collection');

        (new RequestResolver())->resolve(
            ['id' => 'request', 'collection_id' => 'collection-a'],
            ['id' => 'environment', 'collection_id' => 'collection-b'],
        );
    }

    public function test_disabled_variables_are_not_used(): void
    {
        try {
            (new RequestResolver())->resolve(
                ['id' => 'request', 'collection_id' => 'collection', 'url' => '{{national_id}}'],
                [
                    'id' => 'environment',
                    'collection_id' => 'collection',
                    'variables' => [['key' => 'national_id', 'value' => '436415528', 'enabled' => false]],
                ],
            );
            $this->fail('Expected an unresolved variable error.');
        } catch (McpException $exception) {
            $this->assertSame('UNRESOLVED_VARIABLE', $exception->errorCode);
            $this->assertSame(['national_id'], $exception->details['missing_variables']);
        }
    }
}
