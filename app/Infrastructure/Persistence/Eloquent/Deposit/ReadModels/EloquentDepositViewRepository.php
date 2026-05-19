<?php

namespace App\Infrastructure\Persistence\Eloquent\Deposit\ReadModels;

use App\Application\Deposit\Queries\DTO\DepositDetailsDTO;
use App\Application\Deposit\Queries\DTO\DepositListItemDTO;
use App\Application\Deposit\Queries\DTO\PaginatedDepositsDTO;
use App\Application\Deposit\ReadModels\DepositViewRepository;
use App\Models\Deposit;

final class EloquentDepositViewRepository implements DepositViewRepository
{
    private array $testData; //delete it test data

    public function __construct(){
        $this->testData = [
            [
                'id' => 1,
                'status' => 'credited',
                'fromAddress' => '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb1',
                'amount' => '1.50000000',
                'confirmations' => 12,
                'requiredConfirmations' => 12,
                'currency' => 'ETH',
                'network' => 'ethereum',
                'txid' => '0x1a2b3c4d5e6f7890abcdef1234567890abcdef1234567890abcdef1234567890',
                'createdAt' => '2024-01-15T10:30:00.000000Z',
            ],
            [
                'id' => 2,
                'status' => 'confirmed',
                'fromAddress' => '0x8Ba1f109551bD432803012645Ac136ddd64DBA72',
                'amount' => '0.05000000',
                'confirmations' => 12,
                'requiredConfirmations' => 12,
                'currency' => 'BTC',
                'network' => 'bitcoin',
                'txid' => '1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa',
                'createdAt' => '2024-01-15T09:45:00.000000Z',
            ],
            [
                'id' => 3,
                'status' => 'pending',
                'fromAddress' => 'T9yD14Nj9j7xAB4dbGeiX9h8unkKHxuWwb',
                'amount' => '5000.00000000',
                'confirmations' => 5,
                'requiredConfirmations' => 12,
                'currency' => 'USDT',
                'network' => 'tron',
                'txid' => 'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2',
                'createdAt' => '2024-01-15T11:00:00.000000Z',
            ],
            [
                'id' => 4,
                'status' => 'detected',
                'fromAddress' => '0x95aD61b0a150d79219dCF64E1E6Cc01f0B64C4cE',
                'amount' => '100.00000000',
                'confirmations' => 1,
                'requiredConfirmations' => 12,
                'currency' => 'USDC',
                'network' => 'arbitrum',
                'txid' => '0x2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d7e8f9a0b1c2d3e4f5a6b7c8d9e0f1a2b3c',
                'createdAt' => '2024-01-15T12:15:00.000000Z',
            ],
            [
                'id' => 5,
                'status' => 'pending',
                'fromAddress' => '0x3C44CdDdB6a900fa2b585dd299e03d12FA4293BC',
                'amount' => '2500.00000000',
                'confirmations' => 3,
                'requiredConfirmations' => 12,
                'currency' => 'USDT',
                'network' => 'bsc',
                'txid' => '0xc3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2c3d4',
                'createdAt' => '2024-01-15T11:30:00.000000Z',
            ],
            [
                'id' => 6,
                'status' => 'confirmed',
                'fromAddress' => '0x7a250d5630B4cF539739dF2C5dAcb4c659F2488D',
                'amount' => '0.50000000',
                'confirmations' => 12,
                'requiredConfirmations' => 12,
                'currency' => 'ETH',
                'network' => 'arbitrum',
                'txid' => '0xd4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2c3d4e5',
                'createdAt' => '2024-01-15T10:00:00.000000Z',
            ],
            [
                'id' => 7,
                'status' => 'pending',
                'fromAddress' => 'bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq',
                'amount' => '0.10000000',
                'confirmations' => 2,
                'requiredConfirmations' => 12,
                'currency' => 'BTC',
                'network' => 'bitcoin',
                'txid' => '3J98t1WpEZ73CNmQviecrnyiWrnqRhWNLy',
                'createdAt' => '2024-01-15T12:00:00.000000Z',
            ],
            [
                'id' => 8,
                'status' => 'credited',
                'fromAddress' => '0xb794f5ea0ba39494ce839613fffba74279579268',
                'amount' => '10000.00000000',
                'confirmations' => 12,
                'requiredConfirmations' => 12,
                'currency' => 'MATIC',
                'network' => 'polygon',
                'txid' => '0xe5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2c3d4e5f6',
                'createdAt' => '2024-01-15T09:00:00.000000Z',
            ],
            [
                'id' => 9,
                'status' => 'pending',
                'fromAddress' => '0x2170Ed0880ac9A755fd29B2688956BD959F933F8',
                'amount' => '10.00000000',
                'confirmations' => 8,
                'requiredConfirmations' => 12,
                'currency' => 'BNB',
                'network' => 'bsc',
                'txid' => '0xf6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2c3d4e5f6a7',
                'createdAt' => '2024-01-15T11:45:00.000000Z',
            ],
            [
                'id' => 10,
                'status' => 'detected',
                'fromAddress' => '0xdAC17F958D2ee523a2206206994597C13D831ec7',
                'amount' => '500.00000000',
                'confirmations' => 1,
                'requiredConfirmations' => 12,
                'currency' => 'USDT',
                'network' => 'ethereum',
                'txid' => '0xa7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2c3d4e5f6a7b8',
                'createdAt' => '2024-01-15T12:30:00.000000Z',
            ],
        ];
    } //testData - only test dev- delete it!!!
    public function getUserDeposits(int $userId,?string $status,?string $currency,?string $network,int $page,int $perPage): PaginatedDepositsDTO
    {
        $query = Deposit::query()
            ->with(['currency', 'network'])
            ->where('user_id', $userId)
            ->latest('id');

        if ($status) {
            $query->where('status', $status);
        }

        if ($currency) {
            $query->whereHas('currency', function ($q) use ($currency) {
                $q->where('code', strtoupper($currency));
            });
        }

        if ($network) {
            $query->whereHas('network', function ($q) use ($network) {
                $q->where('code', strtolower($network));
            });
        }

        $result = $query->paginate(
            perPage: $perPage,
            page: $page
        );
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
        $lastPage = ceil(count($this->testData)/$perPage);
        $total = count($this->testData);
        $firstIndex = $perPage * $page - $perPage;
        $LastIndex = min($perPage * $page, $total);

        $testResult = array_slice($this->testData, $firstIndex, $LastIndex);

        return new PaginatedDepositsDTO(
            items: array_map( fn($deposit) => new DepositListItemDTO(
                id: $deposit['id'],
                fromAddress: $deposit['fromAddress'],
                status: $deposit['status'],
                amount: $deposit['amount'],
                confirmations: $deposit['confirmations'],
                requiredConfirmations: $deposit['requiredConfirmations'],
                currency: $deposit['currency'],
                network: $deposit['network'],
                txid: $deposit['txid'],
                createdAt: $deposit['createdAt'],
            ), $testResult),
            currentPage: $page,
            lastPage: $lastPage,
            perPage: $perPage,
            total: $total,
        );

//////////////////////////////////////////////////////////////////////////
//        return new PaginatedDepositsDTO(
//            items: array_map(
//                fn ($deposit) => new DepositListItemDTO(
//                    id: $deposit->id,
//                    status: $deposit->status,
//                    amount: $deposit->amount,
//                    confirmations: $deposit->confirmations,
//                    requiredConfirmations: $deposit->required_confirmations,
//                    currency: $deposit->currency->code,
//                    network: $deposit->network->code,
//                    txid: $deposit->txid,
//                    createdAt: $deposit->created_at->toISOString(),
//                ),
//                $result->items()
//            ),
//            currentPage: $result->currentPage(),
//            lastPage: $result->lastPage(),
//            perPage: $result->perPage(),
//            total: $result->total(),
//        );
    }

