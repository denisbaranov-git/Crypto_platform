<?php

namespace App\Infrastructure\Events;

use App\Domain\Shared\EventPublisher;
use Illuminate\Support\Facades\DB;

final class LaravelEventPublisher implements EventPublisher
{
    public function publishAfterCommit(array $events): void
    {
        DB::afterCommit(function () use ($events) {
            foreach ($events as $event) {
                event($event);
            }
        });
    }
    public function publish(array $events): void
    {
        foreach ($events as $event) {
            event($event);
        }
    }
}
