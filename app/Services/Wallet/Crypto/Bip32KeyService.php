<?php

declare(strict_types=1);

namespace App\Services\Wallet\Crypto;

use App\Services\Wallet\Crypto\Contracts\Bip32KeyServiceInterface;
use BitWasp\Bitcoin\Key\Factory\HierarchicalKeyFactory;
use BitWasp\Bitcoin\Network\NetworkFactory;

final class Bip32KeyService implements Bip32KeyServiceInterface
{
    public function __construct(
        private readonly Bip39MnemonicService $bip39,
    ) {
    }

    public function accountXpub(string $mnemonic, string $path, string $passphrase = '', bool $testnet = false): string
    {
        $seed = $this->bip39->generateSeed($mnemonic, $passphrase);

        $factory = new HierarchicalKeyFactory();
        $master = $factory->fromEntropy($seed);

        $node = $master->derivePath($this->normalizePath($path));

        $network = $testnet
            ? NetworkFactory::bitcoinTestnet()
            : NetworkFactory::bitcoin();

        $xpub = $node->toExtendedPublicKey($network);

        if (is_string($xpub)) {
            return $xpub;
        }

        if (is_object($xpub) && method_exists($xpub, '__toString')) {
            return (string) $xpub;
        }

        throw new \RuntimeException('Unable to serialize extended public key.');
    }

    public function derivePublicKeyHex(string $xpub, string $network, int ...$segments): string
    {
        $factory = new HierarchicalKeyFactory();
        $bitwaspNetwork = $this->resolveNetwork($network);

        $node = $factory->fromExtended($xpub, $bitwaspNetwork);

        foreach ($segments as $segment) {
            $node = $node->deriveChild($segment);
        }

        return $this->extractPublicKeyHex($node);
    }

    public function expectedPrefixForNetwork(string $network): string
    {
        return match (true) {
            $this->isTestnetNetwork($network) => 'tpub',
            default => 'xpub',
        };
    }

    public function assertXpubPrefix(string $xpub, string $network): void
    {
        $expected = $this->expectedPrefixForNetwork($network);

        if (!str_starts_with($xpub, $expected)) {
            throw new \RuntimeException(
                "XPUB prefix mismatch for {$network}: expected {$expected}, got " . substr($xpub, 0, 4)
            );
        }
    }

    private function resolveNetwork(string $network)
    {
        return $this->isTestnetNetwork($network)
            ? NetworkFactory::bitcoinTestnet()
            : NetworkFactory::bitcoin();
    }

    private function isTestnetNetwork(string $network): bool
    {
        return str_contains($network, 'testnet')
            || str_contains($network, 'sepolia')
            || str_contains($network, 'nile')
            || str_contains($network, 'amoy');
    }

    private function normalizePath(string $path): string
    {
        return preg_replace('#^m/#', '', $path) ?? $path;
    }

    private function extractPublicKeyHex(mixed $node): string
    {
        $publicKey = $node->getPublicKey();

        if (is_string($publicKey)) {
            return $publicKey;
        }

        if (is_object($publicKey)) {
            if (method_exists($publicKey, 'getBuffer')) {
                $buffer = $publicKey->getBuffer();

                if (is_object($buffer) && method_exists($buffer, 'getHex')) {
                    return $buffer->getHex();
                }
            }

            if (method_exists($publicKey, 'getHex')) {
                return $publicKey->getHex();
            }

            if (method_exists($publicKey, 'toHex')) {
                return $publicKey->toHex();
            }

            if (method_exists($publicKey, '__toString')) {
                return (string) $publicKey;
            }
        }

        throw new \RuntimeException('Unable to extract public key hex.');
    }
}
