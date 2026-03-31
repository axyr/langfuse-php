<?php

declare(strict_types=1);

namespace Langfuse\Cache;

use Langfuse\Contracts\PromptCacheInterface;
use Langfuse\Contracts\PromptInterface;

class PromptCache implements PromptCacheInterface
{
    /** @var array<string, array{prompt: PromptInterface, expiresAt: float}> */
    private array $cache = [];

    public function get(string $key): ?PromptInterface
    {
        if (! $this->has($key)) {
            return null;
        }

        return $this->cache[$key]['prompt'];
    }

    public function put(string $key, PromptInterface $prompt, int $ttl): void
    {
        $this->cache[$key] = [
            'prompt' => $prompt,
            'expiresAt' => microtime(true) + $ttl,
        ];
    }

    public function isExpired(string $key): bool
    {
        if (! isset($this->cache[$key])) {
            return true;
        }

        return microtime(true) > $this->cache[$key]['expiresAt'];
    }

    public function has(string $key): bool
    {
        return isset($this->cache[$key]);
    }
}
