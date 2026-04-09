<?php

namespace App\Domain\Deposit\Exceptions;

final class InvalidDepositTransition extends \DomainException
{
    public function __construct(string $from, string $to)
    {
        parent::__construct("Invalid deposit state transition: {$from} -> {$to}.");
    }
}
