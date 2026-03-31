<?php

declare(strict_types=1);

namespace Langfuse;

use Illuminate\Support\Facades\Facade;
use Langfuse\Contracts\LangfuseClientInterface;
use Langfuse\Testing\LangfuseFake;

/**
 * @method static \Langfuse\Objects\LangfuseTrace trace(\Langfuse\Dto\TraceBody $body)
 * @method static void score(\Langfuse\Dto\ScoreBody $body)
 * @method static void flush()
 * @method static bool isEnabled()
 * @method static \Langfuse\Contracts\PromptInterface prompt(string $name, ?int $version = null, ?string $label = null, string|array<int, array<string, string>>|null $fallback = null)
 *
 * @see \Langfuse\LangfuseClient
 */
class LangfuseFacade extends Facade
{
    public static function fake(): LangfuseFake
    {
        $fake = new LangfuseFake();
        static::swap($fake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return LangfuseClientInterface::class;
    }
}
