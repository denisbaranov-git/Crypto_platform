<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

class EloquentWithdrawalAttempt extends Model
{
    protected $table = 'withdrawal_attempts';

//    withdrawal_attempts
//  Одна заявка на вывод может иметь несколько попыток broadcast/retry:
//
//    retry без потери истории,
//    аудит, почему broadcast падал,
//    возможность видеть last successful tx,
//    recovery после worker crash,
//    нормальную поддержку stuck/failed транзакций.
//
//    withdrawals хранит бизнес-состояние.
//    withdrawal_attempts хранит техническую историю отправки в сеть.

//    withdrawals хранит бизнес-состояние.
//    withdrawal_attempts хранит техническую историю отправки в сеть.
}
