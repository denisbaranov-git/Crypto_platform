<?php

namespace App\Console\Commands;

use BitWasp\Bitcoin\Crypto\Random\Random;
use BitWasp\Bitcoin\Key\Factory\HierarchicalKeyFactory;
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

    public function handle()
    {
        $mnemonic = $this->getMasterMnemonic();
        $encryptedMnemonic = Crypt::encryptString($mnemonic);
        $this->updateEnvFile('MASTER_MNEMONIC_ENCRYPTED', $encryptedMnemonic);

        // generate xpub
        $seedGenerator = new Bip39SeedGenerator();
        //$seed = $seedGenerator->getSeed($mnemonic, 'password');
        $seed = $seedGenerator->getSeed($mnemonic);
        $factory = new HierarchicalKeyFactory();
        $master = $factory->fromEntropy($seed);

        foreach ($this->networks as $network => $config) {
            //Denis деревация до уровня /account/ = /0, далее index уже в конкретном кошельке
            $accountKey = $master->derivePath($config['path']);
            $xpub = $accountKey->toExtendedPublicKey();

            // save to .env
            $envKey = strtoupper($network) . '_XPUB';
            $this->updateEnvFile($envKey, $xpub);

            $this->info("{$network} xpub generated and saved");
        }

        $this->warn("IMPORTANT: Master mnemonic has been encrypted and saved to .env");
        $this->warn("Make sure to backup this encryption key (APP_KEY) securely!");
    }

    private function getMasterMnemonic(): string
    {
        if ($this->confirm('Do you have an existing mnemonic phrase?')) {
            return $this->secret('Enter your mnemonic phrase (12 or 24 words)');
        } // либо руками вводим
        // либо генерируем
        // Generate a mnemonic
        $random = new Random();
        $entropy = $random->bytes(Bip39Mnemonic::MAX_ENTROPY_BYTE_LEN);

        $bip39 = MnemonicFactory::bip39();
        $mnemonic = $bip39->entropyToMnemonic($entropy);

        $this->info("\nGenerated mnemonic phrase:");
        $this->line($mnemonic);
        $this->warn("\nSAVE THIS IN A SECURE LOCATION! It will not be shown again.");

        if (!$this->confirm('Have you saved this phrase?')) {
            $this->error('You must save the mnemonic phrase before continuing!');
            exit(1);
        }

        return $mnemonic;
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
