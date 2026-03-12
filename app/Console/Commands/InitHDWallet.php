<?php

namespace App\Console\Commands;

use _PHPStan_781aefaf6\Nette\Neon\Exception;
use App\Services\Wallet\HDAddressGeneratorInterface;
use BitWasp\Bitcoin\Crypto\Random\Random;
use BitWasp\Bitcoin\Key\Deterministic\HierarchicalKeyFactory;
use BitWasp\Bitcoin\Mnemonic\Bip39\Bip39Mnemonic;
use BitWasp\Bitcoin\Mnemonic\Bip39\Bip39SeedGenerator;
use BitWasp\Bitcoin\Mnemonic\MnemonicFactory;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;

class InitHDWallet extends Command
{
    protected $signature = 'hd-wallet:init-all';
    protected $description = 'Initialize HD wallets for all supported networks';

    private array $networks = [
        'ethereum' => ['coin_type' => 60, 'path' => "m/44'/60'/0'/0"],
        'bsc' => ['coin_type' => 60, 'path' => "m/44'/60'/0'/0"],
        'polygon' => ['coin_type' => 60, 'path' => "m/44'/60'/0'/0"],
        'tron' => ['coin_type' => 195, 'path' => "m/44'/195'/0'/0"],
    ];
    protected HDAddressGeneratorInterface $addressGenerator;

    public function __construct( HDAddressGeneratorInterface $addressGenerator )
    {
        parent::__construct();
        $this->addressGenerator = $addressGenerator;
    }
    public function handle()
    {
        $mnemonic = $this->getMasterMnemonic();
//        $encryptedMnemonic = Crypt::encryptString($mnemonic);
//        $this->updateEnvFile('MASTER_MNEMONIC_ENCRYPTED', $encryptedMnemonic);

        // generate xpub
        $seedGenerator = new Bip39SeedGenerator();
        $seed = $seedGenerator->getSeed($mnemonic);
        $factory = new HierarchicalKeyFactory();
        $master = $factory->fromEntropy($seed);

        foreach ($this->networks as $network => $config) {
            $this->generateXpub($master,$network, $config);
            $this->generateSystemWallet($network);
        }
    }

    private function generateXpub($master,$network, $config): void
    {
        //Denis деревация до уровня /account/ = /0, далее index уже в конкретном кошельке
        $accountKey = $master->derivePath($config['path']);
        $xpub = $accountKey->toExtendedPublicKey();

        // save to .env
        $envKey = strtoupper($network) . '_XPUB';
        $this->updateEnvFile($envKey, $xpub);

        $this->info("{$network} xpub generated and saved");
    }
    private function generateSystemWallet($network): void
    {
        //$mnemonic = $this->getMasterMnemonic();

        $wallets_index = Wallet::where('type', 'system')->pluc('address_index'); // или hot cold // denis!!!!!!
        $key_address = $this->addressGenerator->generate($network, $wallets_index);

//        $privateKey = $key_address['private_key'];
//        $address = $key_address['address'];

        $encryptedPrivateKey = Crypt::encryptString($privateKey);
        $this->updateEnvFile(strtoupper($network).'_HOT_PRIVATE_KEY_ENCRYPTED', $encryptedPrivateKey);

        $encryptedAddress = Crypt::encryptString($privateKey);
        $this->updateEnvFile(strtoupper($network).'_HOT_ADDRESS_ENCRYPTED', $encryptedAddress);

        $this->info("Hot Wallet for {$network} initialized!");
        $this->line("Hot Address: {$address}");
        $this->warn("Make sure to send initial funds to this address for withdrawals!");
    }
    private function getMasterMnemonic(): string
    {
        $encryptedMnemonic = ENV('MASTER_MNEMONIC_ENCRYPTED'); //denis// нужно переделать на config
        if(!$encryptedMnemonic) throw new Exception('MASTER_MNEMONIC_ENCRYPTED is not defined');

        return Crypt::decryptString($encryptedMnemonic);
//        if ($this->confirm('Do you have an existing mnemonic phrase?')) {
//            return $this->secret('Enter your mnemonic phrase (12 or 24 words)');
//        } // либо руками вводим
//        // либо генерируем
//        // Generate a mnemonic
//        $random = new Random();
//        $entropy = $random->bytes(Bip39Mnemonic::MAX_ENTROPY_BYTE_LEN);
//
//        $bip39 = MnemonicFactory::bip39();
//        $mnemonic = $bip39->entropyToMnemonic($entropy);
//
//        $this->info("\nGenerated mnemonic phrase:");
//        $this->line($mnemonic);
//        $this->warn("\nSAVE THIS IN A SECURE LOCATION! It will not be shown again.");
//
//        if (!$this->confirm('Have you saved this phrase?')) {
//            $this->error('You must save the mnemonic phrase before continuing!');
//            exit(1);
//        }
//
//        return $mnemonic;
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
}
