# Langfuse PHP SDK for Laravel

A Laravel package for integrating with [Langfuse](https://langfuse.com) - the open-source LLM observability platform. Track traces, spans, generations, events, and scores from your Laravel application with minimal setup.

## Problem

When building LLM-powered applications, you need visibility into what's happening: which prompts were sent, what the model returned, how long it took, how much it cost, and whether the output was any good. Without observability, debugging issues and improving quality is guesswork.

Langfuse solves this by providing a tracing and analytics platform purpose-built for LLM applications. This SDK gives your Laravel application a clean, idiomatic way to send that telemetry data to Langfuse.

## Features

- **Trace LLM interactions** - capture the full lifecycle of requests, from input to output
- **Nested observations** - organize work into traces, spans, and generations with automatic parent-child relationships
- **Generation tracking** - record model name, parameters, token usage, and costs for each LLM call
- **Scoring** - attach numeric, boolean, or categorical quality scores to traces and observations
- **Prompt management** - fetch, cache, and compile prompts from Langfuse with stale-while-revalidate caching and fallback support
- **Prism integration** - optional auto-instrumentation for [Prism](https://github.com/prism-php/prism) LLM calls
- **Automatic batching** - events are queued and sent in batches to minimize HTTP overhead
- **Graceful degradation** - API failures are caught and logged, never thrown; a disabled mode silently no-ops
- **Auto-flush on shutdown** - queued events are flushed automatically when the application terminates
- **Facade support** - use `Langfuse::trace(...)` anywhere in your code
- **Testing utilities** - swap the client with a fake for assertions in your test suite

## Requirements

- PHP 8.4+
- Laravel 11 or 12

## Installation

Install via Composer:

```bash
composer require langfuse/langfuse-php
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=langfuse-config
```

Add your Langfuse credentials to `.env`:

```env
LANGFUSE_PUBLIC_KEY=pk-lf-...
LANGFUSE_SECRET_KEY=sk-lf-...
```

## Configuration

All configuration lives in `config/langfuse.php` and can be overridden via environment variables:

| Variable | Default | Description |
|---|---|---|
| `LANGFUSE_PUBLIC_KEY` | `""` | Your Langfuse project public key |
| `LANGFUSE_SECRET_KEY` | `""` | Your Langfuse project secret key |
| `LANGFUSE_BASE_URL` | `https://cloud.langfuse.com` | Langfuse API base URL (change for self-hosted) |
| `LANGFUSE_ENABLED` | `true` | Set to `false` to disable all tracing |
| `LANGFUSE_FLUSH_AT` | `10` | Number of events to batch before auto-flushing |
| `LANGFUSE_REQUEST_TIMEOUT` | `15` | HTTP request timeout in seconds |
| `LANGFUSE_PROMPT_CACHE_TTL` | `60` | Prompt cache TTL in seconds |
| `LANGFUSE_PRISM_ENABLED` | `false` | Enable automatic Prism LLM call tracing |

## Usage

### Basic trace

```php
use Langfuse\LangfuseFacade as Langfuse;
use Langfuse\Dto\TraceBody;

$trace = Langfuse::trace(new TraceBody(
    id: 'trace-1',
    name: 'chat-request',
    userId: 'user-42',
    metadata: ['environment' => 'production'],
    tags: ['chat', 'gpt-4'],
));
```

### Tracking an LLM generation

```php
use Langfuse\Dto\GenerationBody;
use Langfuse\Dto\Usage;

$generation = $trace->generation(new GenerationBody(
    id: 'gen-1',
    name: 'chat-completion',
    model: 'gpt-4',
    input: [['role' => 'user', 'content' => 'Explain observability']],
    modelParameters: ['temperature' => 0.7, 'max_tokens' => 500],
));

// After the LLM responds:
$generation->end(
    output: [['role' => 'assistant', 'content' => 'Observability is...']],
    usage: new Usage(input: 12, output: 85, total: 97),
);
```

### Spans for non-LLM work

Use spans to track any operation within a trace - database queries, API calls, processing steps:

```php
use Langfuse\Dto\SpanBody;

$span = $trace->span(new SpanBody(
    id: 'span-1',
    name: 'retrieve-context',
));

// ... do work ...

$span->end(output: 'Retrieved 5 documents');
```

### Nesting observations

Spans and generations can be nested to represent complex workflows:

```php
$trace = Langfuse::trace(new TraceBody(id: 'trace-1', name: 'rag-pipeline'));

$retrievalSpan = $trace->span(new SpanBody(id: 'span-retrieval', name: 'retrieval'));

    $embeddingGen = $retrievalSpan->generation(new GenerationBody(
        id: 'gen-embed',
        name: 'embed-query',
        model: 'text-embedding-3-small',
    ));
    $embeddingGen->end(output: [0.1, 0.2, 0.3], usage: new Usage(input: 8, total: 8));

    $searchSpan = $retrievalSpan->span(new SpanBody(id: 'span-search', name: 'vector-search'));
    $searchSpan->end(output: '5 results found');

$retrievalSpan->end(output: 'context ready');

$completionGen = $trace->generation(new GenerationBody(
    id: 'gen-completion',
    name: 'answer',
    model: 'gpt-4',
    input: [['role' => 'user', 'content' => 'What is RAG?']],
));
$completionGen->end(
    output: [['role' => 'assistant', 'content' => 'RAG stands for...']],
    usage: new Usage(input: 120, output: 200, total: 320),
);
```

### Events

Lightweight observations for logging discrete moments without a start/end lifecycle:

```php
use Langfuse\Dto\EventBody;

$trace->event(new EventBody(
    id: 'event-1',
    name: 'cache-hit',
    input: ['key' => 'user-profile-42'],
    output: ['cached' => true],
));
```

### Scores

Attach quality metrics to traces or specific observations:

```php
use Langfuse\Dto\ScoreBody;
use Langfuse\Enums\ScoreDataType;

// Score on a trace
$trace->score(new ScoreBody(
    id: 'score-1',
    name: 'user-satisfaction',
    value: 4.5,
    dataType: ScoreDataType::NUMERIC,
    comment: 'User rated the response positively',
));

// Score without a trace (via client directly)
Langfuse::score(new ScoreBody(
    id: 'score-2',
    name: 'hallucination',
    traceId: 'trace-1',
    value: 0.0,
    dataType: ScoreDataType::BOOLEAN,
));
```

### Error tracking

Mark failed operations with a level and status message:

```php
use Langfuse\Enums\ObservationLevel;

$generation->end(
    level: ObservationLevel::ERROR,
    statusMessage: 'Rate limited by provider',
);
```

### Flushing

Events are batched and auto-flushed when the queue reaches the `LANGFUSE_FLUSH_AT` threshold. They are also automatically flushed when the Laravel application terminates.

To flush manually:

```php
Langfuse::flush();
```

### Prompt management

Fetch prompts from your Langfuse project, with built-in caching and fallback support:

```php
use Langfuse\LangfuseFacade as Langfuse;

// Fetch and compile a text prompt
$prompt = Langfuse::prompt('movie-critic');
$compiled = $prompt->compile(['movie' => 'Dune 2']);

// Fetch a specific version or label
$prompt = Langfuse::prompt('movie-critic', version: 3);
$prompt = Langfuse::prompt('movie-critic', label: 'production');

// Provide a fallback for when the API is unavailable
$prompt = Langfuse::prompt('movie-critic', fallback: 'Review {{movie}} briefly.');

// Chat prompts work the same way
$prompt = Langfuse::prompt('chat-assistant', fallback: [
    ['role' => 'system', 'content' => 'You are a helpful assistant.'],
]);
$messages = $prompt->compile(['name' => 'World']);
```

Prompts are cached in-memory with a configurable TTL (`LANGFUSE_PROMPT_CACHE_TTL`). When the cache expires, the SDK revalidates from the API - if the API is unavailable, the stale cached version is returned. Link prompt metadata to generations for full traceability:

```php
$prompt = Langfuse::prompt('movie-critic');
$generation = $trace->generation(new GenerationBody(
    id: 'gen-1',
    name: 'review',
    model: 'gpt-4',
    metadata: $prompt->toLinkMetadata(),
));
```

### Prism integration

If you use [Prism](https://github.com/prism-php/prism) for LLM calls, enable automatic tracing with a single environment variable:

```env
LANGFUSE_PRISM_ENABLED=true
```

When enabled, the SDK wraps Prism's provider layer to automatically create traces and generations for every `text()`, `structured()`, and `stream()` call - including model parameters, token usage, and error tracking. No code changes required.

### Testing with fakes

The SDK provides a testing double that records events without making HTTP calls:

```php
use Langfuse\Dto\GenerationBody;
use Langfuse\Dto\TraceBody;
use Langfuse\LangfuseFacade as Langfuse;

// In your test
$fake = Langfuse::fake();

// Run your application code...
$trace = Langfuse::trace(new TraceBody(id: 'trace-1', name: 'test'));
$trace->generation(new GenerationBody(id: 'gen-1', name: 'chat'));

// Assert what was recorded
$fake->assertTraceCreated('test')
    ->assertGenerationCreated('chat')
    ->assertEventCount(2);

// Other available assertions
$fake->assertSpanCreated('name');
$fake->assertScoreCreated('name');
$fake->assertEventCreated('name');
$fake->assertNothingSent();
```

You can also pre-configure prompt responses for the fake:

```php
use Langfuse\Dto\TextPrompt;

$fake = Langfuse::fake();
$fake->withPrompt(new TextPrompt(name: 'test', version: 1, prompt: 'Hello {{name}}'));

$prompt = Langfuse::prompt('test');
$prompt->compile(['name' => 'World']); // "Hello World"
```

### Disabling in tests

Set `LANGFUSE_ENABLED=false` in your `.env.testing` to silently disable all tracing. The SDK substitutes a no-op batcher - no events are queued or sent, and no code changes are needed.

## Architecture

```
┌──────────────────────────────────────────────────┐
│  Your Application                                │
│                                                  │
│  Langfuse::trace()  →  LangfuseTrace             │
│                         ├── span()  →  LangfuseSpan
│                         ├── generation() → LangfuseGeneration
│                         ├── event()                │
│                         └── score()                │
└───────────────┬──────────────────────────────────┘
                │ enqueue(IngestionEvent)
                ▼
        ┌───────────────┐
        │  EventBatcher │  queues events, auto-flushes at threshold
        └───────┬───────┘
                │ send(IngestionBatch)
                ▼
     ┌─────────────────────┐
     │  IngestionApiClient │  HTTP POST to Langfuse API
     └─────────────────────┘
```

All DTOs are immutable readonly classes. API and batching failures are caught and logged - they never propagate exceptions to your application.

## Testing

```bash
composer test
```

## License

MIT
