<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Dashboard', [
            'user' => $request->user(),
            'stats' => [
                'total_orders' => $request->user()->orders()->count(),
                'total_spent' => $request->user()->orders()->sum('total'),
            ]
        ]);
    }
}
