<?php

namespace App\Mcp\Planning;

use App\Mcp\Exceptions\McpException;

class PlanSchema
{
    public function get(): array
    {
        $path = base_path('docs/mcp/contracts/model-plan.schema.json');
        try {
            $schema = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            throw new McpException('MODEL_SCHEMA_INVALID', 'The model plan schema is unavailable.', httpStatus: 500);
        }

        if (!is_array($schema)) {
            throw new McpException('MODEL_SCHEMA_INVALID', 'The model plan schema is invalid.', httpStatus: 500);
        }
        unset($schema['$schema'], $schema['$id'], $schema['title']);
        return $schema;
    }
}
