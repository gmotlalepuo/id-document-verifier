<?php

namespace App\Mcp\Model;

interface ModelClient
{
    public function generatePlan(array $messages, array $schema): ModelResponse;
    public function status(): array;
}
