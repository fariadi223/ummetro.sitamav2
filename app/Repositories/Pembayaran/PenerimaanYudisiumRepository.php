<?php

namespace App\Repositories\Pembayaran;

use App\Support\TahunAkademik;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Penerimaan (klik lunas) biaya yudisium mahasiswa.
 *
 * Port dari SITAMA v1 Admin::penerimaan_yudisium / yudisium_proses.
 * Tabel: bak_lns_yudisium (satu baris per NPM), tbl_mhs + tbl_sms.
 *
 * Tidak dipindahkan: pencarian mahasiswa lama di MSSQL (NPM angkatan ≤ 2015).
 */
class PenerimaanYudisiumRepository
{
  /* ------------------------------------------------------------------
   | Daftar pelunasan
   * ------------------------------------------------------------------ */

  private function query(array $f): Builder
  {
    $cari = strtolower(trim((string) ($f['search'] ?? '')));

    return DB::table('bak_lns_yudisium')
      ->when($f['psstudi'] ?? null, fn ($q, $v) => $q->where('psstudi', $v))
      ->when($f['dari'] ?? null, fn ($q, $v) => $q->whereDate('tanggal_lns', '>=', $v))
      ->when($f['sampai'] ?? null, fn ($q, $v) => $q->whereDate('tanggal_lns', '<=', $v))
      ->when($cari !== '', fn ($q) => $q->where(fn ($w) => $w
        ->whereRaw('LOWER(nama_mhs) LIKE ?', ["%{$cari}%"])
        ->orWhere('npm', 'like', "%{$cari}%")));
  }

  public function daftar(array $f): LengthAwarePaginator
  {
    return $this->query($f)
      ->orderByDesc('tanggal_lns')->orderBy('npm')
      ->paginate((int) ($f['limit'] ?? 25), ['psstudi', 'npm', 'nama_mhs', 'nama_prg', 'tanggal_lns'])
      ->withQueryString();
  }

  /** @return array{total: int, hari_ini: int, bulan_ini: int} */
  public function ringkasan(): array
  {
    $r = DB::table('bak_lns_yudisium')->selectRaw("
      COUNT(*) AS total,
      COUNT(*) FILTER (WHERE tanggal_lns = CURRENT_DATE) AS hari_ini,
      COUNT(*) FILTER (WHERE date_trunc('month', tanggal_lns) = date_trunc('month', CURRENT_DATE)) AS bulan_ini
    ")->first();

    return ['total' => (int) $r->total, 'hari_ini' => (int) $r->hari_ini, 'bulan_ini' => (int) $r->bulan_ini];
  }

  /** Prodi yang pernah ada pelunasan (kode_prodi => nama). */
  public function pilihanProdi(): array
  {
    return DB::table('tbl_sms')
      ->whereIn('kode_prodi', DB::table('bak_lns_yudisium')->select('psstudi')->distinct())
      ->orderBy('jenjang_didik')->orderBy('nm_lemb')
      ->get(['kode_prodi', 'jenjang_didik', 'nm_lemb'])
      ->mapWithKeys(fn ($p) => [trim($p->kode_prodi) => trim($p->jenjang_didik) . ' ' . trim($p->nm_lemb)])
      ->all();
  }

  /* ------------------------------------------------------------------
   | Mahasiswa (Admin::function_search_mhs)
   * ------------------------------------------------------------------ */

  private function mahasiswa(string $npm): ?object
  {
    $m = DB::table('tbl_mhs as t1')
      ->join('tbl_sms as t2', 't2.id_sms', '=', 't1.id_sms')
      ->where('t1.nipd', $npm)
      ->get(['t1.id_reg_pd', 't1.id_sms', 't1.nipd', 't1.nm_pd', 't1.mulai_smt', 't2.jenjang_didik', 't2.nm_lemb', 't2.kode_prodi']);

    return $m->count() === 1 ? $m->first() : null;
  }

  public function cariMahasiswa(string $npm, string $idSmt): ?array
  {
    $m = $this->mahasiswa($npm);
    if (!$m) {
      return null;
    }

    $lunas = DB::table('bak_lns_yudisium')->where('npm', trim($m->nipd))->value('tanggal_lns');

    return [
      'idreg' => $m->id_reg_pd,
      'npm' => trim($m->nipd),
      'nmmhs' => trim($m->nm_pd),
      'nmprg' => trim($m->jenjang_didik) . ' ' . trim($m->nm_lemb),
      'pstdi' => trim((string) $m->kode_prodi),
      'thmsk' => substr((string) $m->mulai_smt, 0, 4),
      'smmhs' => TahunAkademik::semesterMahasiswa(trim((string) $m->mulai_smt), $idSmt),
      'lunas' => $lunas,
    ];
  }

  /* ------------------------------------------------------------------
   | Klik lunas (Admin::yudisium_proses)
   * ------------------------------------------------------------------ */

  /** Data mahasiswa diambil ulang dari tbl_mhs, bukan dari isian form. */
  public function lunasi(string $npm): array
  {
    $m = $this->mahasiswa($npm) ?? throw new RuntimeException('Mahasiswa dengan NPM ini tidak ditemukan.');
    $npm = trim($m->nipd);

    if (DB::table('bak_lns_yudisium')->where('npm', $npm)->exists()) {
      throw new RuntimeException('Maaf, data sudah di-klik lunas.');
    }

    $data = [
      'psstudi' => trim((string) $m->kode_prodi),
      'npm' => $npm,
      'nama_mhs' => trim($m->nm_pd),
      'nama_prg' => trim($m->jenjang_didik) . ' ' . trim($m->nm_lemb),
      'tanggal_lns' => date('Y-m-d'),
    ];

    try {
      DB::table('bak_lns_yudisium')->insert($data);
    } catch (UniqueConstraintViolationException) {
      throw new RuntimeException('Maaf, data sudah di-klik lunas.');
    }

    return $data;
  }
}
