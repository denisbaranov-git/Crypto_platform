<?php

namespace App\Domain\Deposit\Repositories;

use App\Domain\Deposit\Entities\Deposit;
use App\Domain\Deposit\ValueObjects\DepositId;
use App\Domain\Shared\ValueObjects\ExternalKey;

interface DepositRepository
{
    public function save(Deposit $deposit): Deposit;

    public function findById(DepositId $id): ?Deposit;
    public function lockById(DepositId $id): ?Deposit;

    public function findByExternalKey(int $networkId, ExternalKey $externalKey): ?Deposit;

    public function existsByExternalKey(int $networkId, ExternalKey $externalKey): bool;

    /**
     * @return Deposit[]
     */
    public function findOpenByNetwork(int $networkId, int $limit = 500): array;

    /**
     * Все депозиты, которые сидят выше указанного блока.
     * Используется при reorg rewind.
     *
     * @return Deposit[]
     */
    public function findByNetworkAndBlockNumberGreaterThan(int $networkId, int $blockNumber): array;
    public function findByNetworkAndBlockNumberBetween( int $networkId, int $rewindTo, int $oldLastProcessedBlock): array;
}
