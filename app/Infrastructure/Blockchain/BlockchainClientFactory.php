<?php

namespace App\Infrastructure\Blockchain;

use App\Domain\Identity\Exceptions\DomainException;
use App\Infrastructure\Blockchain\Clients\BitcoinClient;
use App\Infrastructure\Blockchain\Clients\EvmClient;
use App\Infrastructure\Blockchain\Clients\TronClient;
use App\Infrastructure\Blockchain\Contracts\BlockchainClient;
use App\Infrastructure\Blockchain\Services\SystemWalletSecretResolver;
use App\Infrastructure\Blockchain\Support\JsonRpcClient;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentNetwork;

final class BlockchainClientFactory
{
    public function __construct(
        private readonly SystemWalletSecretResolver $secrets,
    ) {}
    public function forNetwork(int $networkId): BlockchainClient
    {
        $network = EloquentNetwork::query()->findOrFail($networkId);

        return match ($network->rpc_driver) {
            'evm' => new EvmClient(
                rpc: new JsonRpcClient($network->rpc_url, null),
                networkId: $networkId,
                secrets: $this->secrets,
            ),
            'tron' => new TronClient(
                rpcUrl: $network->rpc_url,
                networkId: $networkId,
                secrets: $this->secrets,
            ),
            'bitcoin' => new BitcoinClient(
                rpc: new JsonRpcClient($network->rpc_url, null),
                networkId: $networkId,
                secrets: $this->secrets,
            ),
            default => throw new DomainException("Unsupported rpc_driver: {$network->rpc_driver}"),
        };
    }
}
