<?php

namespace App\Domain\Shared\Outbox;

final readonly class OutboxMessage
{
    public function __construct(
        public string $idempotencyKey,
        public string $aggregateType,
        public string|int $aggregateId,
        public string $eventType,
        public array $payload,
        public ?\DateTimeImmutable $availableAt = null,
    ) {}

    public static function fromDomainEvent(
        string $aggregateType,
        string|int $aggregateId,
        object $event,
        string $idempotencyKey,
        ?\DateTimeImmutable $availableAt = null,
    ): self {
        return new self(
            idempotencyKey: $idempotencyKey,
            aggregateType: $aggregateType,
            aggregateId: $aggregateId,
            eventType: $event::class,
            payload: json_decode(json_encode($event, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR),
            availableAt: $availableAt,
        );
    }
}
