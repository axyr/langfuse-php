<?php

declare(strict_types=1);

namespace Langfuse\Testing;

use Langfuse\Contracts\LangfuseClientInterface;
use Langfuse\Contracts\PromptInterface;
use Langfuse\Dto\IngestionEvent;
use Langfuse\Dto\ScoreBody;
use Langfuse\Dto\TraceBody;
use Langfuse\Objects\LangfuseTrace;
use PHPUnit\Framework\Assert;

class LangfuseFake implements LangfuseClientInterface
{
    private readonly RecordingEventBatcher $batcher;

    /** @var array<PromptInterface> */
    private array $promptResponses = [];

    public function __construct()
    {
        $this->batcher = new RecordingEventBatcher();
    }

    public function trace(TraceBody $body): LangfuseTrace
    {
        return new LangfuseTrace(
            body: $body,
            batcher: $this->batcher,
        );
    }

    public function score(ScoreBody $body): void
    {
        $event = new IngestionEvent(
            id: $body->id,
            type: \Langfuse\Enums\EventType::ScoreCreate,
            timestamp: now()->toIso8601ZuluString(),
            body: $body,
        );

        $this->batcher->enqueue($event);
    }

    public function flush(): void
    {
        $this->batcher->flush();
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function prompt(
        string $name,
        ?int $version = null,
        ?string $label = null,
        string|array|null $fallback = null,
    ): PromptInterface {
        if (isset($this->promptResponses[$name])) {
            return $this->promptResponses[$name];
        }

        if (is_string($fallback)) {
            return \Langfuse\Dto\PromptFactory::fallbackText($name, $fallback);
        }

        if (is_array($fallback)) {
            return \Langfuse\Dto\PromptFactory::fallbackChat($name, $fallback);
        }

        throw \Langfuse\Exceptions\PromptNotFoundException::forName($name);
    }

    public function withPrompt(PromptInterface $prompt): self
    {
        $this->promptResponses[$prompt->getName()] = $prompt;

        return $this;
    }

    /**
     * @return array<IngestionEvent>
     */
    public function events(): array
    {
        return $this->batcher->events();
    }

    public function assertTraceCreated(?string $name = null): self
    {
        $traces = $this->batcher->eventsOfType('trace-create');

        Assert::assertNotEmpty($traces, 'Expected at least one trace to be created, but none were.');

        if ($name !== null) {
            $names = array_map(fn(IngestionEvent $e): mixed => $e->body->toArray()['name'] ?? null, $traces);
            Assert::assertContains($name, $names, "Expected a trace named '{$name}' but none was found.");
        }

        return $this;
    }

    public function assertGenerationCreated(?string $name = null): self
    {
        $generations = $this->batcher->eventsOfType('generation-create');

        Assert::assertNotEmpty($generations, 'Expected at least one generation to be created, but none were.');

        if ($name !== null) {
            $names = array_map(fn(IngestionEvent $e): mixed => $e->body->toArray()['name'] ?? null, $generations);
            Assert::assertContains($name, $names, "Expected a generation named '{$name}' but none was found.");
        }

        return $this;
    }

    public function assertScoreCreated(?string $name = null): self
    {
        $scores = $this->batcher->eventsOfType('score-create');

        Assert::assertNotEmpty($scores, 'Expected at least one score to be created, but none were.');

        if ($name !== null) {
            $names = array_map(fn(IngestionEvent $e): mixed => $e->body->toArray()['name'] ?? null, $scores);
            Assert::assertContains($name, $names, "Expected a score named '{$name}' but none was found.");
        }

        return $this;
    }

    public function assertSpanCreated(?string $name = null): self
    {
        $spans = $this->batcher->eventsOfType('span-create');

        Assert::assertNotEmpty($spans, 'Expected at least one span to be created, but none were.');

        if ($name !== null) {
            $names = array_map(fn(IngestionEvent $e): mixed => $e->body->toArray()['name'] ?? null, $spans);
            Assert::assertContains($name, $names, "Expected a span named '{$name}' but none was found.");
        }

        return $this;
    }

    public function assertEventCreated(?string $name = null): self
    {
        $events = $this->batcher->eventsOfType('event-create');

        Assert::assertNotEmpty($events, 'Expected at least one event to be created, but none were.');

        if ($name !== null) {
            $names = array_map(fn(IngestionEvent $e): mixed => $e->body->toArray()['name'] ?? null, $events);
            Assert::assertContains($name, $names, "Expected an event named '{$name}' but none was found.");
        }

        return $this;
    }

    public function assertNothingSent(): self
    {
        Assert::assertEmpty(
            $this->batcher->events(),
            'Expected no events to be sent, but ' . count($this->batcher->events()) . ' were recorded.',
        );

        return $this;
    }

    public function assertEventCount(int $expected): self
    {
        Assert::assertCount(
            $expected,
            $this->batcher->events(),
            'Expected ' . $expected . ' events but found ' . count($this->batcher->events()) . '.',
        );

        return $this;
    }
}
