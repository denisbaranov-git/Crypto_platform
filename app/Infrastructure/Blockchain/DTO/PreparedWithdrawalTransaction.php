<?php
declare(strict_types=1);

namespace App\Infrastructure\Blockchain\DTO;

final readonly class PreparedWithdrawalTransaction
{
public function __construct(
public string $fingerprint,
public string $rawTransaction,
public string $rawTransactionHash,
public array $metadata = [],
) {}
}
