<?php

namespace App\Repositories\Laporan;

use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Laporan rincian dispensasi pembayaran mahasiswa.
 *
 * Port dari SITAMA v1 Report::rinci_dispensasi / Ekpordata::rincian_dispensasi
 * dan M_sistem::QueryPotongan.
 *
 * Tabel bak_tagihan_potongan: satu baris per (id_reg_pd, thnsmt, ma).
 *  - uang_pokok    : biaya asli rekening tersebut
 *  - uang_potongan : dispensasi (selisih yang tidak ditagihkan); menjadi 0
 *                    setelah dispensasi dimasukkan kembali ke struk
 *  - tagihan       : uang_pokok - uang_potongan
 * Catatan: di v1 label kolom "Dispensasi" dan "Tagihan" tertukar.
 */
class LaporanDispensasiRepository
{
  public const STATUS = ['1' => 'Ada Dispensasi', '0' => 'Dispensasi 0'];

  private function query(array $f): Builder
  {
    $npm = trim((string) ($f['npm'] ?? ''));

    return DB::table('bak_tagihan_potongan as t1')
      ->join('tbl_mhs as t3', 't3.id_reg_pd', '=', 't1.id_reg_pd')
      ->join('tbl_sms as t4', 't4.id_sms', '=', 't3.id_sms')
      ->where('t1.thnsmt', $f['id_smt'])
      ->when($f['id_sms'] ?? null, fn ($q, $v) => $q->where('t3.id_sms', $v))
      ->when($f['angkatan'] ?? null, fn ($q, $v) => $q->whereRaw('SUBSTRING(t3.mulai_smt, 1, 4) = ?', [$v]))
      ->when($f['ma'] ?? null, fn ($q, $v) => $q->where('t1.ma', $v))
      ->when($npm !== '', fn ($q) => $q->where('t3.nipd', 'like', "{$npm}%"))
      ->when(($f['status'] ?? '') === '1', fn ($q) => $q->where('t1.uang_potongan', '>', 0))
      ->when(($f['status'] ?? '') === '0', fn ($q) => $q->where('t1.uang_potongan', '<=', 0));
  }

  private function kolom(Builder $q): Builder
  {
    return $q->select([
      't1.id_reg_pd', 't1.thnsmt', DB::raw('t1.ma::bigint AS ma'), 't1.uraian',
      't1.uang_pokok', 't1.uang_potongan', DB::raw('(t1.uang_pokok - t1.uang_potongan) AS tagihan'),
      DB::raw("(t1.post_piutang = B'1') AS terjurnal"),
      't3.nipd', 't3.nm_pd', 't3.mulai_smt',
      't4.jenjang_didik', 't4.nm_lemb',
    ])
      ->orderBy('t4.jenjang_didik')->orderBy('t4.nm_lemb')->orderBy('t3.nipd')->orderBy('t1.ma');
  }

  public function daftar(array $f): LengthAwarePaginator
  {
    return $this->kolom($this->query($f))->paginate((int) ($f['limit'] ?? 25))->withQueryString();
  }

  public function semua(array $f): Collection
  {
    return $this->kolom($this->query($f))->get();
  }

  /**
   * Ringkasan tanpa filter status, agar jumlah per tab selalu terlihat.
   *
   * @return array{rincian: int, mahasiswa: int, aktif: int, nol: int, pokok: int, potongan: int, tagihan: int}
   */
  public function ringkasan(array $f): array
  {
    $r = $this->query(array_diff_key($f, ['status' => 1]))->selectRaw('
      COUNT(*) AS rincian,
      COUNT(DISTINCT t1.id_reg_pd) AS mahasiswa,
      COUNT(*) FILTER (WHERE t1.uang_potongan > 0) AS aktif,
      COALESCE(SUM(t1.uang_pokok), 0) AS pokok,
      COALESCE(SUM(t1.uang_potongan), 0) AS potongan
    ')->first();

    return [
      'rincian' => (int) $r->rincian,
      'mahasiswa' => (int) $r->mahasiswa,
      'aktif' => (int) $r->aktif,
      'nol' => (int) $r->rincian - (int) $r->aktif,
      'pokok' => (int) $r->pokok,
      'potongan' => (int) $r->potongan,
      'tagihan' => (int) $r->pokok - (int) $r->potongan,
    ];
  }
}
