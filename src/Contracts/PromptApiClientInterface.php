<?php

declare(strict_types=1);

namespace Langfuse\Contracts;

interface PromptApiClientInterface
{
    /**
     * @return array<string, mixed>|null
     */
    public function get(string $name, ?int $version = null, ?string $label = null): ?array;
}
