<?php

namespace App\Http\Controllers\Api;

use App\Application\Identity\Commands\LoginUserCommand;
use App\Application\Identity\Handlers\LoginUserHandler;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;

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

        //$eloquentUser = EloquentUser::findOrFail($user->id()->value());

        $token = $eloquentUser->createToken('api')->plainTextToken;
        //Auth::loginUsingId($user->id()->value());

        return response()->json([
            'token' => $token
        ]);
    }
}
