<?php

namespace App\Domain\Deposit\Entities;

use App\Domain\Deposit\ValueObjects\DepositStatus;
use App\Domain\Shared\RecordsDomainEvents;
use App\Domain\Shared\ValueObject\CryptoMoney;

final class Deposit
{
    use RecordsDomainEvents;
    private function __construct(
        private TxId $txId,
        private LogIndex $logIndex,
        private WalletAddress $address,
        private CryptoMoney $amount,
        private DepositStatus $status,
        private int $confirmations = 0,
        private ?BlockNumber $blockNumber = null,
    ) {}

    public static function detect(
        TxId $txId,
        LogIndex $logIndex,
        WalletAddress $address,
        CryptoMoney $amount,
        BlockNumber $block
    ): self {
        return new self(
            $txId,
            $logIndex,
            $address,
            $amount,
            DepositStatus::DETECTED,
            0,
            $block
        );
    }

    public function confirm(int $confirmations): void
    {
        if ($this->status === DepositStatus::CREDITED) {
            return;
        }

        $this->confirmations = $confirmations;

        if ($confirmations >= 12) {
            $this->status = DepositStatus::CONFIRMED;
        }

        $this->recordDomainEvent( new DepositConfirmed($this->txId, $this->logIndex));
    }

    public function credit(): void
    {
        if ($this->status !== DepositStatus::CONFIRMED) {
            throw new \DomainException('Not confirmed');
        }

        $this->status = DepositStatus::CREDITED;
        $this->recordDomainEvent( new DepositCredited($this->txId, $this->logIndex));
    }
}
