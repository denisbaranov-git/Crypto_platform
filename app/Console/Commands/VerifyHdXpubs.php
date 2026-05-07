<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Wallet\Services\GeneratedAddress;
use App\Services\Wallet\Crypto\Contracts\Bip32KeyServiceInterface;
use App\Services\Wallet\Generators\BitcoinHDAddressGenerator;
use App\Services\Wallet\Generators\EvmHDAddressGenerator;
use App\Services\Wallet\Generators\TronHDAddressGenerator;
use BitWasp\Bitcoin\Key\Factory\HierarchicalKeyFactory;
use BitWasp\Bitcoin\Network\NetworkFactory;
use Illuminate\Console\Command;

final class VerifyHdXpubs extends Command
{
    protected $signature = 'hd-wallet:verify-xpubs
                            {--network= : Check only one network}
                            {--index=0 : Child index to derive for validation}
                            {--check-change : Also verify Bitcoin change branch (chain=1)}';

    protected $description = 'Validate HD XPUBs, restore, derive child path, and verify GeneratedAddress consistency';

    private array $networks = [
        // Mainnet
        'ethereum' => ['coin_type' => 60, 'path' => "m/44'/60'/0'"],
        'tron' => ['coin_type' => 195, 'path' => "m/44'/195'/0'"],
        'bitcoin' => ['coin_type' => 0, 'path' => "m/44'/0'/0'"],

        // EVM compatible
        'arbitrum' => ['coin_type' => 60, 'path' => "m/44'/60'/0'"],
        'base' => ['coin_type' => 60, 'path' => "m/44'/60'/0'"],
        'polygon' => ['coin_type' => 60, 'path' => "m/44'/60'/0'"],
        'bsc' => ['coin_type' => 60, 'path' => "m/44'/60'/0'"],

        // Testnet
        'ethereum_sepolia' => ['coin_type' => 1, 'path' => "m/44'/1'/0'"],
        'tron_nile' => ['coin_type' => 1, 'path' => "m/44'/1'/0'"],
        'bitcoin_testnet' => ['coin_type' => 1, 'path' => "m/44'/1'/0'"],
        'arbitrum_sepolia' => ['coin_type' => 1, 'path' => "m/44'/1'/0'"],
        'base_sepolia' => ['coin_type' => 1, 'path' => "m/44'/1'/0'"],
        'polygon_amoy' => ['coin_type' => 1, 'path' => "m/44'/1'/0'"],
    ];

    public function __construct(
        private readonly Bip32KeyServiceInterface $bip32,
    ) {
        parent::__construct();
    }

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
            $expectedPrefix = $this->bip32->expectedPrefixForNetwork($network);

            $this->line("Network: <info>{$network}</info>");
            $this->line("  Expected prefix: <info>{$expectedPrefix}</info>");
            $this->line("  Actual prefix:   <info>" . substr($xpub, 0, 4) . "</info>");

            try {
                // CHANGE: assertXpubPrefix() is void and throws on mismatch
                $this->bip32->assertXpubPrefix($xpub, $network);
                $this->info('  Prefix OK');
            } catch (\Throwable $e) {
                $this->error('  Prefix failed: ' . $e->getMessage());
                $this->newLine();
                $fail++;
                continue;
            }

            if (isset($seenXpubs[$xpub])) {
                $this->warn("  Duplicate XPUB: same as {$seenXpubs[$xpub]}");
            } else {
                $seenXpubs[$xpub] = $network;
            }

