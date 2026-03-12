<?php

namespace App\Console\Commands;

use BitWasp\Bitcoin\Crypto\Random\Random;
use BitWasp\Bitcoin\Mnemonic\Bip39\Bip39Mnemonic;
use BitWasp\Bitcoin\Mnemonic\MnemonicFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;

class CreateMasterMnemonic extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:create-master-mnemonic';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'create master mnemonic';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        if ( ENV('MASTER_MNEMONIC_ENCRYPTED') ) //denis// нужно переделать на config
            if (!$this->confirm('MASTER_MNEMONIC_ENCRYPTED is exist!!! Continue?')) {
                exit(1);
            }

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
        $encryptedMnemonic = Crypt::encryptString($mnemonic);

        $this->updateEnvFile('MASTER_MNEMONIC_ENCRYPTED', $encryptedMnemonic);

        $this->warn("IMPORTANT: Master mnemonic has been encrypted and saved to .env");
        $this->warn("Make sure to backup this encryption key (APP_KEY) securely!!!");
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