    public function getDepositDetails(
        int $userId,
        int $depositId
    ): ?DepositDetailsDTO {
        $deposit = Deposit::query()
            ->with(['currency','network','walletAddress',])
            ->where('user_id', $userId)
            ->find($depositId);

//only test dev - delete!!!
$deposit_test = $this->testData[$depositId];
return new DepositDetailsDTO(
    id: $deposit_test['id'],
    status: $deposit_test['status'],
    amount: $deposit_test['amount'],
    confirmations: $deposit_test['confirmations'],
    requiredConfirmations: $deposit_test['requiredConfirmations'],
    txid: $deposit_test['txid'],
    fromAddress: $deposit_test['fromAddress'],
    toAddress: 'toAddress',
    blockHash: 'blockHash',
    blockNumber: 0,
    currency: $deposit_test['currency'],
    network: $deposit_test['network'],
    walletAddress: 'walletAddress',
    creditedAt: 'creditedAt',
    createdAt: 'createdAt',
    explorerUrl: 'explorerUrl',
);


//        if (!$deposit) {
//            return null;
//        }
//
//        return new DepositDetailsDTO(
//            id: $deposit->id,
//            status: $deposit->status,
//            amount: $deposit->amount,
//            confirmations: $deposit->confirmations,
//            requiredConfirmations: $deposit->required_confirmations,
//            txid: $deposit->txid,
//            fromAddress: $deposit->from_address,
//            toAddress: $deposit->to_address,
//            blockHash: $deposit->block_hash,
//            blockNumber: $deposit->block_number,
//            currency: $deposit->currency->code,
//            network: $deposit->network->code,
//            walletAddress: $deposit->walletAddress->address,
//            creditedAt: optional($deposit->credited_at)?->toISOString(),
//            createdAt: $deposit->created_at->toISOString(),
//            explorerUrl: $this->buildExplorerUrl($deposit),
//        );
    }

    private function buildExplorerUrl(Deposit $deposit): ?string
    {
        if (!$deposit->network?->explorer_url) {
            return null;
        }

        return rtrim($deposit->network->explorer_url, '/')
            . '/tx/'
            . $deposit->txid;
    }
}
