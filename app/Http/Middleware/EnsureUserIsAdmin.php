<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next)
    {
        if (!Auth::check() || !Auth::user()?->isAdmin()) {
            Auth::logout();

            return redirect('/')->with('error', 'Kamu bukan admin!');
        }

        return $next($request);
    }
}
