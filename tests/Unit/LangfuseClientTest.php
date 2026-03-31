<?php

declare(strict_types=1);

use Langfuse\Cache\PromptCache;
use Langfuse\Config\LangfuseConfig;
use Langfuse\Contracts\EventBatcherInterface;
use Langfuse\Contracts\PromptApiClientInterface;
use Langfuse\Dto\IngestionEvent;
use Langfuse\Dto\ScoreBody;
use Langfuse\Dto\TraceBody;
use Langfuse\Enums\EventType;
use Langfuse\LangfuseClient;
use Langfuse\Objects\LangfuseTrace;
use Langfuse\Prompt\PromptManager;

function createClient(EventBatcherInterface $batcher, ?LangfuseConfig $config = null): LangfuseClient
{
    $config ??= new LangfuseConfig(publicKey: 'pk', secretKey: 'sk');
    $promptManager = new PromptManager(
        Mockery::mock(PromptApiClientInterface::class),
        new PromptCache(),
    );

    return new LangfuseClient($batcher, $config, $promptManager);
}

it('creates a trace and returns LangfuseTrace', function () {
    $batcher = Mockery::mock(EventBatcherInterface::class);
    $batcher->shouldReceive('enqueue')->once(); // trace-create

    $client = createClient($batcher);

    $trace = $client->trace(new TraceBody(id: 'trace-1', name: 'test'));

    expect($trace)->toBeInstanceOf(LangfuseTrace::class)
        ->and($trace->getId())->toBe('trace-1');
});

it('enqueues score event', function () {
    $batcher = Mockery::mock(EventBatcherInterface::class);
    $batcher->shouldReceive('enqueue')
        ->once()
        ->with(Mockery::on(function (IngestionEvent $event) {
            return $event->type === EventType::ScoreCreate
                && $event->body instanceof ScoreBody
                && $event->body->traceId === 'trace-1'
                && $event->body->name === 'accuracy';
        }));

    $client = createClient($batcher);

    $client->score(new ScoreBody(
        id: 'score-1',
        traceId: 'trace-1',
        name: 'accuracy',
        value: 0.95,
    ));
});

it('delegates flush to batcher', function () {
    $batcher = Mockery::mock(EventBatcherInterface::class);
    $batcher->shouldReceive('flush')->once();

    $client = createClient($batcher);

    $client->flush();
});

it('reports enabled state from config', function () {
    $batcher = Mockery::mock(EventBatcherInterface::class);

    $enabledClient = createClient(
        $batcher,
        new LangfuseConfig(publicKey: 'pk', secretKey: 'sk', enabled: true),
    );

    $disabledClient = createClient(
        $batcher,
        new LangfuseConfig(publicKey: 'pk', secretKey: 'sk', enabled: false),
    );

    expect($enabledClient->isEnabled())->toBeTrue()
        ->and($disabledClient->isEnabled())->toBeFalse();
});
