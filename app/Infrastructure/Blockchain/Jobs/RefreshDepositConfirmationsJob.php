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
//config/deposit.php
// return [
//    'scanner' => [
//        'default_safety_margin_blocks' => 12,
//        'networks' => [
//            'ethereum' => [
//                'scan_interval_seconds' => 6,
//                'safety_margin_blocks'   => 12,
//                'reorg_window_blocks'    => 50,
//            ],
//            'tron' => [
//                'scan_interval_seconds' => 5,
//                'safety_margin_blocks'   => 20,
//                'reorg_window_blocks'    => 50,
//            ],
//            'bitcoin' => [
//                'scan_interval_seconds' => 30,
//                'safety_margin_blocks'   => 6,
//                'reorg_window_blocks'    => 100,
//            ],
//        ],
//    ],
//        'confirmations' => [
//            'default_blocks' => 12,
//        ],
//    'evm' => [
//            'transfer_signature' => '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef',
//        ],
//];
        $networkConfig = config("deposit.scanner.networks.{$network->code}", []); //denis //move to DB
        $scanInterval = (int) ($networkConfig['scan_interval_seconds'] ?? 60);

        if ($cursor->scanned_at && $cursor->scanned_at->diffInSeconds(now()) < $scanInterval) {
            return;
        }

        $client = $clientFactory->forNetwork($network->id);
        $head = $client->headBlock();
//        public function findOpenByNetwork(int $networkId, int $limit = 500): array
//        {
//            $rows = EloquentDeposit::query()
//                ->where('network_id', $networkId)
//                ->whereIn('status', ['detected', 'pending', 'confirmed'])
//                ->orderBy('id')
//                ->limit($limit)
//                ->get();
//
//            return $rows->map(fn (EloquentDeposit $row) => $this->mapper->toEntity($row))->all();
//        }
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
