<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Pembungkus API endpoint SITAMA (project endpoint.ummetro.sitama).
 *
 * login() menukar SITAMA_USERNAME / SITAMA_PASSWORD dengan JWT lewat
 * POST auth/login. Token disimpan di cache selama masa berlakunya
 * (expires_in, dalam menit) supaya tidak login di setiap permintaan.
 *
 * get() / post() / put() menyertakan token sebagai Bearer. Bila endpoint
 * membalas 401 (token kedaluwarsa / dicabut), token diambil ulang sekali
 * lalu permintaan diulang.
 *
 * Semua balasan endpoint memakai amplop {code, messages, errors, data};
 * method ini mengembalikan amplop itu utuh sebagai array.
 */
class SitamaService
{
  /**
   * Login ke endpoint dan kembalikan access token.
   */
  public function login(bool $paksaBaru = false): string
  {
    $cacheKey = config('sitama.cache_key', 'sitama.token');

    if (!$paksaBaru && ($token = Cache::get($cacheKey))) {
      return $token;
    }

    $username = (string) config('sitama.username');
    $password = (string) config('sitama.password');

    if ($username === '' || $password === '') {
      throw new RuntimeException('SITAMA_USERNAME / SITAMA_PASSWORD belum diisi di .env.');
    }

    $data = $this->authenticate($username, $password);

    if ($data === null) {
      throw new RuntimeException('Login SITAMA gagal: username/password akun layanan ditolak.');
    }

    // expires_in dari endpoint dalam menit (JWT_TTL). Disimpan 5 menit lebih
    // pendek agar token tidak dipakai tepat saat kedaluwarsa.
    $menit = max(1, (int) ($data['expires_in'] ?? 60) - 5);
    Cache::put($cacheKey, $data['access_token'], now()->addMinutes($menit));

    return $data['access_token'];
  }

  /**
   * Periksa username/password ke endpoint (POST auth/login).
   *
   * Mengembalikan isi `data` balasan (access_token, expires_in, user, role)
   * bila diterima, atau null bila kredensial ditolak (401/422). Gangguan
   * lain (jaringan, 5xx) dilempar sebagai RuntimeException supaya pemanggil
   * bisa membedakan "password salah" dari "layanan mati".
   *
   * Tidak menyimpan apa pun ke cache: dipakai juga untuk login pengguna.
   */
  public function authenticate(string $username, string $password): ?array
  {
    $balasan = $this->kirim(
      fn () => $this->http()->post('auth/login', [
        'username' => $username,
        'password' => $password,
      ])
    );

    // Endpoint membalas 400 untuk validasi gagal dan 401 untuk kredensial
    // salah / role tidak diizinkan.
    if (in_array($balasan->status(), [400, 401, 422], true)) {
      return null;
    }

    $data = $balasan->json('data');

    if (!$balasan->successful() || !is_array($data) || empty($data['access_token'])) {
      throw new RuntimeException('Login SITAMA gagal: ' . $this->pesan($balasan));
    }

    return $data;
  }

  /**
   * Hapus token tersimpan; login berikutnya akan meminta token baru.
   */
  public function logout(): void
  {
    Cache::forget(config('sitama.cache_key', 'sitama.token'));
  }

  public function get(string $path, array $query = []): array
  {
    return $this->panggil('get', $path, $query);
  }

  public function post(string $path, array $data = []): array
  {
    return $this->panggil('post', $path, $data);
  }

  public function put(string $path, array $data = []): array
  {
    return $this->panggil('put', $path, $data);
  }

  /**
   * Kirim permintaan ber-token; ulangi sekali dengan token baru bila 401.
   */
  private function panggil(string $method, string $path, array $data): array
  {
    $path = ltrim($path, '/');

    $balasan = $this->kirim(
      fn () => $this->http()->withToken($this->login())->{$method}($path, $data)
    );

    if ($balasan->status() === 401) {
      $token = $this->login(true);
      $balasan = $this->kirim(
        fn () => $this->http()->withToken($token)->{$method}($path, $data)
      );
    }

    if ($balasan->failed()) {
      Log::warning('SITAMA ' . strtoupper($method) . " {$path} gagal", [
        'status' => $balasan->status(),
        'pesan' => $this->pesan($balasan),
      ]);

      throw new RuntimeException(
        'SITAMA ' . strtoupper($method) . " {$path} gagal ({$balasan->status()}): " . $this->pesan($balasan)
      );
    }

    return $balasan->json() ?? [];
  }

  private function http(): PendingRequest
  {
    $url = trim((string) config('sitama.url'));

    if ($url === '') {
      throw new RuntimeException('SITAMA_URL belum diisi di .env.');
    }

    return Http::baseUrl(rtrim($url, '/') . '/')
      ->acceptJson()
      ->asJson()
      ->timeout(config('sitama.timeout', 30))
      ->withOptions(['verify' => config('sitama.verify', true)]);
  }

  /**
   * Jalankan permintaan dan ubah gangguan jaringan menjadi pesan yang jelas.
   */
  private function kirim(callable $permintaan): Response
  {
    try {
      return $permintaan();
    } catch (ConnectionException $e) {
      throw new RuntimeException('Tidak bisa terhubung ke SITAMA: ' . $e->getMessage(), 0, $e);
    }
  }

  private function pesan(Response $balasan): string
  {
    return (string) ($balasan->json('messages') ?? $balasan->reason());
  }
}
