<?php

namespace App\Http\Controllers;

use App\Application\Identity\Commands\LoginUserCommand;
use App\Application\Identity\Handlers\LoginUserHandler;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Infrastructure\Persistence\Eloquent\Repositories\UserMapper;
use Illuminate\Http\Request;
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
