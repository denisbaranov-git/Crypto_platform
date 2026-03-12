<?php

namespace App\Console\Commands;

use App\Services\Wallet\AddressGeneratorInterface;
use BitWasp\Bitcoin\Crypto\Random\Random;
use BitWasp\Bitcoin\Mnemonic\Bip39\Bip39Mnemonic;
use BitWasp\Bitcoin\Mnemonic\Bip39\Bip39SeedGenerator;
use BitWasp\Bitcoin\Mnemonic\MnemonicFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;

class InitHotWallet extends Command
{
    //protected $signature = 'hd-wallet:init-hot {network}';
    protected $signature = 'hd-wallet:init-hot';
    protected $description = 'Initialize Hot Wallet for a network';
    /**
     * Execute the console command.
     */
    private array $networks = [
        'ethereum' => ['coin_type' => 60, 'path' => "m/44'/60'/0'/0"],
        'bsc' => ['coin_type' => 60, 'path' => "m/44'/60'/0'/0"],
        'polygon' => ['coin_type' => 60, 'path' => "m/44'/60'/0'/0"],
        'tron' => ['coin_type' => 195, 'path' => "m/44'/195'/0'/0"],
    ];
    protected AddressGeneratorInterface $addressGenerator;

    public function __construct( AddressGeneratorInterface $addressGenerator )
    {
        parent::__construct();
        $this->addressGenerator = $addressGenerator;
    }

    public function handle()
    {
        $this->info('=== Инициализация горячих кошельков ===');

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
//        $mnemonic = $this->getMasterMnemonic();
//
//        $encryptedMnemonic = Crypt::encryptString($mnemonic);
//        $envKey = strtoupper($network) . '_HOT_WALLET_MNEMONIC_ENCRYPTED';
//        $this->updateEnvFile($envKey, $encryptedMnemonic);
//
//        $seedGenerator = new Bip39SeedGenerator();
//        $seed = $seedGenerator->getSeed($mnemonic);
//
//        $encryptedSeed = Crypt::encryptString($seed);
//        // 3. Обновляем .env
//        $this->updateEnvFile(strtoupper($network) . '_HOT_SEED', $encryptedSeed);
    }

//$factory = new HierarchicalKeyFactory();
//$masterNode = $factory->fromSeed($seed); // Это xprv (содержит приватный ключ)
//
//// Теперь у нас есть полный доступ
//$privateKey = $masterNode->getPrivateKey(); // Приватный ключ для подписи
//$xprv = $masterNode->toExtendedPrivateKey(); // Полный xprv (хранить в холоде!)
//$xpub = $masterNode->toExtendedPublicKey(); // Публичный xpub (можно на сервер)
    private function generate(array $networks)
    {
        foreach ($networks as $network) {
            $key_address = $this->addressGenerator->generate($network);
            $privateKey = $key_address['private_key'];
            $address = $key_address['address'];

            $encryptedPrivateKey = Crypt::encryptString($privateKey);
            $this->updateEnvFile(strtoupper($network).'_HOT_PRIVATE_KEY_ENCRYPTED', $encryptedPrivateKey);

            $encryptedAddress = Crypt::encryptString($privateKey);
            $this->updateEnvFile(strtoupper($network).'_HOT_ADDRESS_ENCRYPTED', $encryptedAddress);

            $this->info("Hot Wallet for {$network} initialized!");
            $this->line("Hot Address: {$address}");
            $this->warn("Make sure to send initial funds to this address for withdrawals!");

        }
    }
//    private function getHotWalletSeed(string $network): string
//    {
//        if ($this->confirm("Do you have an existing Hot Wallet seed for {$network}?")) {
//            return $this->secret('Enter seed (hex or mnemonic)');
//        }
//
//        // Генерируем новый seed (32 байта)
//        return bin2hex(random_bytes(32));
//    }
    private function getMasterMnemonic(): string
    {
        $encryptedMnemonic = ENV('MASTER_MNEMONIC_ENCRYPTED'); //denis// нужно переделать на config
        if(!$encryptedMnemonic) throw new \Exception('MASTER_MNEMONIC_ENCRYPTED is not defined');

        return Crypt::decryptString($encryptedMnemonic);
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
