<?php

namespace App\Http\Controllers\Web;

use App\Application\Identity\Commands\LoginUserCommand;
use App\Application\Identity\Handlers\LoginUserHandler;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function __invoke(LoginRequest $request, LoginUserHandler $handler)
    {
        $data = $request->validated();

        $eloquentUser = $handler->handle(
            new LoginUserCommand(
                $data['email'],
                $data['password']
            )
        );

        Auth::login($eloquentUser);

        $request->session()->regenerate();

        return response()->json([
            'ok' => true,
            'user' => [
                'id' => $eloquentUser->id,
                'name' => $eloquentUser->name,
                'email' => $eloquentUser->email,
            ],
        ]);
    }
}
