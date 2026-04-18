<?php

namespace App\Application\Deposit\Handlers;

use App\Application\Deposit\Commands\RegisterDetectedDepositCommand;
use App\Domain\Deposit\Entities\Deposit;
use App\Domain\Deposit\Exceptions\DuplicateDeposit;
use App\Domain\Deposit\Repositories\DepositRepository;
use App\Domain\Shared\ValueObjects\BlockNumber;
use App\Domain\Shared\ValueObjects\ExternalKey;
//use App\Domain\Shared\ValueObjects\TransactionHash;
use App\Domain\Shared\ValueObjects\TxId;
use Illuminate\Support\Facades\DB;

final class RegisterDetectedDepositHandler
{
    public function __construct(
        private readonly DepositRepository $deposits,
        //private readonly OutboxRepository $outbox,
    ) {}

    public function handle(RegisterDetectedDepositCommand $command): Deposit
    {
        return DB::transaction(function () use ($command): Deposit {
            $externalKey = new ExternalKey($command->externalKey);

            if ($this->deposits->existsByExternalKey($command->networkId, $externalKey)) {
                throw new DuplicateDeposit((string) $command->networkId, $command->externalKey);
            }

            $deposit = Deposit::detect(
                userId: $command->userId,
                networkId: $command->networkId,
                currencyNetworkId: $command->currencyNetworkId,
                walletAddressId: $command->walletAddressId,
                externalKey: $externalKey,
                txid: new TxId($command->txid),
                amount: $command->amount,
                toAddress: $command->toAddress,
                fromAddress: $command->fromAddress,
                blockHash: $command->blockHash,
                blockNumber: $command->blockNumber !== null ? new BlockNumber($command->blockNumber) : null,
                confirmations: $command->confirmations,
                metadata: $command->metadata,
//                metadata: array_merge($command->metadata, [
//                    'asset_type' => $command->assetType,
//                    'contract_address' => $command->contractAddress,
//                ]),
            );

            $deposit = $this->deposits->save($deposit);

//            foreach ($deposit->pullDomainEvents() as $event) {
////                $this->outbox->append(OutboxMessage::fromDomainEvent(
////                    aggregateType: 'deposit',
////                    aggregateId: $deposit->id()->value(),
////                    event: $event, //payload: json_decode(json_encode($event, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR), // convert to Array. true - is array
////                    idempotencyKey: 'deposit:' . $deposit->id()->value() . ':' . $event::class,
////                ));
//
//                $this->outbox->append(//OutboxMessage::fromDomainEvent - need back refactor!!!!!
//                    idempotencyKey: 'deposit:' . $deposit->id()->value() . ':' . $event::class,
//                    aggregateType: 'deposit',
//                    aggregateId: (string)$deposit->id()->value(),
//                    eventType: DepositDetected::class,
//                    payload: [  //payload: json_decode(json_encode($event, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR), // convert to Array. true - is array
//                        'depositId' => $deposit->id()->value(),
//                        'networkId' => $deposit->networkId(),
//                        'externalKey' => $deposit->externalKey()->value(),
//                        'txid' => $deposit->txid()->value(),
//                        'amount' => $deposit->amount(),
//                    ]
//                );
//            }

            return $deposit;
        });
    }
}
