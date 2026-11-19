<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Arahkan user yang belum login ke pintu masuk yang sesuai:
     * area pengelola ke login operator, sisanya ke login alumni.
     */
    protected function redirectTo(Request $request): ?string
    {
        if ($request->expectsJson()) {
            return null;
        }

        return $request->is('admin', 'admin/*', 'prodi', 'prodi/*', 'operator/*')
            ? route('operator.login')
            : route('login');
    }
}
