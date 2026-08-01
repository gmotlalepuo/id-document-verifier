<?php

namespace App\Mcp\Model;

class ModelResponse
{
    public function __construct(
        public readonly string $content,
        public readonly string $model,
        public readonly ?int $promptTokens = null,
        public readonly ?int $completionTokens = null,
        public readonly ?int $durationNanoseconds = null,
    ) {
    }
}
