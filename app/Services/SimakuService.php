<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Pembungkus API endpoint SIMAKU.
 *
 * Setiap permintaan membawa Bearer JWT HS256 buatan sendiri (iss/aud/iat/exp,
 * berlaku 1 jam) yang ditandatangani SIMAKU_JWT_SECRET, sama seperti
 * App\Services\Httpsimaku di endpoint SITAMA.
 *
 * Balasan daftar memakai format PaginateCollection SIMAKU:
 * {code, messages, data[], recordsTotal, currentPage, lastPage, ...}.
 */
class SimakuService
{
  private const CACHE_REKENING = 'simaku.coa.level6';
  private const CACHE_UNIT = 'simaku.unit';

  /**
   * Rekening level 6 (GET coa/level6), seluruh halaman, disimpan di cache.
   * Setiap item: ['ma' => idrek6, 'kode' => '4.1.01.02.01.01', 'nama' => ...].
   */
  public function rekeningLevel6(): Collection
  {
    $menit = max(1, (int) config('simaku.cache_menit', 60));

    return Cache::remember(self::CACHE_REKENING, now()->addMinutes($menit), function () {
      $hasil = collect();
      $halaman = 1;

      do {
        $balasan = $this->get('coa/level6', ['page' => $halaman, 'limit' => 1000]);
        $hasil = $hasil->concat($balasan['data'] ?? []);
        $terakhir = (int) ($balasan['lastPage'] ?? 1);
        $halaman++;
      } while ($halaman <= $terakhir && $halaman <= 50);

      return $hasil
        ->map(fn ($item) => $this->rekening((array) $item))
        ->filter(fn ($r) => $r['ma'] !== '' && $r['nama'] !== '')
        ->unique('ma')
        ->sortBy('kode', SORT_NATURAL)
        ->values();
    });
  }

  /**
   * Baris jurnal SPM (GET journal/spm) untuk satu transaksi pembayaran.
   * Jurnal SITAMA ditulis per rincian struk: bukti = bak_tagihan_dtl.id,
   * key_bank = payment key (bak_tagihan.api_key = bak_kppmhs.key_tagihan).
   */
  public function jurnalSpm(array $filter): Collection
  {
    $balasan = $this->get('journal/spm', array_filter($filter, fn ($v) => $v !== null && $v !== '') + ['limit' => 100]);

    return collect($balasan['data'] ?? [])->map(fn ($r) => (object) $r);
  }

  /**
   * Daftar unit SIMAKU (GET unit), disimpan di cache. Hanya id_unit, kode,
   * nama, dan status yang disimpan (balasan endpoint juga memuat kolom lain).
   */
  public function unit(): Collection
  {
    $menit = max(1, (int) config('simaku.cache_menit', 60));

    return Cache::remember(self::CACHE_UNIT, now()->addMinutes($menit), function () {
      $balasan = $this->get('unit', ['limit' => 1000]);

      return collect($balasan['data'] ?? [])
        ->map(fn ($u) => [
          'id_unit' => (int) ($u['id_unit'] ?? 0),
          'kode' => trim((string) ($u['kode'] ?? '')),
          'nama' => trim(preg_replace('/\s+/', ' ', (string) ($u['nama'] ?? ''))),
          'status' => trim((string) ($u['status'] ?? '')),
        ])
        ->filter(fn ($u) => $u['id_unit'] > 0 && $u['nama'] !== '')
        ->sortBy('kode', SORT_NATURAL)
        ->values();
    });
  }

  /** Ubah unit (id_uk + kodeunit) jurnal SPM satu rincian pembayaran. */
  public function ubahUnitJurnal(string $keyBank, string $bukti, int $idUk): array
  {
    try {
      $balasan = $this->http()->put('journal/spm/unit', ['key_bank' => $keyBank, 'bukti' => $bukti, 'id_uk' => $idUk]);
    } catch (ConnectionException $e) {
      throw new RuntimeException('Tidak bisa terhubung ke SIMAKU: ' . $e->getMessage(), 0, $e);
    }

    if ($balasan->failed()) {
      $pesan = (string) ($balasan->json('messages') ?? $balasan->json('message') ?? $balasan->reason());
      Log::warning('SIMAKU PUT journal/spm/unit gagal', ['status' => $balasan->status(), 'pesan' => $pesan]);

      throw new RuntimeException("Sinkron unit gagal ({$balasan->status()}): {$pesan}");
    }

    return (array) $balasan->json('data');
  }

  public function lupakanRekening(): void
  {
    Cache::forget(self::CACHE_REKENING);
  }

  public function get(string $path, array $query = []): array
  {
    try {
      $balasan = $this->http()->get(ltrim($path, '/'), $query);
    } catch (ConnectionException $e) {
      throw new RuntimeException('Tidak bisa terhubung ke SIMAKU: ' . $e->getMessage(), 0, $e);
    }

    if ($balasan->failed()) {
      $pesan = (string) ($balasan->json('messages') ?? $balasan->json('message') ?? $balasan->reason());
      Log::warning("SIMAKU GET {$path} gagal", ['status' => $balasan->status(), 'pesan' => $pesan]);

      throw new RuntimeException("SIMAKU GET {$path} gagal ({$balasan->status()}): {$pesan}");
    }

    return $balasan->json() ?? [];
  }

  /**
   * Normalisasi satu rekening. Kode lengkap dirangkai dari koderek1..6
   * (bila dikirim datar atau bersarang rek5.rek4...), selain itu koderek6.
   */
  private function rekening(array $r): array
  {
    $kode = $r['kode'] ?? null;

    if (!$kode) {
      $bagian = [];
      $node = $r;

      // Bentuk bersarang: rek6 -> rek5 -> rek4 -> ... -> rek1.
      for ($level = 6; $level >= 1; $level--) {
        $bagian[$level] = $r["koderek{$level}"] ?? ($node["koderek{$level}"] ?? null);
        $node = (array) ($node['rek' . ($level - 1)] ?? []);
      }

      $bagian = array_filter(array_reverse($bagian, true), fn ($v) => $v !== null && $v !== '');
      $kode = count($bagian) === 6 ? implode('.', $bagian) : ($r['koderek6'] ?? '');
    }

    return [
      'ma' => (string) ($r['idrek6'] ?? $r['id'] ?? ''),
      'kode' => (string) $kode,
      'nama' => trim((string) ($r['namarek6'] ?? $r['nama'] ?? '')),
    ];
  }

  private function http(): PendingRequest
  {
    $url = trim((string) config('simaku.url'));
    $secret = (string) config('simaku.jwt_secret');

    if ($url === '' || $secret === '') {
      throw new RuntimeException('SIMAKU_URL / SIMAKU_JWT_SECRET belum diisi di .env.');
    }

    $http = Http::baseUrl(rtrim($url, '/') . '/')
      ->acceptJson()
      ->withToken($this->token($secret))
      ->timeout(config('simaku.timeout', 30))
      ->withOptions(['verify' => config('simaku.verify', true)]);

    if ($kunci = config('simaku.api_key')) {
      $http = $http->withHeaders(['X-API-KEY' => $kunci]);
    }

    return $http;
  }

  private function token(string $secret): string
  {
    $sekarang = time();

    return JWT::encode([
      'iss' => 'service sitama',
      'aud' => 'backend',
      'iat' => $sekarang,
      'exp' => $sekarang + 3600,
    ], $secret, 'HS256');
  }
}
