<?php

declare(strict_types=1);

namespace App\Console\Commands;

use BitWasp\Bitcoin\Key\Factory\HierarchicalKeyFactory;
use Illuminate\Console\Command;

final class VerifyHdXpubs extends Command
{
    protected $signature = 'hd-wallet:verify-xpubs
                            {--network= : Check only one network}
                            {--index=0 : Child index to derive for validation}';

    protected $description = 'Validate HD XPUBs: prefix, restore, and derive child path';

    private array $networks = [
        'ethereum' => ['coin_type' => 60, 'path' => "m/44'/60'/0'"],
        'tron' => ['coin_type' => 195, 'path' => "m/44'/195'/0'"],
        'bitcoin' => ['coin_type' => 0, 'path' => "m/44'/0'/0'"],
        'arbitrum' => ['coin_type' => 60, 'path' => "m/44'/60'/0'"],
        'base' => ['coin_type' => 60, 'path' => "m/44'/60'/0'"],
        'polygon' => ['coin_type' => 60, 'path' => "m/44'/60'/0'"],
        'bsc' => ['coin_type' => 60, 'path' => "m/44'/60'/0'"],
        'ethereum_sepolia' => ['coin_type' => 1, 'path' => "m/44'/1'/0'"],
        'tron_nile' => ['coin_type' => 1, 'path' => "m/44'/1'/0'"],
        'bitcoin_testnet' => ['coin_type' => 1, 'path' => "m/44'/1'/0'"],
        'arbitrum_sepolia' => ['coin_type' => 1, 'path' => "m/44'/1'/0'"],
        'base_sepolia' => ['coin_type' => 1, 'path' => "m/44'/1'/0'"],
        'polygon_amoy' => ['coin_type' => 1, 'path' => "m/44'/1'/0'"],
    ];

    public function handle(): int
    {
        $this->info('=== HD XPUB Verification ===');
        $this->newLine();

        $networks = $this->determineNetworks();
        if (empty($networks)) {
            $this->error('No networks selected.');
            return self::FAILURE;
        }

        $index = (int) $this->option('index');
        $factory = new HierarchicalKeyFactory();
        $seenXpubs = [];

        $ok = 0;
        $fail = 0;

        foreach ($networks as $network) {
            $xpub = (string) config("wallet.{$network}_xpub");

            if ($xpub === '') {
                $this->error("✗ {$network}: XPUB not configured");
                $fail++;
                continue;
            }

            $isTestnet = $this->isTestnetNetwork($network);
            $expectedPrefix = $isTestnet ? 'tpub' : 'xpub';

            $this->line("Network: <info>{$network}</info>");
            $this->line("  Expected prefix: <info>{$expectedPrefix}</info>");
            $this->line("  Actual prefix:   <info>" . substr($xpub, 0, 4) . "</info>");

            if (!str_starts_with($xpub, $expectedPrefix)) {
                $this->warn("  Prefix mismatch: expected {$expectedPrefix}, got " . substr($xpub, 0, 4));
            } else {
                $this->info('  Prefix OK');
            }

            if (isset($seenXpubs[$xpub])) {
                $this->warn("  Duplicate XPUB: same as {$seenXpubs[$xpub]}");
            } else {
                $seenXpubs[$xpub] = $network;
            }

            try {
                $node = $factory->fromExtended(
                    $xpub,
                    $isTestnet ? \BitWasp\Bitcoin\Network\NetworkFactory::bitcoinTestnet() : \BitWasp\Bitcoin\Network\NetworkFactory::bitcoin()
                );

                $child = $node->deriveChild(0)->deriveChild($index);

                $pubHex = $this->extractPublicKeyHex($child);

                $this->info('  Restore OK');
                $this->line('  Derivation OK: 0/' . $index);
                $this->line('  Child pubkey:   <comment>' . substr($pubHex, 0, 24) . '...</comment>');
                $this->newLine();

                $ok++;
            } catch (\Throwable $e) {
                $this->error('  Restore/derivation failed: ' . $e->getMessage());
                $this->newLine();
                $fail++;
            }
        }

        $this->info('=== Summary ===');
        $this->line("  OK:   <info>{$ok}</info>");
        if ($fail > 0) {
            $this->line("  Fail: <error>{$fail}</error>");
        }

        return $fail > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function determineNetworks(): array
    {
        $specificNetwork = $this->option('network');

        if ($specificNetwork) {
            if (!isset($this->networks[$specificNetwork])) {
                $this->error("Unknown network: {$specificNetwork}");
                $this->line('Available networks: ' . implode(', ', array_keys($this->networks)));
                return [];
            }

            return [$specificNetwork];
        }

        return array_keys($this->networks);
    }

    private function isTestnetNetwork(string $network): bool
    {
        return str_contains($network, 'testnet')
            || str_contains($network, 'sepolia')
            || str_contains($network, 'nile')
            || str_contains($network, 'amoy');
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
