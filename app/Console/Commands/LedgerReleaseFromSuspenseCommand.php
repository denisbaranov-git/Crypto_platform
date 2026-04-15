<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Ledger\Contracts\LedgerService;
use Illuminate\Console\Command;

final class LedgerReleaseFromSuspenseCommand extends Command
{
    protected $signature = 'ledger:release-from-suspense
        {--user-id= : User ID}
        {--currency-network-id= : Currency network ID}
        {--amount= : Amount}
        {--reason=manual_release : Reason}
        {--reference-id= : Reference ID}
        {--operation-id= : Idempotency key / operation id}';

    protected $description = 'Release user funds from suspense account (admin tool).';

    public function handle(LedgerService $ledger): int
    {
        $userId = (int) $this->option('user-id');
        $currencyNetworkId = (int) $this->option('currency-network-id');
        $amount = (string) $this->option('amount');
        $reason = (string) $this->option('reason');
        $referenceId = $this->option('reference-id') !== null ? (int) $this->option('reference-id') : null;
        $operationId = (string) ($this->option('operation-id') ?: 'release-suspense:' . $userId . ':' . $currencyNetworkId . ':' . $amount . ':' . now()->timestamp);

        $ledger->releaseFromSuspense(
            userId: $userId,
            currencyNetworkId: $currencyNetworkId,
            amount: $amount,
            operationId: $operationId,
            reason: $reason,
            referenceId: $referenceId,
            metadata: [
                'source' => 'artisan',
                'reason' => $reason,
            ]
        );

        $this->info('Funds released from suspense.');

        return self::SUCCESS;
    }
}
