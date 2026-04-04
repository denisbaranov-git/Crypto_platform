<?php

namespace App\Http\Controllers\Api;

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

final class MobileAuthController extends Controller
{
    public function login(LoginRequest $request, LoginUserHandler $handler): JsonResponse
    {
        $data = $request->validated();

        $eloquentUser = $handler->handle(
            new LoginUserCommand($data['email'], $data['password'])
        );

        $token = $eloquentUser->createToken(
            name: $request->header('X-Device-Name', 'mobile'),
            abilities: ['read', 'wallets:read', 'deposits:read', 'withdrawals:read']
        )->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $eloquentUser->id,
                'name' => $eloquentUser->name,
                'email' => $eloquentUser->email,
            ],
        ]);
            //        if (Auth::attempt($request->only('email', 'password'))) {
            //            $user = Auth::user();
            //            $token = $user->createToken('auth-token')->plainTextToken;
            //
            //            return response()->json(['token' => $token]);
            //        }
            //
            //        return response()->json(['message' => 'Invalid credentials'], 401);
    }

    public function register(RegisterRequest $request, RegisterUserHandler $handler): JsonResponse
    {
        $data = $request->validated();

        $eloquentUser = $handler->handle(
            new RegisterUserCommand($data['name'], $data['email'], $data['password'])
        );

        //$token = $eloquentUser->createToken('mobile')->plainTextToken;
        $token = $eloquentUser->createToken(
            name: $request->header('X-Device-Name', 'mobile'),
            abilities: ['read', 'wallets:read', 'deposits:read', 'withdrawals:read']
        )->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $eloquentUser->id,
                'name' => $eloquentUser->name,
                'email' => $eloquentUser->email,
            ],
        ], 201);
    }

    public function logout(Request $request): JsonResponse
    {
        // Отзываем текущий токен.
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['ok' => true]);
    }
}
