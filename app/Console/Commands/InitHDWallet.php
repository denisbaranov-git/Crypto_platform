<?php

namespace App\Console\Commands;

use _PHPStan_781aefaf6\Nette\Neon\Exception;
use App\Services\Wallet\HDAddressGeneratorInterface;
use App\Services\Wallet\WalletService;
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
    protected WalletService $walletService;

    public function __construct( HDAddressGeneratorInterface $addressGenerator, WalletService $walletService )
    {
        parent::__construct();
        $this->addressGenerator = $addressGenerator;
        $this->walletService = $walletService;
    }

    public function handle()
    {
        $mnemonic = $this->getMasterMnemonic();
        // generate xpub
        $seedGenerator = new Bip39SeedGenerator();
        $seed = $seedGenerator->getSeed($mnemonic);
        $factory = new HierarchicalKeyFactory();
        $master = $factory->fromEntropy($seed);

        foreach ($this->networks as $network => $config) {
            $this->generateXpub($master, $network, $config);
        }
    }

    private function generateXpub($master, $network, $config): void
    {
        //Denis деревация до уровня /account/ = /0, далее index уже в конкретном кошельке
        $accountKey = $master->derivePath($config['path']);
        $xpub = $accountKey->toExtendedPublicKey();

        // save to .env
        $envKey = strtoupper($network) . '_XPUB';
        $this->updateEnvFile($envKey, $xpub);

        $this->info("{$network} xpub generated and saved");
    }
    private function getMasterMnemonic(): string
    {
        $encryptedMnemonic = ENV('MASTER_MNEMONIC_ENCRYPTED'); //denis// нужно переделать на config
        if(!$encryptedMnemonic) throw new Exception('MASTER_MNEMONIC_ENCRYPTED is not defined');

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
}
