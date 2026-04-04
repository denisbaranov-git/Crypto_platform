<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;

final class MeController extends Controller
{
    public function __invoke(Request $request): array
    {

        $user = $request->user();
        if(!$user) throw new AuthorizationException();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'status' => $user->status,
        ];
    }
}
