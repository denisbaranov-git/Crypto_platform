<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        $user = $this->loginHandler->handle(...);

        Auth::login($eloquentUser);

        return redirect('/dashboard');
    }
}
