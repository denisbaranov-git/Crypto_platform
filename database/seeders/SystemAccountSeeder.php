<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Infrastructure\Persistence\Eloquent\Models\EloquentSystemAccount;
use Illuminate\Database\Seeder;

final class SystemAccountSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            [
                'code' => 'clearing',
                'name' => 'Clearing Account',
                'purpose' => 'Main accounting clearing account for user deposits/withdrawals settlement.',
            ],
            [
                'code' => 'fee_income',
                'name' => 'Fee Income Account',
                'purpose' => 'Platform fee income ledger account.',
            ],
            [
                'code' => 'treasury',
                'name' => 'Treasury Account',
                'purpose' => 'Platform treasury / retained funds.',
            ],
            [
                'code' => 'suspense',
                'name' => 'Suspense Account',
                'purpose' => 'Temporary holding account for unresolved reconciliation cases.',
            ],
            [
                'code' => 'hot_wallet',
                'name' => 'Hot Wallet Mirror Account',
                'purpose' => 'Ledger mirror of hot wallet balance.',
            ],
        ];

        foreach ($items as $item) {
            EloquentSystemAccount::query()->updateOrCreate(
                ['code' => $item['code']],
                [
                    'name' => $item['name'],
                    'purpose' => $item['purpose'],
                    'is_active' => true,
                    'metadata' => [],
                ]
            );
        }
    }
}
