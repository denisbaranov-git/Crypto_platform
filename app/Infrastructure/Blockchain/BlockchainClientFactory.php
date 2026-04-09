<?php

namespace App\Infrastructure\Blockchain;

use App\Infrastructure\Blockchain\Clients\BitcoinClient;
use App\Infrastructure\Blockchain\Clients\EvmClient;
use App\Infrastructure\Blockchain\Clients\TronClient;
use App\Infrastructure\Blockchain\Contracts\BlockchainClient;
use App\Infrastructure\Blockchain\Support\JsonRpcClient;
use App\Models\Network;

final class BlockchainClientFactory
{
    public function forNetwork(int $networkId): BlockchainClient
    {
        $network = Network::query()->findOrFail($networkId);

        return match ($network->rpc_driver) {
            'evm' => new EvmClient(
                rpc: new JsonRpcClient($network->rpc_url, null),
                networkId: $networkId
            ),
            'tron' => new TronClient(
                rpcUrl: $network->rpc_url,
                networkId: $networkId
            ),
            'bitcoin' => new BitcoinClient(
                rpc: new JsonRpcClient($network->rpc_url, null),
                networkId: $networkId
            ),
            default => throw new \InvalidArgumentException("Unsupported rpc_driver: {$network->rpc_driver}"),
        };
    }
}
