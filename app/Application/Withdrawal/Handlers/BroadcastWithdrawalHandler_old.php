<?php

declare(strict_types=1);

namespace App\Application\Withdrawal\Handlers;

use App\Application\Withdrawal\Commands\BroadcastWithdrawalCommand;
use App\Domain\Ledger\Repositories\LedgerHoldRepository;
use App\Domain\Shared\ValueObjects\TxId;
use App\Domain\Withdrawal\Entities\WithdrawalAttempt;
use App\Domain\Withdrawal\Repositories\WithdrawalAttemptRepository;
use App\Domain\Withdrawal\Repositories\WithdrawalRepository;
use App\Infrastructure\Blockchain\BlockchainClientFactory;
use App\Infrastructure\Blockchain\Services\SystemWalletNonceService;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentNetwork;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentSystemWallet;
use App\Infrastructure\Ledger\Services\EloquentLedgerService;
use DomainException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * BroadcastWithdrawalHandler: hot wallet signing and sending
 *
 * system hot wallet выбирается из system_wallets
 * client prepares signed raw transaction using that wallet
 * raw tx is stored in withdrawal_attempts
 * raw tx is broadcast
 * txid is saved
 * then consumeHold() is called
 *
 *
 *  CHANGED:
 *  - chain client does prepare + broadcast;
 *  - hot wallet is selected from system_wallets table;
 *  - raw tx is persisted to prevent replay/different-tx retry mistakes.
 */
final class BroadcastWithdrawalHandler_old
{
    public function __construct(
        private readonly WithdrawalRepository $withdrawals,
        private readonly WithdrawalAttemptRepository $attempts,
        private readonly SystemWalletNonceService $nonceService,
        private readonly BlockchainClientFactory $clientFactory,
        private readonly EloquentLedgerService $ledger,
    ) {}

    public function handle(BroadcastWithdrawalCommand $command): void
    {
        $withdrawal = null;
        $attempt = null;
        $client = null;
        $systemWallet = null;

        DB::transaction(function () use ($command, &$withdrawal, &$attempt, &$client, &$systemWallet): void {
            $withdrawal = $this->withdrawals->lockById($command->withdrawalId);

            if (! $withdrawal) {
                throw new DomainException('Withdrawal not found.');
            }

            if (in_array($withdrawal->status(), [
                'cancelled', 'failed', 'released', 'reorged', 'reversed', 'confirmed',
            ], true)) {
                return;
            }

            if ($withdrawal->txid() !== null && in_array($withdrawal->status(), ['broadcasted', 'settled'], true)) {
                return;
            }

            $network = EloquentNetwork::query()->findOrFail($withdrawal->networkId());
            $client = $this->clientFactory->forNetwork($network->id);

            $systemWallet = EloquentSystemWallet::query()
                ->where('network_id', $network->id)
                ->where('type', 'hot')
                ->where('status', 'active')
                ->orderBy('id')
                ->first();

            if (! $systemWallet) {
                throw new DomainException('Active hot system wallet not found for this network.');
            }

            $latestAttempt = $this->attempts->latestForWithdrawal($withdrawal->id()->value());

            if ($latestAttempt && in_array($latestAttempt->status(), ['broadcasting', 'broadcasted'], true)) {
                $attempt = $latestAttempt;
            } else {
                $attemptNo = $this->attempts->nextAttemptNo($withdrawal->id()->value());
                $attempt = WithdrawalAttempt::start(
                    withdrawalId: $withdrawal->id()->value(),
                    attemptNo: $attemptNo,
                    broadcastDriver: $network->rpc_driver,
                    requestPayload: array_merge($command->metadata, [
                        'withdrawal_id' => $withdrawal->id()->value(),
                    ])
                );
            }

            if ($attempt->rawTransaction() === null) {

                $nonce = $this->nonceService->reserveNextNonce($network->id, $systemWallet->id);

                $prepared = $client->prepareWithdrawal(
                    withdrawal: $withdrawal,
                    systemWalletId: (int) $systemWallet->id,
                    context: array_merge($command->metadata, [
                        'nonce' => $nonce,
                    ])
                );

//                $prepared = $client->prepareWithdrawal(
//                    withdrawal: $withdrawal,
//                    systemWalletId: (int) $systemWallet->id,
//                    context: $command->metadata
//                );

                $attempt->storePreparedTransaction(
                    fingerprint: $prepared->fingerprint,
                    rawTransactionHash: $prepared->rawTransactionHash,
                    rawTransaction: $prepared->rawTransaction
                );

                $this->attempts->save($attempt);
            }

            $withdrawal->markBroadcastPending();
            $this->withdrawals->save($withdrawal);
        });

        if (! $withdrawal || ! $attempt || ! $client || ! $systemWallet) {
            return;
        }

        try {
            $rawTx = $attempt->rawTransaction();

            if ($rawTx === null) {
                throw new DomainException('Prepared raw transaction missing.');
            }

            $txid = $client->broadcastWithdrawalRaw($rawTx);

            DB::transaction(function () use ($withdrawal, $attempt, $txid, $systemWallet): void {
                $attempt->markBroadcasted($txid, [
                    'system_wallet_id' => $systemWallet->id,
                ]);
                $this->attempts->save($attempt);

                $withdrawal->markBroadcasted(new TxId($txid), (int) $systemWallet->id);
                $this->withdrawals->save($withdrawal);
            });

            $consumeOperationId = 'withdrawal:' . $withdrawal->id()->value() . ':consume';

            $this->ledger->consumeHold( // списывает hold через double-entry
                holdId: $withdrawal->ledgerHoldId(),
                operationId: $consumeOperationId,
                referenceType: 'withdrawal',
                referenceId: $withdrawal->id()->value(),
                metadata: array_merge($command->metadata, [
                    'txid' => $txid,
                ])
            );

            //DB::transaction(function () use ($withdrawal, $consumeOperationId): void {
                $withdrawal->markSettled($consumeOperationId);
                $this->withdrawals->save($withdrawal);
            //});
        } catch (Throwable $e) {
            //DB::transaction(function () use ($withdrawal, $e): void {
                $withdrawal->recordLastError($e->getMessage());
                $this->withdrawals->save($withdrawal);
            //});

            throw $e;
        }
    }
}
