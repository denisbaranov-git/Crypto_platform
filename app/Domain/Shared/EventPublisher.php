<?php

namespace App\Domain\Shared;

interface EventPublisher
{
    public function publishAfterCommit(array $events): void;
}
