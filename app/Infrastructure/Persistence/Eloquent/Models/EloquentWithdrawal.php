<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

final class EloquentWithdrawal extends Model
{
    protected $table = 'withdrawals';

    protected $fillable = [
        'user_id',
        'network_id',
        'currency_network_id',
        'destination_address',
        'destination_tag',
        'amount',
        'fee_amount',
        'network_fee_estimated_amount',
        'network_fee_actual_amount',
        'network_fee_currency_network_id',
        'total_debit_amount',
        'fee_rule_id',
        'fee_snapshot',
        'ledger_hold_id',
        'reserve_operation_id',
        'consume_operation_id',
        'release_operation_id',
        'reversal_operation_id',
        'system_wallet_id',
        'txid',
        'broadcast_attempts',
        'status',
        'requested_at',
        'reserved_at',
        'broadcasted_at',
        'settled_at',
        'confirmed_at',
        'cancelled_at',
        'failed_at',
        'released_at',
        'failure_reason',
        'cancellation_reason',
        'rejection_reason',
        'last_error',
//        'confirmed_block_number',
//        'confirmed_block_hash',
//        'confirmed_confirmations',
        'confirmed_block_number',
        'confirmed_block_hash',
        'confirmed_confirmations',
        'reorged_at',
        'reversed_at',
        'reorg_reason',
        'reversal_reason',
        'reorg_block_number',
        'reversal_attempts',
        'reversal_last_error',
        'reversal_failed_at',
        'network_fee_posted_at',
        'network_fee_operation_id',
        'idempotency_key',
        'version',
        'metadata',
    ];

    protected $casts = [
        'fee_snapshot' => 'array',
        'metadata' => 'array',
        'broadcast_attempts' => 'integer',
        'reversal_attempts' => 'integer',
        'version' => 'integer',
        'confirmed_block_number' => 'integer',
        'confirmed_confirmations' => 'integer',
        'reorg_block_number' => 'integer',
        'network_fee_currency_network_id' => 'integer',
        'fee_rule_id' => 'integer',
        'ledger_hold_id' => 'integer',
        'network_id' => 'integer',
        'currency_network_id' => 'integer',
        'system_wallet_id' => 'integer',
    ];
}
