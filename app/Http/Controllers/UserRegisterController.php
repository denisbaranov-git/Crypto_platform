<?php

namespace App\Http\Controllers;

use App\Application\Identity\Commands\RegisterUserCommand;
use App\Application\Identity\Handlers\RegisterUserHandler;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use Illuminate\Http\Request;

class UserRegisterController extends Controller
{
    public function __invoke(RegisterRequest $request, RegisterUserHandler $handler)
    {
        $data = $request->validated();

        $user = $handler->handle(
            new RegisterUserCommand(
                $data['name'],
                $data['email'],
                $data['password']
            )
        );
        return response()->json(['id' => $user->id()->value()], 201);
        //return response()->json(['id' => $user->id()->value()]);
    }
}
