<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Application\Identity\Handlers\LoginUserHandler;
use App\Application\Identity\Handlers\RegisterUserHandler;
use App\Application\Identity\Commands\LoginUserCommand;
use App\Application\Identity\Commands\RegisterUserCommand;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class AuthController extends Controller
{
    public function login(LoginRequest $request, LoginUserHandler $handler): JsonResponse
    {
        $data = $request->validated();

        /** @var EloquentUser $eloquentUser */
        $eloquentUser = $handler->handle(
            new LoginUserCommand($data['email'], $data['password'])
        );

        // Web/session login.
        Auth::login($eloquentUser);

        // Защита от session fixation.
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

    public function register(RegisterRequest $request, RegisterUserHandler $handler): JsonResponse
    {
        $data = $request->validated();

        /** @var EloquentUser $eloquentUser */
        $eloquentUser = $handler->handle(
            new RegisterUserCommand($data['name'], $data['email'], $data['password'])
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
        ], 201);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['ok' => true]);
    }
}
