<?php

namespace App\Http\Controllers\authentications;

use App\Auth\SitamaUser;
use App\Http\Controllers\Controller;
use App\Services\SitamaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use RuntimeException;

class AuthController extends Controller
{
  /** Kunci session id administrator asli saat mode "login as". */
  public const KUNCI_IMPERSONASI = 'impersonasi_admin_id';

  /** Kunci session token endpoint SITAMA milik pengguna yang login. */
  public const KUNCI_TOKEN = 'sitama_token';

  /** Kunci session profil akun endpoint SITAMA yang login. */
  public const KUNCI_PROFIL = 'sitama_user';

  public function __construct(private SitamaService $sitama)
  {
  }

  /**
   * Halaman login utama aplikasi SITAMA.
   */
  public function showLogin(): View
  {
    return view('content.authentications.auth-login');
  }

  /**
   * Halaman login pengelola.
   */
  public function showOperatorLogin(): View
  {
    return view('content.authentications.auth-login-operator');
  }

  public function login(Request $request): RedirectResponse
  {
    return $this->attempt($request);
  }

  public function operatorLogin(Request $request): RedirectResponse
  {
    return $this->attempt($request);
  }

  /**
   * Username/password diperiksa ke endpoint SITAMA lewat SitamaService,
   * lalu profilnya disimpan di session sebagai user web (tanpa tabel users).
   */
  private function attempt(Request $request): RedirectResponse
  {
    $kredensial = $request->validate([
      'username' => ['required', 'string', 'max:191'],
      'password' => ['required', 'string', 'min:3'],
    ], [
      'username.required' => 'Username wajib diisi.',
      'password.required' => 'Password wajib diisi.',
    ]);

    try {
      $data = $this->sitama->authenticate($kredensial['username'], $kredensial['password']);
    } catch (RuntimeException $e) {
      Log::error('Login operator: endpoint SITAMA tidak dapat dihubungi', ['pesan' => $e->getMessage()]);

      return $this->gagal('Layanan login sedang tidak dapat dihubungi. Coba beberapa saat lagi.');
    }

    if ($data === null) {
      return $this->gagal('Username atau password salah, atau akun tidak memiliki akses.');
    }

    // Route dasbor per role endpoint.
    $role = [
      'pustik' => 'admin.dashboard',
      'adminpenerimaan' => 'operator.dashboard',
    ];

    // Endpoint mengirim role sebagai daftar objek {id, name, description, pivot};
    // yang dipakai hanya name-nya (string polos juga diterima).
    $roleEndpoint = collect((array) ($data['role'] ?? []))
      ->map(fn ($r) => is_array($r) ? ($r['name'] ?? null) : $r)
      ->filter(fn ($r) => is_string($r) && $r !== '')
      ->values()
      ->all();
    $tujuan = collect($roleEndpoint)->map(fn ($r) => $role[$r] ?? null)->filter()->first();

    if (!$tujuan) {
      return $this->gagal('Akun tidak memiliki akses ke aplikasi ini.');
    }

    $user = SitamaUser::dariEndpoint($kredensial['username'], (array) ($data['user'] ?? []), $roleEndpoint);

    $request->session()->regenerate();

    // Profil & token endpoint disimpan di session: profil dipakai
    // SitamaUserProvider untuk memulihkan user, token untuk memanggil API
    // atas nama pengguna ini.
    $request->session()->put(self::KUNCI_PROFIL, $user->toArray());
    $request->session()->put(self::KUNCI_TOKEN, $data['access_token']);

    Auth::login($user);

    return redirect()->route($tujuan);
  }

  public function logout(Request $request): RedirectResponse
  {
    $tujuan = $request->user()?->loginRoute() ?? route('operator.login');

    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect($tujuan);
  }

  private function gagal(string $pesan): RedirectResponse
  {
    return back()
      ->withInput(request()->only('username', 'remember'))
      ->withErrors(['username' => $pesan]);
  }
}
