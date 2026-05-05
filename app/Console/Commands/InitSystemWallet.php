<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Wallet\AddressGeneratorInterface;
use App\Services\Wallet\SystemWalletService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class InitSystemWallet extends Command
{
    /**
     * # Интерактивный режим
     * php artisan hd-wallet:init-hot
     *
     * # Для конкретной сети
     * php artisan hd-wallet:init-hot ethereum
     *
     * # Для всех mainnet сетей
     * php artisan hd-wallet:init-hot --all
     *
     * # Для всех сетей включая testnet
     * php artisan hd-wallet:init-hot --all --testnet
     *
     * # Создать cold wallet
     * php artisan hd-wallet:init-hot ethereum --type=cold --force
     */
    protected $signature = 'hd-wallet:init-hot
                            {network? : Network code}
                            {--type=hot : Wallet type (hot/cold/sweep/fee)}
                            {--all : Initialize all mainnet networks}
                            {--testnet : Include testnet networks}
                            {--force : Skip confirmation}';

    protected $description = 'Initialize system wallet for specified network';

    private array $mainnetNetworks = [
        'ethereum',
        'tron',
        'bitcoin',
    ];

    private array $testnetNetworks = [
        'ethereum_sepolia',
        'arbitrum_sepolia',
        'base_sepolia',
        'polygon_amoy',
        'tron_nile',
        'bitcoin_testnet',
    ];

    public function __construct(
        private readonly AddressGeneratorInterface $addressGenerator,
        private readonly SystemWalletService $systemWalletService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('=== System Wallet Initialization ===');
        $this->newLine();

        // Определяем сети
        $networks = $this->determineNetworks();

        if (empty($networks)) {
            $this->error('No networks selected.');
            return self::FAILURE;
        }

        // Определяем тип кошелька
        $walletType = $this->option('type');
        $allowedTypes = ['hot', 'cold', 'sweep', 'fee'];

        if (!in_array($walletType, $allowedTypes)) {
            $this->error("Invalid wallet type: {$walletType}. Use: " . implode(', ', $allowedTypes));
            return self::FAILURE;
        }

        // Показываем конфигурацию
        $this->info('Configuration:');
        $this->line("  Type: <comment>{$walletType}</comment>");
        $this->line("  Networks: <comment>" . implode(', ', $networks) . "</comment>");
        $this->newLine();

        // Подтверждение
        if (!$this->option('force')) {
            if (!$this->confirm('Proceed with initialization?', true)) {
                $this->info('Cancelled.');
                return self::SUCCESS;
            }
        }

        // Инициализация
        $success = 0;
        $failed = 0;

        foreach ($networks as $network) {
            $this->newLine();
            $this->info("[{$network}]");
            $this->line(str_repeat('-', 40));

            try {
                $result = $this->initializeWallet($network, $walletType);

                $this->info("✓ Success!");
                $this->line("  Address: <info>{$result['address']}</info>");
                $this->line("  Type: <info>{$result['type']}</info>");
                $this->line("  Network: <info>{$result['network']}</info>");
                $success++;

            } catch (\Exception $e) {
                $this->error("✗ Failed: {$e->getMessage()}");
                $failed++;
            }
        }

        // Итоги
        $this->newLine();
        $this->info('=== Complete ===');
        $this->line("  Success: <info>{$success}</info>");
        if ($failed > 0) {
            $this->line("  Failed: <error>{$failed}</error>");
        }

        if ($success > 0) {
            $this->newLine();
            $this->warn('⚠️  Next steps:');
            $this->line('  1. Backup .env file');
            $this->line('  2. Fund wallets with initial balance');
            $this->line('  3. Test in testnet first');
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Определяет список сетей
     */
    private function determineNetworks(): array
    {
        $network = $this->argument('network');

        if ($network) {
            $allNetworks = array_merge($this->mainnetNetworks, $this->testnetNetworks);

            if (!in_array($network, $allNetworks)) {
                $this->error("Unknown network: {$network}");
                return [];
            }

            return [$network];
        }

        if ($this->option('all')) {
            return $this->option('testnet')
                ? array_merge($this->mainnetNetworks, $this->testnetNetworks)
                : $this->mainnetNetworks;
        }

        // Интерактивный выбор
        $choices = $this->mainnetNetworks;
        if ($this->option('testnet')) {
            $choices = array_merge($choices, $this->testnetNetworks);
        }
        $choices[] = 'all';

        $selected = $this->choice('Select network:', $choices, 0);

        return $selected === 'all' ? array_slice($choices, 0, -1) : [$selected];
    }

    /**
     * Инициализирует кошелек
     */
    private function initializeWallet(string $network, string $walletType): array
    {
        // 1. Генерируем адрес
        $addressData = $this->addressGenerator->generate($network);

        $privateKey = $addressData['private_key'] ?? null;
        $address = $addressData['address'] ?? null;

        if (!$privateKey || !$address) {
            throw new \RuntimeException("Failed to generate address");
        }

        // 2. Шифруем приватный ключ
        $encryptedPrivateKey = Crypt::encryptString($privateKey);

        // 3. Сохраняем в .env
        $envPrefix = strtoupper($network) . '_' . strtoupper($walletType);

        $this->updateEnvFile("SYSTEM_WALLET_{$envPrefix}_ADDRESS", $address);
        $this->updateEnvFile("SYSTEM_WALLET_{$envPrefix}_PRIVATE_KEY_ENCRYPTED", $encryptedPrivateKey);

        // В development сохраняем и сырой ключ
        if (!app()->environment('production')) {
            $this->updateEnvFile("SYSTEM_WALLET_{$envPrefix}_PRIVATE_KEY", $privateKey);
        }

        // 4. Получаем network_id
        $networkId = DB::table('networks')
            ->where('code', $network)
            ->value('id');

        if (!$networkId) {
            throw new \RuntimeException("Network '{$network}' not found in database");
        }

        // 5. Сохраняем в БД
        $wallet = $this->systemWalletService->createWallet(
            networkId: $networkId,
            address: $address,
            encryptedPrivateKey: $encryptedPrivateKey,
            type: $walletType,
        );

        return [
            'address' => $address,
            'type' => $walletType,
            'network' => $network,
        ];
    }

    /**
     * Обновляет .env файл
     */
    private function updateEnvFile(string $key, string $value): void
    {
        $envPath = base_path('.env');

        if (!file_exists($envPath)) {
            throw new \RuntimeException('.env file not found');
        }

        $envContent = file_get_contents($envPath);

        if (preg_match("/^{$key}=.*/m", $envContent)) {
            $envContent = preg_replace(
                "/^{$key}=.*/m",
                "{$key}={$value}",
                $envContent
            );
        } else {
            $envContent .= "\n{$key}={$value}\n";
        }

        file_put_contents($envPath, $envContent);
    }
}
