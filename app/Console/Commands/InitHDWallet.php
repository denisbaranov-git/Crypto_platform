<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Wallet\Crypto\Contracts\Bip32KeyServiceInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class InitHDWallet extends Command
{
    protected $signature = 'hd-wallet:init-all
                            {--network= : Specific network to generate (ethereum, tron, bitcoin, etc.)}
                            {--testnet : Include testnet networks}
                            {--force : Skip confirmation}';

    protected $description = 'Initialize HD wallets and generate XPUBs for supported networks';

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
        $this->info('=== HD Wallet Initialization ===');
        $this->newLine();

        try {
            $mnemonic = $this->getMasterMnemonic();
        } catch (\Throwable $e) {
            $this->error('✗ Master mnemonic not configured!');
            $this->line('  Please set MASTER_MNEMONIC_ENCRYPTED in .env');
            $this->line('  Use: php artisan hd-wallet:generate-mnemonic');
            return self::FAILURE;
        }

        $networks = $this->determineNetworks();

        if (empty($networks)) {
            $this->error('No networks selected.');
            return self::FAILURE;
        }

        $this->info('Networks to initialize:');
        foreach ($networks as $network) {
            $config = $this->networks[$network];
            $this->line("  • {$network} (coin_type: {$config['coin_type']}, path: {$config['path']})");
        }
        $this->newLine();

        if (!$this->option('force')) {
            $this->warn('⚠️  WARNING: This will generate new XPUBs for selected networks.');
            $this->line('  If XPUBs already exist, they will be OVERWRITTEN!');
            $this->newLine();

            if (!$this->confirm('Do you want to continue?', true)) {
                $this->info('Operation cancelled.');
                return self::SUCCESS;
            }
        }

        $this->info('Generating XPUBs...');
        $this->newLine();

        $success = 0;
        $failed = 0;

        foreach ($networks as $network) {
            $config = $this->networks[$network];

            try {
                $xpub = $this->generateXpub($mnemonic, $network, $config['path']);

                // CHANGE: сначала сохраняем в БД, чтобы hd_wallets был источником истины
                $this->persistHdWallet($network, $xpub);

                // CHANGE: затем обновляем .env
                $envKey = strtoupper($network) . '_XPUB';
                $this->updateEnvFile($envKey, $xpub);

                $this->info("✓ {$network}");
                $this->line("  XPUB: <info>" . substr($xpub, 0, 30) . "...</info>");
                $this->line("  Env: <info>{$envKey}</info>");
                $this->line("  Path: <info>{$config['path']}</info>");
                $this->newLine();

                $success++;
            } catch (\Throwable $e) {
                $this->error("✗ {$network}: {$e->getMessage()}");
                $failed++;
            }
        }

        $this->info('=== Complete ===');
        $this->line("  Generated: <info>{$success}</info>");
        if ($failed > 0) {
            $this->line("  Failed: <error>{$failed}</error>");
        }

        $this->newLine();
        $this->warn('⚠️  Next steps:');
        $this->line('  1. Backup .env file securely');
        $this->line('  2. Verify XPUBs are correct');
        $this->line('  3. Initialize system wallets: php artisan hd-wallet:init-hot');
        $this->line('  4. Generate user addresses: php artisan wallet:generate-addresses');

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
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

        $available = array_keys($this->networks);

        if (!$this->option('testnet')) {
            $available = array_filter($available, static function (string $network): bool {
                return !str_contains($network, 'testnet')
                    && !str_contains($network, 'sepolia')
                    && !str_contains($network, 'nile')
                    && !str_contains($network, 'amoy');
            });
        }

        return array_values($available);
    }

    private function generateXpub(string $mnemonic, string $network, string $path): string
    {
        $passphrase = (string) config('wallet.bip39_passphrase', '');
        $testnet = $this->isTestnetNetwork($network);

        $xpub = $this->bip32->accountXpub($mnemonic, $path, $passphrase, $testnet);

        // CHANGE: void-method, throws exception on mismatch
        $this->bip32->assertXpubPrefix($xpub, $network);

        return $xpub;
    }

    /**
     * CHANGE: persist xpub in hd_wallets
     *
     * - If row does not exist: create it with next_index = 0
     * - If row exists: update xpub, but preserve next_index
     *
     * Why:
     * next_index is operational state; re-running init must not reset address issuance history.
     */
    private function persistHdWallet(string $network, string $xpub): void
    {
        $networkId = $this->resolveNetworkId($network);
        $now = now();

        DB::transaction(function () use ($networkId, $network, $xpub, $now): void {
            $existing = DB::table('hd_wallets')
                ->where('network_id', $networkId)
                ->first();

            if ($existing === null) {
                DB::table('hd_wallets')->insert([
                    'network_id' => $networkId,
                    //'xpub' => $xpub,
                    'encrypted_xpub' => encrypt($xpub),
                    'next_index' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $this->line("  DB: inserted hd_wallets row for {$network} (next_index=0)");
                return;
            }

            // CHANGE: preserve next_index, update only xpub and timestamp
            DB::table('hd_wallets')
                ->where('network_id', $networkId)
                ->update([
                    //'xpub' => $xpub,
                    'encrypted_xpub' => encrypt($xpub),
                    'updated_at' => $now,
                ]);

            if (($existing->xpub ?? null) !== $xpub) {
                $this->warn("  DB: updated xpub for {$network}; next_index preserved ({$existing->next_index})");
            } else {
                $this->line("  DB: hd_wallet already up-to-date; next_index preserved ({$existing->next_index})");
            }
        });
    }

    /**
     * CHANGE: resolve id from networks table without hard-coding one column name.
     * If your table uses only `code`, this will still work.
     */
    private function resolveNetworkId(string $network): int
    {
        $networkId = DB::table('networks')->where('code', $network)->value('id');

        if (!$networkId) {
            $networkId = DB::table('networks')->where('slug', $network)->value('id');
        }

        if (!$networkId) {
            $networkId = DB::table('networks')->where('name', $network)->value('id');
        }

        if (!$networkId) {
            throw new \RuntimeException("Network record not found for '{$network}'.");
        }

        return (int) $networkId;
    }

    private function isTestnetNetwork(string $network): bool
    {
        return in_array($network, [
            'ethereum_sepolia',
            'tron_nile',
            'bitcoin_testnet',
            'arbitrum_sepolia',
            'base_sepolia',
            'polygon_amoy',
        ], true);
    }

    private function getMasterMnemonic(): string
    {
        $encryptedMnemonic = config('wallet.master_mnemonic_encrypted') ?: env('MASTER_MNEMONIC_ENCRYPTED');

        if (!$encryptedMnemonic) {
            throw new \RuntimeException('MASTER_MNEMONIC_ENCRYPTED is not defined in .env or config/wallet.php');
        }

        try {
            return Crypt::decryptString($encryptedMnemonic);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Failed to decrypt MASTER_MNEMONIC_ENCRYPTED. It may be corrupted or encrypted with a different key.');
        }
    }

    private function updateEnvFile(string $key, string $value): void
    {
        $envPath = base_path('.env');

        if (!file_exists($envPath)) {
            throw new \RuntimeException('.env file not found at: ' . $envPath);
        }

        if (!is_writable($envPath)) {
            throw new \RuntimeException('.env file is not writable');
        }

        $envContent = file_get_contents($envPath);

        if ($envContent === false) {
            throw new \RuntimeException('Unable to read .env file');
        }

        $quotedKey = preg_quote($key, '/');

        if (preg_match("/^{$quotedKey}=.*/m", $envContent)) {
            $envContent = preg_replace("/^{$quotedKey}=.*/m", "{$key}={$value}", $envContent);
        } else {
            $envContent = rtrim($envContent) . "\n{$key}={$value}\n";
        }

        if (file_put_contents($envPath, $envContent, LOCK_EX) === false) {
            throw new \RuntimeException('Failed to write .env file');
        }

        $this->line("  Updated: {$key}");
    }
}
