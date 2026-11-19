<?php

namespace App\Http\Middleware;

use App\Auth\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
  /**
   * Batasi akses route berdasarkan role user.
   *
   * Contoh pemakaian: ->middleware('role:admin') atau ->middleware('role:admin,prodi')
   */
  public function handle(Request $request, Closure $next, string ...$roles): Response
  {
    $user = $request->user();

    if (!$user) {
      return redirect($this->loginRoute($roles));
    }

    if (!$user->is_active) {
      $pintuMasuk = $user->loginRoute();

      auth()->logout();
      $request->session()->invalidate();
      $request->session()->regenerateToken();

      return redirect($pintuMasuk)->withErrors([
        $this->kolomError($roles) => 'Akun Anda tidak aktif. Hubungi administrator.',
      ]);
    }

    if (!$user->hasAnyRole($roles)) {
      abort(401, 'Anda tidak memiliki hak akses ke halaman ini.');
    }

    return $next($request);
  }

  /**
   * Pintu masuk sesuai area yang dijaga: area alumni memakai login publik,
   * area pengelola memakai login operator.
   */
  private function loginRoute(array $roles): string
  {
    return $this->areaAlumni($roles) ? route('login') : route('operator.login');
  }

  /**
   * Nama field yang dipakai form login terkait, supaya pesan error tampil
   * menempel pada input yang benar.
   */
  private function kolomError(array $roles): string
  {
    return $this->areaAlumni($roles) ? 'npm' : 'username';
  }

  private function areaAlumni(array $roles): bool
  {
    return $roles === [Role::ALUMNI];
  }
}
