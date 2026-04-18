<?php

declare(strict_types=1);

namespace App\Application\Withdrawal\Handlers;

use App\Application\Withdrawal\Commands\BroadcastWithdrawalCommand;
use App\Contracts\WithdrawalStrategy;
use App\Domain\Withdrawal\Entities\WithdrawalAttempt;
use App\Domain\Withdrawal\Repositories\WithdrawalAttemptRepository;
use App\Domain\Withdrawal\Repositories\WithdrawalRepository;
use App\Domain\Withdrawal\Services\WithdrawalRoutingService;
use App\Infrastructure\Blockchain\BlockchainClientFactory;
use App\Infrastructure\Ledger\Services\EloquentLedgerService;
use DomainException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * CHANGED:
 * - no outbox bridge between internal steps;
 * - broadcast happens after DB commit of "broadcasting" state;
 * - consumeHold is called immediately after successful broadcast;
 * - if consume fails, withdrawal stays broadcasted and recovery job fixes it.
 */
final class BroadcastWithdrawalHandler
{
    public function __construct(
        private readonly WithdrawalRepository $withdrawals,
        private readonly WithdrawalAttemptRepository $attempts,
        private readonly WithdrawalRoutingService $routing,
        private readonly EloquentLedgerService $ledger,
        private readonly BlockchainClientFactory $clientFactory,
        /** @var iterable<WithdrawalStrategy> */
        private readonly iterable $strategies,
    ) {}

    public function handle(BroadcastWithdrawalCommand $command): void
    {
        $withdrawal = null;
        $attempt = null;
        $strategy = null;
        $systemWalletId = null;

        DB::transaction(function () use ($command, &$withdrawal, &$attempt, &$strategy, &$systemWalletId): void {
            $withdrawal = $this->withdrawals->lockById($command->withdrawalId);

            if (! $withdrawal) {
                throw new DomainException('Withdrawal not found.');
            }

            if (in_array($withdrawal->status(), ['cancelled', 'failed', 'settled', 'confirmed'], true)) {
                return;
            }

            if ($withdrawal->txid() !== null && in_array($withdrawal->status(), ['broadcasted', 'settled', 'confirmed'], true)) { // -> ||
                return;
            }

            $strategy = $this->resolveStrategy($withdrawal); ///?????????????????

            $client = $this->clientFactory->forNetwork($withdrawal->networkId());
            $client->send($withdrawal);
            /*
            $privateKey = $user->getNetworkKey($network);

            if (!$privateKey) {
                throw new RuntimeException('Private key not found');
            }
            $config = $this->tokenConfigService->getTokenNetworkConfig($currency, $network );

            if ($wallet->currency === $config['native_currency']) {
                $txid = $client->sendNative(
                    $privateKey,
                    $toAddress,
                    $transaction->amount // строка
                );
            } else {
                if (!$config) {
                    throw new RuntimeException(
                        "Token configuration not found for {$currency} on {$network}"
                    );
                }

                $txid = $client->sendToken(
                    $privateKey,
                    $toAddress,
                    $transaction->amount,
                    $config['contract'],
                    $config['decimals']
                );
            }
             * */

            $systemWalletId = $this->routing->selectSystemWallet($withdrawal);

            $attemptNo = $this->attempts->nextAttemptNo($withdrawal->id()->value());

            $attempt = WithdrawalAttempt::start(
                withdrawalId: $withdrawal->id()->value(),
                attemptNo: $attemptNo,
                broadcastDriver: $strategy->driver(),
                requestPayload: array_merge($command->metadata, [
                    'withdrawal_id' => $withdrawal->id()->value(),
                    'system_wallet_id' => $systemWalletId,
                ])
            );

            $this->attempts->save($attempt);

            $withdrawal->markBroadcastPending();
            $this->withdrawals->save($withdrawal);
        });

        if (! $withdrawal || ! $strategy || ! $attempt || $systemWalletId === null) {
            return;
        }

        try {
            $txid = $strategy->broadcast($withdrawal, array_merge($command->metadata, [ ////!!!!!!!!!!!!!!!!!!
                'withdrawal_id' => $withdrawal->id()->value(),
                'attempt_no' => $attempt->attemptNo(),
                'system_wallet_id' => $systemWalletId,
            ]));

            //DB::transaction(function () use ($withdrawal, $attempt, $txid, $systemWalletId): void {
                $attempt->markBroadcasted($txid->value(), [
                    'system_wallet_id' => $systemWalletId,
                ]);
                $this->attempts->save($attempt);

                $withdrawal->markBroadcasted($txid, $systemWalletId);
                $this->withdrawals->save($withdrawal);
            //});
/////////////////////////////////////////////////////////////////////////////////////////////////////
            $consumeOperationId = 'withdrawal:' . $withdrawal->id()->value() . ':consume';

            $this->ledger->consumeHold( //denis   TO EARLY!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
                holdId: $withdrawal->ledgerHoldId(),
                operationId: $consumeOperationId,
                referenceType: 'withdrawal',
                referenceId: $withdrawal->id()->value(),
                metadata: array_merge($command->metadata, [
                    'txid' => $txid->value(),
                ])
            );

            //DB::transaction(function () use ($withdrawal, $consumeOperationId): void {
                $withdrawal->markSettled($consumeOperationId); //
                $this->withdrawals->save($withdrawal);
            //});
        } catch (Throwable $e) {
            DB::transaction(function () use ($withdrawal, $e): void {
                $withdrawal->recordLastError($e->getMessage());
                $this->withdrawals->save($withdrawal);
            });

            throw $e;
        }
    }

    private function resolveStrategy($withdrawal): WithdrawalStrategy
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->supports($withdrawal)) {
                return $strategy;
            }
        }

        throw new DomainException('No withdrawal strategy available for this network.');
    }
}
