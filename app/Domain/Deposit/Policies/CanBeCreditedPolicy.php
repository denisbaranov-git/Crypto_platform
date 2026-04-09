<?php

namespace App\Domain\Deposit\Policies;

use App\Domain\Deposit\Entities\Deposit;
use App\Domain\Deposit\ValueObjects\ConfirmationRequirement;
use App\Domain\Deposit\ValueObjects\DepositStatus;

final class CanBeCreditedPolicy
{
    /**
     * Чистая бизнес-проверка:
     * депозит можно кредитовать только если:
     * 1) он подтвержден;
     * 2) подтверждений достаточно;
     * 3) он ещё не кредитован.
     */
//    public function canBeCredited(Deposit $deposit, int $requiredConfirmations): bool
//    {
//        return $deposit->status()->is(DepositStatus::Confirmed)
//            && $deposit->confirmations() >= $requiredConfirmations
//            && ! $deposit->isCredited();
//
//    }
    public function canBeCredited(Deposit $deposit, ConfirmationRequirement $requirement): bool
    {
        if ($deposit->status() === DepositStatus::Credited) {
            return false;
        }

        if ($deposit->status() === DepositStatus::Failed) {
            return false;
        }

        if ($requirement->isBlocks()) {
            return $deposit->status() === DepositStatus::Confirmed
                && $deposit->confirmations() >= $requirement->requiredConfirmations;
        }

        if ($requirement->isFinality()) {
            return $deposit->status() === DepositStatus::Confirmed
                && $deposit->finalizedAt() !== null;
        }

        return false;
    }
}
