<?php

declare(strict_types=1);

namespace App\Services\Wallet\Crypto;

use App\Services\Wallet\Crypto\Contracts\Bip32KeyServiceInterface;
use BitWasp\Bitcoin\Key\Factory\HierarchicalKeyFactory;
use BitWasp\Bitcoin\Network\NetworkFactory;
use BitWasp\Buffertools\Buffer;

final class Bip32KeyService implements Bip32KeyServiceInterface
{
    public function __construct(
        private readonly Bip39MnemonicService $bip39,
    ) {
    }

    public function accountXpub(string $mnemonic, string $path, string $passphrase = '', bool $testnet = false): string
    {
        $seed = $this->bip39->generateSeed($mnemonic, $passphrase);

        $factory = new HierarchicalKeyFactory();

        // fromEntropy() ожидает Buffer, а не raw string
        $entropy = Buffer::hex(bin2hex($seed));
        $master = $factory->fromEntropy($entropy);

        $node = $master->derivePath($this->normalizePath($path));

        $network = $testnet
            ? NetworkFactory::bitcoinTestnet()
            : NetworkFactory::bitcoin();

        // сериализация делается сразу с правильной сетью (xpub/tpub)
        $xpub = $node->toExtendedPublicKey($network);

        if (is_string($xpub)) {
            return $xpub;
        }

        if (is_object($xpub) && method_exists($xpub, '__toString')) {
            return (string) $xpub;
        }

        throw new \RuntimeException('Unable to serialize extended public key.');
    }

    public function derivePublicKeyHex(string $xpub, string $network, int ...$segments): string
    {
        $factory = new HierarchicalKeyFactory();
        $bitwaspNetwork = $this->resolveNetwork($network);

        // fromExtended() получает network object, иначе tpub/testnet ломается
        $node = $factory->fromExtended($xpub, $bitwaspNetwork);

        foreach ($segments as $segment) {
            $node = $node->deriveChild($segment);
        }

        return $this->extractPublicKeyHex($node);
    }
    public function normalizeUncompressedPublicKeyHex(string $publicKeyHex): string
    {
        $publicKeyHex = strtolower(trim($publicKeyHex));

        // Уже uncompressed
        if (strlen($publicKeyHex) === 130 && str_starts_with($publicKeyHex, '04')) {
            return $publicKeyHex;
        }

        // compressed secp256k1 public key
        if (strlen($publicKeyHex) !== 66 || !in_array(substr($publicKeyHex, 0, 2), ['02', '03'], true)) {
            throw new \RuntimeException('Unsupported public key format.');
        }

        if (!function_exists('gmp_init')) {
            throw new \RuntimeException('GMP extension is required to decompress public keys.');
        }

        $p = gmp_init('FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEFFFFFC2F', 16);
        $x = gmp_init(substr($publicKeyHex, 2), 16);

        $y2 = gmp_mod(gmp_add(gmp_powm($x, 3, $p), 7), $p);
        $exp = gmp_div_q(gmp_add($p, 1), 4);
        $y = gmp_powm($y2, $exp, $p);

        $yIsOdd = gmp_intval(gmp_mod($y, 2)) === 1;
        $prefixIsOdd = substr($publicKeyHex, 0, 2) === '03';

        if ($yIsOdd !== $prefixIsOdd) {
            $y = gmp_sub($p, $y);
        }

        return '04' . $this->gmpHexPad($x, 64) . $this->gmpHexPad($y, 64);
    }
    public function deriveUncompressedPublicKeyHex(string $xpub, string $network, int ...$segments): string
    {
        $compressed = $this->derivePublicKeyHex($xpub, $network, ...$segments);

        // EVM/TRON считают адрес от uncompressed public key
        return $this->decompressPublicKeyHex($compressed);
    }

    public function expectedPrefixForNetwork(string $network): string
    {
        return $this->isTestnetNetwork($network) ? 'tpub' : 'xpub';
    }

    public function assertXpubPrefix(string $xpub, string $network): void
    {
        $expected = $this->expectedPrefixForNetwork($network);

        if (!str_starts_with($xpub, $expected)) {
            throw new \RuntimeException(
                "XPUB prefix mismatch for {$network}: expected {$expected}, got " . substr($xpub, 0, 4)
            );
        }
    }

    private function resolveNetwork(string $network)
    {
        return $this->isTestnetNetwork($network)
            ? NetworkFactory::bitcoinTestnet()
            : NetworkFactory::bitcoin();
    }

    private function isTestnetNetwork(string $network): bool
    {
        return str_contains($network, 'testnet')
            || str_contains($network, 'sepolia')
            || str_contains($network, 'nile')
            || str_contains($network, 'amoy');
    }

    private function normalizePath(string $path): string
    {
        return preg_replace('#^m/#', '', $path) ?? $path;
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

    private function decompressPublicKeyHex(string $publicKeyHex): string
    {
        $publicKeyHex = strtolower(trim($publicKeyHex));

        if (str_starts_with($publicKeyHex, '04') && strlen($publicKeyHex) === 130) {
            return $publicKeyHex;
        }

        if (!in_array(substr($publicKeyHex, 0, 2), ['02', '03'], true) || strlen($publicKeyHex) !== 66) {
            throw new \RuntimeException('Unsupported public key format for decompression.');
        }

        if (!function_exists('gmp_init')) {
            throw new \RuntimeException('GMP extension is required to decompress public keys.');
        }

        // secp256k1 field prime
        $p = gmp_init('FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEFFFFFC2F', 16);
        $x = gmp_init(substr($publicKeyHex, 2), 16);

        // y^2 = x^3 + 7 mod p
        $y2 = gmp_mod(gmp_add(gmp_powm($x, 3, $p), 7), $p);

        // For secp256k1, p % 4 == 3, so sqrt can be computed as y = y2^((p+1)/4) mod p
        $exp = gmp_div_q(gmp_add($p, 1), 4);
        $y = gmp_powm($y2, $exp, $p);

        $yIsOdd = gmp_intval(gmp_mod($y, 2)) === 1;
        $prefixIsOdd = substr($publicKeyHex, 0, 2) === '03';

        if ($yIsOdd !== $prefixIsOdd) {
            $y = gmp_sub($p, $y);
        }

        return '04' . $this->gmpHexPad($x, 64) . $this->gmpHexPad($y, 64);
    }

    private function gmpHexPad(\GMP $number, int $bytes): string
    {
        $hex = gmp_strval($number, 16);

        return str_pad($hex, $bytes * 2, '0', STR_PAD_LEFT);
    }
}
