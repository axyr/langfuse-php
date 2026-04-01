<?php

declare(strict_types=1);

namespace Langfuse\Concerns;

use Langfuse\Contracts\SerializableInterface;
use Langfuse\Dto\IdGenerator;
use Langfuse\Dto\IngestionEvent;
use Langfuse\Enums\EventType;

trait CreatesIngestionEvents
{
    protected function generateId(): string
    {
        return IdGenerator::uuid();
    }

    protected function generateTimestamp(): string
    {
        return IdGenerator::timestamp();
    }

    protected function createIngestionEvent(EventType $type, SerializableInterface $body): IngestionEvent
    {
        return new IngestionEvent(
            id: $this->generateId(),
            type: $type,
            timestamp: $this->generateTimestamp(),
            body: $body,
        );
    }
}
