<?php

namespace App\Mcp\Model;

use App\Mcp\Exceptions\McpException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class OllamaModelClient implements ModelClient
{
    public function generatePlan(array $messages, array $schema): ModelResponse
    {
        try {
            $response = Http::baseUrl(config('mcp.model.base_url'))
                ->acceptJson()
                ->timeout(config('mcp.model.timeout_seconds'))
                ->post('/api/chat', [
                    'model' => config('mcp.model.name'),
                    'messages' => $messages,
                    'stream' => false,
                    'format' => $schema,
                    'options' => [
                        'temperature' => 0,
                        'num_ctx' => config('mcp.model.context_length'),
                        'num_predict' => config('mcp.model.max_output_tokens'),
                    ],
                    'keep_alive' => config('mcp.model.keep_alive'),
                ]);
        } catch (ConnectionException) {
            throw new McpException(
                'MODEL_UNAVAILABLE',
                'The local Qwen model service is unavailable.',
                ['runtime' => 'ollama', 'model' => config('mcp.model.name')],
                503,
            );
        }

        if (!$response->successful()) {
            $message = $response->json('error');
            throw new McpException(
                'MODEL_UNAVAILABLE',
                is_string($message) && $message !== '' ? 'Ollama could not generate a plan: '.$message : 'Ollama could not generate a plan.',
                ['runtime' => 'ollama', 'model' => config('mcp.model.name'), 'status' => $response->status()],
                503,
            );
        }

        $content = $response->json('message.content');
        if (!is_string($content) || trim($content) === '') {
            throw new McpException('MODEL_OUTPUT_INVALID', 'Qwen returned an empty planning response.', httpStatus: 422);
        }

        return new ModelResponse(
            $content,
            (string) ($response->json('model') ?: config('mcp.model.name')),
            $this->integerOrNull($response->json('prompt_eval_count')),
            $this->integerOrNull($response->json('eval_count')),
            $this->integerOrNull($response->json('total_duration')),
        );
    }

    public function generateStructured(array $messages, array $schema): ModelResponse
    {
        return $this->generatePlan($messages, $schema);
    }

    public function status(): array
    {
        try {
            $response = Http::baseUrl(config('mcp.model.base_url'))
                ->acceptJson()
                ->timeout(min(5, config('mcp.model.timeout_seconds')))
                ->get('/api/tags');
        } catch (ConnectionException) {
            return $this->statusPayload(false, false, 'Ollama is not reachable.');
        }

        if (!$response->successful()) {
            return $this->statusPayload(false, false, 'Ollama returned HTTP '.$response->status().'.');
        }

        $configured = config('mcp.model.name');
        $models = array_map(
            fn ($model) => is_array($model) ? ($model['name'] ?? $model['model'] ?? null) : null,
            is_array($response->json('models')) ? $response->json('models') : [],
        );
        $installed = in_array($configured, array_filter($models), true);

        return $this->statusPayload(true, $installed, $installed ? null : 'The configured model is not installed.');
    }

    private function statusPayload(bool $runtimeAvailable, bool $modelInstalled, ?string $message): array
    {
        return [
            'runtime' => 'ollama',
            'runtime_available' => $runtimeAvailable,
            'model' => config('mcp.model.name'),
            'model_installed' => $modelInstalled,
            'ready' => $runtimeAvailable && $modelInstalled,
            'message' => $message,
        ];
    }

    private function integerOrNull(mixed $value): ?int
    {
        return is_int($value) || is_numeric($value) ? (int) $value : null;
    }
}
