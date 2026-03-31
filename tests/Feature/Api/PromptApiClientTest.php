<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Langfuse\Api\PromptApiClient;
use Langfuse\Config\LangfuseConfig;

beforeEach(function () {
    $this->config = new LangfuseConfig(
        publicKey: 'pk-test',
        secretKey: 'sk-test',
        baseUrl: 'https://test.langfuse.com',
    );
});

it('fetches prompt from api', function () {
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

    $client = new PromptApiClient($this->config);
    $result = $client->get('movie-critic');

    expect($result)->toBeArray()
        ->and($result['name'])->toBe('movie-critic')
        ->and($result['prompt'])->toBe('Review {{movie}}');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/api/public/v2/prompts/movie-critic')
            && $request->hasHeader('Authorization', 'Basic ' . base64_encode('pk-test:sk-test'));
    });
});

it('passes version as query parameter', function () {
    Http::fake([
        'test.langfuse.com/api/public/v2/prompts/*' => Http::response([
            'name' => 'test',
            'version' => 3,
            'type' => 'text',
            'prompt' => 'v3',
        ]),
    ]);

    $client = new PromptApiClient($this->config);
    $client->get('test', version: 3);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'version=3');
    });
});

it('passes label as query parameter', function () {
    Http::fake([
        'test.langfuse.com/api/public/v2/prompts/*' => Http::response([
            'name' => 'test',
            'version' => 1,
            'type' => 'text',
            'prompt' => 'labeled',
        ]),
    ]);

    $client = new PromptApiClient($this->config);
    $client->get('test', label: 'staging');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'label=staging');
    });
});

it('returns null on http error', function () {
    Http::fake([
        'test.langfuse.com/api/public/v2/prompts/*' => Http::response('Not Found', 404),
    ]);

    $client = new PromptApiClient($this->config);
    $result = $client->get('nonexistent');

    expect($result)->toBeNull();
});

it('returns null on network error', function () {
    Http::fake([
        'test.langfuse.com/api/public/v2/prompts/*' => fn() => throw new \Exception('Connection refused'),
    ]);

    $client = new PromptApiClient($this->config);
    $result = $client->get('test');

    expect($result)->toBeNull();
});

it('does not send version or label when null', function () {
    Http::fake([
        'test.langfuse.com/api/public/v2/prompts/*' => Http::response([
            'name' => 'test',
            'version' => 1,
            'type' => 'text',
            'prompt' => 'test',
        ]),
    ]);

    $client = new PromptApiClient($this->config);
    $client->get('test');

    Http::assertSent(function ($request) {
        return ! str_contains($request->url(), 'version=')
            && ! str_contains($request->url(), 'label=');
    });
});
