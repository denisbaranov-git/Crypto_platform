<?php

namespace App\Infrastructure\Blockchain;

use App\Domain\Deposit\Repositories\DepositRepository;
use App\Domain\Shared\Outbox\OutboxMessage;
use App\Domain\Shared\Outbox\OutboxRepository;
use App\Infrastructure\Blockchain\Contracts\BlockchainClient;
use App\Infrastructure\Persistence\Eloquent\Repositories\NetworkScannerCursorRepository;
use App\Domain\Deposit\ValueObjects\BlockNumber;

final class ReorgDetector
{
    public function __construct(
        private readonly NetworkScannerCursorRepository $cursors,
        private readonly DepositRepository $deposits,
        private readonly OutboxRepository $outbox,
    ) {}

    /**
     * Проверяем только последний обработанный блок.
     * Это простая и очень практичная защита:
     * если hash старого блока не совпал, значит chain reorganized.
     */
    public function detectAndRewind(int $networkId, BlockchainClient $client, int $reorgWindowBlocks): bool
    {
        $cursor = $this->cursors->get($networkId);

        if ($cursor->last_processed_block <= 0 || ! $cursor->last_processed_block_hash) {
            return false;
        }

        $currentHash = $client->blockHash($cursor->last_processed_block);

        if ($currentHash === $cursor->last_processed_block_hash) {
            return false;
        }

        // Самый простой безопасный rewind:
        // отступаем на reorg window, чтобы перечитать проблемный участок.
        $rewindTo = max(0, $cursor->last_processed_block - $reorgWindowBlocks);

        $this->markAffectedDepositsAsReorged($networkId, $rewindTo);
        $this->cursors->rewind($networkId, $rewindTo);

        return true;
    }

    private function markAffectedDepositsAsReorged(int $networkId, int $rewindTo): void
    {
        $affectedDeposits = $this->deposits->findByNetworkAndBlockNumberGreaterThan($networkId, $rewindTo);

        foreach ($affectedDeposits as $deposit) {
            $deposit->markReorged(new BlockNumber($rewindTo));

            $saved = $this->deposits->save($deposit);

            foreach ($saved->pullDomainEvents() as $event) {
                $this->outbox->append(OutboxMessage::fromDomainEvent(
                    aggregateType: 'deposit',
                    aggregateId: $saved->id()->value(),
                    event: $event,
                    idempotencyKey: 'deposit:' . $saved->id()->value() . ':reorged:' . $rewindTo,
                ));
            }
        }
    }
}
