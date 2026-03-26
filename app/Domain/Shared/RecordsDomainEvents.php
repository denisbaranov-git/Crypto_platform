<?php

namespace App\Domain\Shared;

trait RecordsDomainEvents
{
    /** @var array<object> */
    private array $domainEvents = [];

    protected function recordDomainEvent(object $event): void
    {
        $this->domainEvents[] = $event;
    }

    /**
     * @return array<object>
     */
    public function pullDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }
}
