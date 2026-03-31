<?php

namespace App\Domain\Deposit\Repositories;;

interface DepositRepository
{
    public function save(Deposit $deposit): void;

    public function findById(DepositId $id): ?Deposit;

}
