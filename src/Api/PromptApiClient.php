<?php

declare(strict_types=1);

namespace Langfuse\Api;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Langfuse\Config\LangfuseConfig;
use Langfuse\Contracts\PromptApiClientInterface;

class PromptApiClient implements PromptApiClientInterface
{
    public function __construct(
        private readonly LangfuseConfig $config,
    ) {}

    public function get(string $name, ?int $version = null, ?string $label = null): ?array
    {
        try {
            return $this->doGet($name, $version, $label);
        } catch (\Throwable $throwable) {
            Log::warning('Langfuse prompt fetch error', ['message' => $throwable->getMessage()]);

            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function doGet(string $name, ?int $version, ?string $label): ?array
    {
        $query = array_filter([
            'version' => $version,
            'label' => $label,
        ], fn(mixed $value): bool => $value !== null);

        $response = Http::withHeaders([
            'Authorization' => $this->config->authHeader(),
            'Content-Type' => 'application/json',
        ])
            ->timeout($this->config->requestTimeout)
            ->get($this->config->promptsUrl($name), $query);

        if (! $response->successful()) {
            Log::warning('Langfuse prompt fetch failed', [
                'status' => $response->status(),
                'name' => $name,
            ]);

            return null;
        }

        /** @var array<string, mixed>|null */
        return $response->json();
    }
}
