<?php

declare(strict_types=1);

namespace Langfuse\Contracts;

use Langfuse\Dto\ScoreBody;
use Langfuse\Dto\TraceBody;
use Langfuse\Objects\LangfuseTrace;

interface LangfuseClientInterface
{
    public function trace(TraceBody $body): LangfuseTrace;

    public function score(ScoreBody $body): void;

    public function flush(): void;

    public function isEnabled(): bool;

    /**
     * @param string|array<int, array<string, string>>|null $fallback
     */
    public function prompt(
        string $name,
        ?int $version = null,
        ?string $label = null,
        string|array|null $fallback = null,
    ): PromptInterface;
}
