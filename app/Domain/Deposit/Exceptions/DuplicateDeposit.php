<?php

namespace App\Domain\Deposit\Exceptions;

class DuplicateDeposit extends \Exception
{
    public function __construct(string $networkId, string $externalKey)
    {
        parent::__construct("Duplicate deposit detected for network={$networkId}, key={$externalKey}.");
    }

}
