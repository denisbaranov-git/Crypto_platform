<?php

namespace App\Infrastructure\Blockchain\Clients;

use App\Application\Deposit\DTO\DetectedBlockchainEvent;
use App\Infrastructure\Blockchain\Contracts\BlockchainClient;
use App\Infrastructure\Blockchain\Support\JsonRpcClient;

final class BitcoinClient implements BlockchainClient
{
    public function __construct(
        private readonly JsonRpcClient $rpc,
        private readonly int $networkId,
    ) {}

    public function headBlock(): int
    {
        return (int) $this->rpc->call('getblockcount');
    }

    public function blockHash(int $blockNumber): string
    {
        return (string) $this->rpc->call('getblockhash', [$blockNumber]);
    }

    public function scanBlock(int $blockNumber, array $tokenContracts = []): array
    {
        $events = [];
        $blockHash = $this->blockHash($blockNumber);
        $block = (array) $this->rpc->call('getblock', [$blockHash, 2]);

        foreach (($block['tx'] ?? []) as $tx) {
            $txid = (string) ($tx['txid'] ?? '');

            foreach (($tx['vout'] ?? []) as $vout) {
                $n = (int) ($vout['n'] ?? 0);
                $value = (string) ($vout['value'] ?? '0');

                $addresses = data_get($vout, 'scriptPubKey.addresses', []);
                if (! is_array($addresses)) {
                    continue;
                }

                foreach ($addresses as $address) {
                    $events[] = new DetectedBlockchainEvent(
                        networkId: $this->networkId,
                        txid: $txid,
                        externalKey: $txid . ':' . $n,
                        amount: $value,
                        toAddress: (string) $address,
                        fromAddress: null,
                        blockHash: $blockHash,
                        blockNumber: $blockNumber,
                        confirmations: 1,
                        assetType: 'native',
                        contractAddress: null,
                        metadata: [
                            'source' => 'bitcoin',
                            'kind' => 'native_utxo',
                            'vout' => $n,
                        ],
                    );
                }
            }
        }

        return $events;
    }
}
