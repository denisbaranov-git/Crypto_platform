<?php

namespace App\Infrastructure\Blockchain\Repositories;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentNetworkScannerCursor;

final class NetworkScannerCursorRepository
{
    public function get(int $networkId): EloquentNetworkScannerCursor
    {
        return EloquentNetworkScannerCursor::query()->firstOrCreate(
            ['network_id' => $networkId],
            [
                'last_processed_block' => 0,
                'last_processed_block_hash' => null,
                'metadata' => [],
            ]
        );
    }

    public function advance(int $networkId, int $blockNumber, string $blockHash): void
    {
        $cursor = $this->get($networkId);

        $cursor->last_processed_block = $blockNumber;
        $cursor->last_processed_block_hash = $blockHash;
        $cursor->scanned_at = now();
        $cursor->save();
    }

    public function rewind(int $networkId, int $blockNumber): void
    {
        $cursor = $this->get($networkId);

        $cursor->last_processed_block = $blockNumber;
        $cursor->last_processed_block_hash = null;
        $cursor->scanned_at = now();
        $cursor->save();
    }
    public function touch(int $networkId, array $attributes = []): void
    {
        EloquentNetworkScannerCursor::query()->updateOrCreate(
            ['network_id' => $networkId],
            array_merge([
                'scanned_at' => now(),
            ], $attributes)
        );
    }
}
