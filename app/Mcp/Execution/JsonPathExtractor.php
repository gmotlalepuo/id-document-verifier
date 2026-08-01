<?php

namespace App\Mcp\Execution;

use App\Mcp\Exceptions\McpException;

class JsonPathExtractor
{
    public function extract(array $document, string $path): mixed
    {
        if (!preg_match('/^\$(?:\.[a-zA-Z_][a-zA-Z0-9_]*|\[\d+\])+$/', $path)) {
            throw new McpException('KNOWLEDGE_INVALID', 'The configured response extraction path is invalid.', ['path' => $path], 500);
        }

        preg_match_all('/\.([a-zA-Z_][a-zA-Z0-9_]*)|\[(\d+)\]/', substr($path, 1), $matches, PREG_SET_ORDER);
        $value = $document;
        foreach ($matches as $match) {
            $key = $match[1] !== '' ? $match[1] : (int) $match[2];
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }

        return $value;
    }
}
