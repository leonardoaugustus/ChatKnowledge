<?php

namespace App\Services\Tools;

use App\Enums\HttpMethod;
use App\Exceptions\ToolInputValidationException;
use App\Models\AgentTool;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class HttpToolExecutor
{
    /**
     * Execute a configured HTTP tool with the given validated input and return
     * the parsed response.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     *
     * @throws ToolInputValidationException
     */
    public function execute(AgentTool $tool, array $input): array
    {
        $this->validateInput($tool, $input);

        $request = Http::withHeaders($tool->headers ?? []);
        $request = $this->applyAuth($request, $tool->auth ?? []);

        $payloadKey = $tool->method === HttpMethod::Get ? 'query' : 'json';

        $response = $request->send($tool->method->value, $tool->endpoint, [$payloadKey => $input]);

        $response->throw();

        return $response->json() ?? ['raw' => $response->body()];
    }

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ToolInputValidationException
     */
    protected function validateInput(AgentTool $tool, array $input): void
    {
        $required = data_get($tool->input_schema, 'required', []);

        $missing = array_values(array_filter(
            $required,
            fn (string $key) => ! array_key_exists($key, $input),
        ));

        if ($missing !== []) {
            throw new ToolInputValidationException(
                'Missing required input: '.implode(', ', $missing),
            );
        }
    }

    /**
     * @param  array<string, mixed>  $auth
     */
    protected function applyAuth(PendingRequest $request, array $auth): PendingRequest
    {
        return match ($auth['type'] ?? null) {
            'bearer' => $request->withToken((string) ($auth['token'] ?? '')),
            'basic' => $request->withBasicAuth((string) ($auth['username'] ?? ''), (string) ($auth['password'] ?? '')),
            default => $request,
        };
    }
}
