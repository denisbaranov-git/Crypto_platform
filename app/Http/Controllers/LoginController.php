<?php

namespace App\Http\Controllers;

use App\Application\Identity\Commands\LoginUserCommand;
use App\Application\Identity\Handlers\LoginUserHandler;
use App\Http\Requests\LoginRequest;
use App\Infrastructure\Persistence\Eloquent\Mappers\UserMapper;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function __invoke(LoginRequest $request, LoginUserHandler $handler, UserMapper $mapper)
    {
        $data = $request->validated();

        $user = $handler->handle(
            new LoginUserCommand(
                $data['email'],
                $data['password']
            )
        );

        Auth::loginUsingId($user->id()->value());

        return redirect('/dashboard');
    }
}
