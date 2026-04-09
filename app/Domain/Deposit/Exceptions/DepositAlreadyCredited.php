<?php

namespace App\Domain\Deposit\Exceptions;

final class DepositAlreadyCredited extends \DomainException
{
    public function __construct()
    {
        parent::__construct('Deposit is already credited.');
    }
}
