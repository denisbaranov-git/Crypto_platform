<?php

namespace App\Http\Controllers;

use App\Application\Identity\Commands\RegisterUserCommand;
use App\Application\Identity\Handlers\RegisterUserHandler;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class UserRegisterController extends Controller
{
    public function __construct(
        private RegisterUserHandler $handler
    ) {}

    public function __invoke(Request $request)
    {
        $data = $request->validate([
            'name' => ['required'],
            'email' => ['required','email'],
            'password' => ['required','min:8']
        ]);

        $user = $this->handler->handle(
            new RegisterUserCommand(
                $data['name'],
                $data['email'],
                $data['password']
            )
        );

        return response()->json(['id' => $user->id()->value()]);
    }
}
