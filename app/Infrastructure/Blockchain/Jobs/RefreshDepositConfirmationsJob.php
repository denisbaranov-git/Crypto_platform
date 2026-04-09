<?php

namespace App\Infrastructure\Blockchain\Jobs;

use App\Application\Deposit\Commands\UpdateDepositConfirmationsCommand;
use App\Application\Deposit\Handlers\UpdateDepositConfirmationsHandler;
use App\Domain\Deposit\Repositories\DepositRepository;
use App\Infrastructure\Blockchain\BlockchainClientFactory;
use App\Infrastructure\Persistence\Eloquent\Repositories\NetworkScannerCursorRepository;
use App\Models\Network;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

final class RefreshDepositConfirmationsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(
        public int $networkId,
    ) {}

    public function handle(
        BlockchainClientFactory $clientFactory,
        NetworkScannerCursorRepository $cursors,
        DepositRepository $deposits,
        UpdateDepositConfirmationsHandler $updateHandler,
    ): void {
        $network = Network::query()->findOrFail($this->networkId);
        $cursor = $cursors->get($network->id);

        $networkConfig = config("deposit.scanner.networks.{$network->code}", []);
        $scanInterval = (int) ($networkConfig['scan_interval_seconds'] ?? 60);

        if ($cursor->scanned_at && $cursor->scanned_at->diffInSeconds(now()) < $scanInterval) {
            return;
        }

        $client = $clientFactory->forNetwork($network->id);
        $head = $client->headBlock();

        $openDeposits = $deposits->findOpenByNetwork($network->id, 500);

        foreach ($openDeposits as $deposit) {
            $blockNumber = $deposit->blockNumber()?->value();

            if ($blockNumber === null) {
                continue;
            }

            $confirmations = max(0, $head - $blockNumber + 1);

            $updateHandler->handle(new UpdateDepositConfirmationsCommand(
                networkId: $network->id,
                externalKey: $deposit->externalKey()->value(),
                currencyNetworkId: $deposit->currencyNetworkId(),
                amount: $deposit->amount(),
                confirmations: $confirmations,
                fromAddress: $deposit->fromAddress(),
                toAddress: $deposit->toAddress(),
                blockHash: $deposit->blockHash(),
                blockNumber: $blockNumber,
                finalized: null,
                metadata: $deposit->metadata(),
            ));
        }
    }
}
