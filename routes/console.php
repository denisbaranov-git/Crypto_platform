<?php

use App\Infrastructure\Blockchain\Jobs\RefreshDepositConfirmationsJob;
use App\Infrastructure\Blockchain\Jobs\ScanNetworkBlocksJob;
use App\Infrastructure\Outbox\Jobs\OutboxRelayJob;
use App\Models\Network;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    $networks = Network::query()
        ->where('is_testnet', false)
        ->get();

    foreach ($networks as $network) {
        dispatch(new ScanNetworkBlocksJob($network->id))->onQueue('deposits');
        dispatch(new RefreshDepositConfirmationsJob($network->id))->onQueue('deposits');
    }

    dispatch(new OutboxRelayJob(batchSize: 100))->onQueue('outbox');
})
    ->everyMinute()
    ->name('process-deposits-and-outbox')
    ->withoutOverlapping();

//Schedule::job(new ScanNetworkBlocksJob('ethereum'))->everyMinute()->withoutOverlapping();
//Schedule::job(new ScanNetworkBlocksJob('tron'))->everyMinute()->withoutOverlapping();
//Schedule::job(new ScanNetworkBlocksJob('bitcoin'))->everyTwoMinutes()->withoutOverlapping();
//
//Schedule::job(new RefreshDepositConfirmationsJob())->everyMinute()->withoutOverlapping();
//Schedule::job(new OutboxRelayJob())->everyMinute()->withoutOverlapping();
