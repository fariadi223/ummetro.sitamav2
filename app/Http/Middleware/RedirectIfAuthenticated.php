<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                // Arahkan ke dasbor sesuai role. Jangan ke HOME ('/') karena
                // '/' dialihkan ke /login dan memicu redirect loop.
                $user = Auth::guard($guard)->user();

                return redirect(method_exists($user, 'homeRoute')
                    ? $user->homeRoute()
                    : route('admin.dashboard'));
            }
        }

        return $next($request);
    }
}