            try {
                $this->verifyRestoreAndDerivation($xpub, $index, $isTestnet);
                $generated = $this->generateSampleAddress($network, $xpub, $index);

                $this->verifyGeneratedAddressConsistency($network, $generated, $index);

                if ($this->option('check-change') && ($network === 'bitcoin' || $network === 'bitcoin_testnet')) {
                    $this->verifyBitcoinChangeBranch($xpub, $index, $isTestnet);
                }

                $this->info('  OK');
                $this->newLine();
                $ok++;
            } catch (\Throwable $e) {
                $this->error('  Failed: ' . $e->getMessage());
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

    private function verifyRestoreAndDerivation(string $xpub, int $index, bool $isTestnet): void
    {
        $factory = new HierarchicalKeyFactory();
        $bitwaspNetwork = $isTestnet ? NetworkFactory::bitcoinTestnet() : NetworkFactory::bitcoin();

        $node = $factory->fromExtended($xpub, $bitwaspNetwork);
        $child = $node->deriveChild(0)->deriveChild($index);

        $pubHex = $this->extractPublicKeyHex($child);

        $this->line('  Restore OK');
        $this->line('  Derivation OK: 0/' . $index);
        $this->line('  Child pubkey:   <comment>' . substr($pubHex, 0, 24) . '...</comment>');
    }

    private function generateSampleAddress(string $network, string $xpub, int $index): GeneratedAddress
    {
        // CHANGE: EVM aliases are grouped together
        if ($this->isEvmNetwork($network)) {
            return (new EvmHDAddressGenerator($this->bip32, $xpub, $network))->generate($index);
        }

        // CHANGE: TRON aliases are grouped together
        if ($this->isTronNetwork($network)) {
            return (new TronHDAddressGenerator($this->bip32, $xpub, $network))->generate($index);
        }

        // CHANGE: Bitcoin mainnet/testnet handled explicitly
        if ($network === 'bitcoin') {
            return (new BitcoinHDAddressGenerator($this->bip32, $xpub))->generate($index, false, 'segwit', 0);
        }

        if ($network === 'bitcoin_testnet') {
            return (new BitcoinHDAddressGenerator($this->bip32, $xpub))->generate($index, true, 'segwit', 0);
        }

        throw new \InvalidArgumentException("Unsupported network: {$network}");
    }

    private function verifyGeneratedAddressConsistency(
        string $network,
        GeneratedAddress $generated,
        int $index
    ): void {
        $expectedChain = 0;
        $expectedPath = $this->expectedPath($network, $expectedChain, $index);

        if ($generated->chain() !== $expectedChain) {
            throw new \RuntimeException(
                "derivation_chain mismatch: expected {$expectedChain}, got {$generated->chain()}"
            );
        }

        if ($generated->index() !== $index) {
            throw new \RuntimeException(
                "derivation_index mismatch: expected {$index}, got {$generated->index()}"
            );
        }

        if ($generated->path() !== $expectedPath) {
            throw new \RuntimeException(
                "derivation_path mismatch: expected {$expectedPath}, got {$generated->path()}"
            );
        }

        $this->line("  Address OK: <info>{$generated->address()}</info>");
        $this->line("  Path OK:    <info>{$generated->path()}</info>");
        $this->line("  Chain OK:   <info>{$generated->chain()}</info>");
        $this->line("  Index OK:   <info>{$generated->index()}</info>");

        $this->assertAddressFormat($network, $generated);
    }

    private function verifyBitcoinChangeBranch(string $xpub, int $index, bool $isTestnet): void
    {
        $generated = (new BitcoinHDAddressGenerator($this->bip32, $xpub))
            ->generate($index, $isTestnet, 'segwit', 1);

        $expectedPath = $isTestnet
            ? "m/44'/1'/0'/1/{$index}"
            : "m/44'/0'/0'/1/{$index}";

        if ($generated->chain() !== 1) {
            throw new \RuntimeException('Bitcoin change branch chain mismatch: expected 1.');
        }

        if ($generated->path() !== $expectedPath) {
            throw new \RuntimeException(
                "Bitcoin change path mismatch: expected {$expectedPath}, got {$generated->path()}"
            );
        }

        $this->line("  Change branch OK: <info>{$generated->path()}</info>");
    }

    private function expectedPath(string $network, int $chain, int $index): string
    {
        return match (true) {
            $network === 'bitcoin' => "m/44'/0'/0'/{$chain}/{$index}",
            $network === 'bitcoin_testnet' => "m/44'/1'/0'/{$chain}/{$index}",

            $this->isTronNetwork($network) && !$this->isTestnetNetwork($network) => "m/44'/195'/0'/0/{$index}",
            $this->isTronNetwork($network) && $this->isTestnetNetwork($network) => "m/44'/1'/0'/0/{$index}",

            $this->isEvmNetwork($network) && !$this->isTestnetNetwork($network) => "m/44'/60'/0'/0/{$index}",
            $this->isEvmNetwork($network) && $this->isTestnetNetwork($network) => "m/44'/1'/0'/0/{$index}",

            default => throw new \InvalidArgumentException("Unsupported network: {$network}"),
        };
    }

    private function isEvmNetwork(string $network): bool
    {
        return in_array($network, [
            'ethereum',
            'arbitrum',
            'base',
            'polygon',
            'bsc',
            'ethereum_sepolia',
            'arbitrum_sepolia',
            'base_sepolia',
            'polygon_amoy',
        ], true);
    }

    private function isTronNetwork(string $network): bool
    {
        return in_array($network, [
            'tron',
            'tron_nile',
        ], true);
    }

    private function isTestnetNetwork(string $network): bool
    {
        return str_contains($network, 'testnet')
            || str_contains($network, 'sepolia')
            || str_contains($network, 'nile')
            || str_contains($network, 'amoy');
    }

    private function assertAddressFormat(string $network, GeneratedAddress $generated): void
    {
        $address = $generated->address();

        if (str_starts_with($network, 'bitcoin')) {
            if (!preg_match('/^(1|3|bc1|m|n|2|tb1)/i', $address)) {
                throw new \RuntimeException("Bitcoin address format looks invalid: {$address}");
            }
            return;
        }

        if ($this->isTronNetwork($network)) {
            if (!preg_match('/^T[1-9A-HJ-NP-Za-km-z]{33}$/', $address)) {
                throw new \RuntimeException("TRON address format looks invalid: {$address}");
            }
            return;
        }

        if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $address)) {
            throw new \RuntimeException("EVM address format looks invalid: {$address}");
        }
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
