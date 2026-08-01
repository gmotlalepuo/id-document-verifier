<?php

namespace Tests\Unit;

use App\Mcp\Execution\JsonPathExtractor;
use PHPUnit\Framework\TestCase;

class JsonPathExtractorTest extends TestCase
{
    public function test_it_extracts_object_and_array_values(): void
    {
        $extractor = new JsonPathExtractor();
        $document = ['records' => [['registration' => ['qr_link' => 'value']]]];

        $this->assertSame('value', $extractor->extract($document, '$.records[0].registration.qr_link'));
        $this->assertNull($extractor->extract($document, '$.records[1].registration.qr_link'));
    }
}
