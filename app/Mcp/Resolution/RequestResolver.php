<?php

namespace App\Mcp\Resolution;

use App\Mcp\Exceptions\McpException;

class RequestResolver
{
    public function resolve(array $request, array $environment): array
    {
        if (($request['collection_id'] ?? null) !== ($environment['collection_id'] ?? null)) {
            throw new McpException(
                'RESOURCE_RELATIONSHIP_INVALID',
                'The selected environment does not belong to the request collection.',
                httpStatus: 422,
            );
        }

        $variables = $this->enabledRows($environment['variables'] ?? []);
        $values = [];
        $secretValues = [];
        foreach ($variables as $variable) {
            $key = trim((string) ($variable['key'] ?? ''));
            if ($key === '') {
                continue;
            }
            $value = (string) ($variable['value'] ?? '');
            $values[$key] = $value;
            if ($this->isSecretKey($key)) {
                $secretValues[$key] = $value;
            }
        }

        $missing = [];
        $resolve = function (string $value) use ($values, &$missing): string {
            return preg_replace_callback('/\{\{\s*([^{}]+?)\s*\}\}/', function (array $matches) use ($values, &$missing) {
                $key = trim($matches[1]);
                if (!array_key_exists($key, $values)) {
                    $missing[$key] = true;
                    return $matches[0];
                }
                return $values[$key];
            }, $value);
        };

        $headers = $this->resolveRows($request['headers'] ?? [], $resolve);
        $params = $this->resolveRows($request['params'] ?? [], $resolve);
        $form = $this->resolveRows($request['body_form'] ?? [], $resolve);
        $auth = is_array($request['auth'] ?? null) ? $request['auth'] : ['type' => 'none'];
        foreach (['bearerToken', 'basicUsername', 'basicPassword'] as $field) {
            if (isset($auth[$field]) && is_string($auth[$field])) {
                $auth[$field] = $resolve($auth[$field]);
            }
        }

        $resolved = [
            'request_id' => $request['id'] ?? null,
            'environment_id' => $environment['id'] ?? null,
            'method' => $request['method'] ?? 'GET',
            'url' => $resolve((string) ($request['url'] ?? '')),
            'params' => $params,
            'headers' => $headers,
            'body_mode' => $request['body_mode'] ?? 'none',
            'body_raw' => $resolve((string) ($request['body_raw'] ?? '')),
            'body_form' => $form,
            'auth' => $auth,
        ];

        if ($missing) {
            $names = array_keys($missing);
            sort($names);
            throw new McpException(
                'UNRESOLVED_VARIABLE',
                'The request needs additional environment variables: '.implode(', ', $names).'.',
                [
                    'request_id' => $request['id'] ?? null,
                    'environment_id' => $environment['id'] ?? null,
                    'missing_variables' => $names,
                ],
                422,
            );
        }

        return $this->redact($resolved, $secretValues);
    }

    public function redactEnvironment(array $environment): array
    {
        $environment['variables'] = array_map(function ($variable) {
            if (!is_array($variable)) {
                return $variable;
            }
            if ($this->isSecretKey((string) ($variable['key'] ?? ''))) {
                $variable['value'] = '[REDACTED]';
            }
            return $variable;
        }, is_array($environment['variables'] ?? null) ? $environment['variables'] : []);

        return $environment;
    }

    private function resolveRows(mixed $rows, callable $resolve): array
    {
        return array_values(array_map(function (array $row) use ($resolve) {
            $row['key'] = $resolve((string) ($row['key'] ?? ''));
            $row['value'] = $resolve((string) ($row['value'] ?? ''));
            return $row;
        }, $this->enabledRows($rows)));
    }

    private function enabledRows(mixed $rows): array
    {
        if (!is_array($rows)) {
            return [];
        }
        return array_values(array_filter($rows, fn ($row) =>
            is_array($row) && ($row['enabled'] ?? true) === true
        ));
    }

    private function redact(array $resolved, array $secretValues): array
    {
        $replace = function (mixed $value) use (&$replace, $secretValues): mixed {
            if (is_array($value)) {
                foreach ($value as $key => $item) {
                    if ($this->isSecretKey((string) $key) && $key !== 'type') {
                        $value[$key] = '[REDACTED]';
                    } else {
                        $value[$key] = $replace($item);
                    }
                }
                return $value;
            }
            if (!is_string($value)) {
                return $value;
            }
            foreach ($secretValues as $secret) {
                if ($secret !== '') {
                    $value = str_replace($secret, '[REDACTED]', $value);
                }
            }
            return $value;
        };

        $resolved = $replace($resolved);
        foreach ($resolved['headers'] as &$header) {
            if ($this->isSecretKey((string) ($header['key'] ?? ''))) {
                $header['value'] = '[REDACTED]';
            }
        }
        unset($header);
        return $resolved;
    }

    private function isSecretKey(string $key): bool
    {
        return preg_match(config('mcp.secret_key_pattern'), $key) === 1;
    }
}
