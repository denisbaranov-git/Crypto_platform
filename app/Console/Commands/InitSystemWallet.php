<?php

namespace App\Console\Commands;

use App\Services\Wallet\AddressGeneratorInterfaceOld;
use App\Services\Wallet\SystemWalletService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;

class InitSystemWallet extends Command
{
    //protected $signature = 'hd-wallet:init-hot {network}';
    protected $signature = 'hd-wallet:init-hot';
    protected $description = 'Initialize Hot Wallet for a network';

    /**
     * Execute the console command.
     */

    /**
     * 1,Ethereum,           ethereum
     * 2,Tron,               tron
     * 3,Bitcoin,            bitcoin
     * 4,Ethereum Sepolia,   ethereum_sepolia
     * 5,Arbitrum Sepolia,   arbitrum_sepolia
     * 6,Base Sepolia,       base_sepolia
     * 7,Polygon Amoy,       polygon_amoy
     * 8,Tron Nile,          tron_nile
     * 9,Bitcoin Testnet,    bitcoin_testnet
 */
    private array $networks = [ 'ethereum' => 1, 'tron' => 2 ,'bitcoin' => 3,'ethereum_sepolia' => 4, 'arbitrum_sepolia' => 5, 'base_sepolia' => 6, 'polygon_amoy' => 7, 'tron_nile' => 8, 'bitcoin_testnet' => 9];
    private array $system_wallet_types = [ 'hot', 'cold'];
    protected AddressGeneratorInterfaceOld $addressGenerator;
    protected SystemWalletService  $systemWalletService;

    public function __construct(AddressGeneratorInterfaceOld $addressGenerator, SystemWalletService $systemWalletService )
    {
        parent::__construct();
        $this->addressGenerator = $addressGenerator;
        $this->systemWalletService = $systemWalletService;
    }

    public function handle()
    {
        $this->info('=== Инициализация системных кошельков ===');

        // Выбор из списка
        $network = $this->choice(
            'Выбирете для какой сети :',
            array_merge(array_keys($this->networks), ['all networks']),
            0
        );

        // Подтверждение выбора
        $this->warn("Вы выбрали: " . strtoupper($network));

        if ($this->confirm('Подтверждаете выбор?', true)) {
            $this->info("✓ Выбор подтвержден. Генерируем адрес для: " . $network);

            $selected_networks = ( $network === 'all networks')? array_keys($this->networks) : [$network];
            $this->generate($selected_networks);
        } else {
            $this->error("✗ Операция отменена");
            return;
        }

        foreach ($this->networks as $network => $network_id) {
            foreach($this->system_wallet_types as $walletType) {
                $addressData = $this->addressGenerator->generate($network);

                //$publicKey = $addressData['public_key'];
                $privateKey = $addressData['private_key'];
                $address = $addressData['address'];

                $encryptedPrivateKey = Crypt::encryptString($privateKey);
                $this->updateEnvFile(strtoupper($network).'_'.strtoupper($walletType).'_PRIVATE_KEY_ENCRYPTED', $encryptedPrivateKey);

                $this->updateEnvFile(strtoupper($network).'_'.strtoupper($walletType).'_ADDRESS', $address);

                $this->systemWalletService->createWallet( $network_id, $network, $walletType );//denis //нужно передавать только ( $network_id, $walletType )

                $this->info(strtoupper($walletType). " Wallet for {$network} initialized!");
                $this->line( strtoupper($walletType)." Address: {$address}");
                $this->warn("Make sure to send initial funds to this address for withdrawals!");
            }
        }

    }

    private function updateEnvFile(string $key, string $value): void
    {
        $envPath = base_path('.env');
        $envContent = file_get_contents($envPath);

        if (strpos($envContent, $key) !== false) {
            // Обновляем существующую переменную
            $envContent = preg_replace(
                "/^{$key}=.*/m",
                "{$key}={$value}",
                $envContent
            );
        } else {
            // Добавляем новую переменную
            $envContent .= "\n{$key}={$value}\n";
        }

        file_put_contents($envPath, $envContent);
    }
    private function isExistEnvKeyValue(string $key): bool
    {
        return (bool)ENV($key);
    }
}
