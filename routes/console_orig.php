<?php

use App\Infrastructure\Blockchain\Jobs\RefreshDepositConfirmationsJob;
use App\Infrastructure\Blockchain\Jobs\ScanNetworkBlocksJob;
use App\Infrastructure\Outbox\Jobs\OutboxRelayJob;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentNetwork;
use App\Infrastructure\Withdrawal\Jobs\ConfirmWithdrawalJob;
use App\Infrastructure\Withdrawal\Jobs\ExpireWithdrawalHoldsJob;
use App\Infrastructure\Withdrawal\Jobs\ReconcileStuckWithdrawalsJob;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function (): void {
    $networkIds = EloquentNetwork::query()
        ->where('is_testnet', env('BLOCKCHAIN_TESTNET_MODE')) // if test
        ->pluck('id');

    foreach ($networkIds as $networkId) {
        //deposit
        dispatch(new ScanNetworkBlocksJob($networkId))->onQueue('deposits');
        dispatch(new RefreshDepositConfirmationsJob($networkId))->onQueue('deposits');
        //withdrawal
        dispatch(new ConfirmWithdrawalJob((int) $networkId))->onQueue('withdrawals');
    }
    dispatch(new OutboxRelayJob(batchSize: 100))->onQueue('outbox');
})
    ->everyMinute()// через 1min
    ->name('process-deposits-withdrawal-and-outbox')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::call(function (): void {
    $networkIds = EloquentNetwork::query()
        //->where('is_testnet', false) // if test
        ->pluck('id');
    foreach ($networkIds as $networkId) {
        //withdrawal
        dispatch(new ReconcileStuckWithdrawalsJob((int) $networkId))->onQueue('withdrawals');
    }
})
    ->everyFiveMinutes()// через 5min
    ->name('reconcile-stuck-withdrawals')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::job(new ExpireWithdrawalHoldsJob())
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();

//Schedule::job(new ReconcileStuckWithdrawalsJob(networkId: 0))
//    ->everyMinute()
//    ->withoutOverlapping()
//    ->onOneServer()
//    ->when(fn () => true);

//ExpireWithdrawalHoldsJob должен быть частым.
//ConfirmWithdrawalJob должен быть частым.
//ReconcileStuckWithdrawalsJob тоже должен быть частым, потому что это repair loop.

//Schedule::job(new ScanNetworkBlocksJob('ethereum'))->everyMinute()->withoutOverlapping();
//Schedule::job(new ScanNetworkBlocksJob('tron'))->everyMinute()->withoutOverlapping();
//Schedule::job(new ScanNetworkBlocksJob('bitcoin'))->everyTwoMinutes()->withoutOverlapping();
//
//Schedule::job(new RefreshDepositConfirmationsJob())->everyMinute()->withoutOverlapping();
//Schedule::job(new OutboxRelayJob())->everyMinute()->withoutOverlapping();
