<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Langfuse\Contracts\PromptInterface;
use Langfuse\LangfuseFacade;

beforeEach(function () {
    config([
        'langfuse.public_key' => 'pk-test',
        'langfuse.secret_key' => 'sk-test',
        'langfuse.base_url' => 'https://test.langfuse.com',
        'langfuse.enabled' => true,
        'langfuse.flush_at' => 100,
    ]);

    $this->app->forgetInstance(\Langfuse\Config\LangfuseConfig::class);
    $this->app->forgetInstance(\Langfuse\Contracts\EventBatcherInterface::class);
    $this->app->forgetInstance(\Langfuse\Contracts\IngestionApiClientInterface::class);
    $this->app->forgetInstance(\Langfuse\Contracts\LangfuseClientInterface::class);
    $this->app->forgetInstance(\Langfuse\Contracts\PromptApiClientInterface::class);
    $this->app->forgetInstance(\Langfuse\Contracts\PromptCacheInterface::class);
    $this->app->forgetInstance(\Langfuse\Prompt\PromptManager::class);
});

it('fetches prompt via facade', function () {
    Http::fake([
        'test.langfuse.com/api/public/v2/prompts/*' => Http::response([
            'name' => 'movie-critic',
            'version' => 1,
            'type' => 'text',
            'prompt' => 'Review {{movie}}',
            'config' => ['model' => 'gpt-4'],
            'labels' => ['production'],
        ]),
    ]);

    $prompt = LangfuseFacade::prompt('movie-critic');

    expect($prompt)->toBeInstanceOf(PromptInterface::class)
        ->and($prompt->getName())->toBe('movie-critic')
        ->and($prompt->compile(['movie' => 'Dune 2']))->toBe('Review Dune 2');
});

it('fetches prompt with version via facade', function () {
    Http::fake([
        'test.langfuse.com/api/public/v2/prompts/*' => Http::response([
            'name' => 'test',
            'version' => 3,
            'type' => 'text',
            'prompt' => 'v3 prompt',
        ]),
    ]);

    $prompt = LangfuseFacade::prompt('test', version: 3);

    expect($prompt->getVersion())->toBe(3);
});

it('uses fallback when api fails via facade', function () {
    Http::fake([
        'test.langfuse.com/api/public/v2/prompts/*' => Http::response('Error', 500),
    ]);

    $prompt = LangfuseFacade::prompt('test', fallback: 'Fallback {{var}}');

    expect($prompt->isFallback())->toBeTrue()
        ->and($prompt->compile(['var' => 'value']))->toBe('Fallback value');
});
