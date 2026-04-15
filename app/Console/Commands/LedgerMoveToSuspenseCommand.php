<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Ledger\Contracts\LedgerService;
use Illuminate\Console\Command;

final class LedgerMoveToSuspenseCommand extends Command
{
    protected $signature = 'ledger:move-to-suspense
        {--user-id= : User ID}
        {--currency-network-id= : Currency network ID}
        {--amount= : Amount}
        {--reason=manual_adjustment : Reason}
        {--reference-id= : Reference ID}
        {--operation-id= : Idempotency key / operation id}';

    protected $description = 'Move user funds to suspense account (admin tool).';

    public function handle(LedgerService $ledger): int
    {
        $userId = (int) $this->option('user-id');
        $currencyNetworkId = (int) $this->option('currency-network-id');
        $amount = (string) $this->option('amount');
        $reason = (string) $this->option('reason');
        $referenceId = $this->option('reference-id') !== null ? (int) $this->option('reference-id') : null;
        $operationId = (string) ($this->option('operation-id') ?: 'suspense:' . $userId . ':' . $currencyNetworkId . ':' . $amount . ':' . now()->timestamp);

        $ledger->moveToSuspense(
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

        $this->info('Funds moved to suspense.');

        return self::SUCCESS;
    }
}
