<?php

namespace App\Repositories\Laporan;

use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Laporan total tagihan per mahasiswa dalam satu tahun akademik.
 *
 * Port dari SITAMA v1 Report::total_tagihan / Ekpordata::total_tagihan,
 * M_sistem::QueryTagihanDtlG1 (total tagihan) dan M_sistem::FetchKppMhsSUM
 * (sudah dibayar). Di v1 "sudah dibayar" diambil per baris (N+1); di sini
 * digabung lewat satu subquery agregat bak_kppmhs per id_reg_pd.
 *
 * Tabel: bak_tagihan, bak_tagihan_dtl, bak_kppmhs, bak_kppmhs_dtl, tbl_mhs, tbl_sms.
 */
class LaporanTotalTagihanRepository
{
  public const STATUS = ['0' => 'Belum Lunas', '1' => 'Lunas'];

  /** Satu baris per mahasiswa: total tagihan, sudah dibayar (KPP), sisa. */
  private function query(array $f): Builder
  {
    $npm = trim((string) ($f['npm'] ?? ''));

    $tagihan = DB::table('bak_tagihan as t1')
      ->join('bak_tagihan_dtl as t2', 't2.id_tagihan', '=', 't1.id')
      ->join('tbl_mhs as t3', 't3.id_reg_pd', '=', 't1.id_reg_pd')
      ->where('t1.thnsmt', $f['id_smt'])
      ->when($f['id_sms'] ?? null, fn ($q, $v) => $q->where('t3.id_sms', $v))
      ->when($f['angkatan'] ?? null, fn ($q, $v) => $q->whereRaw('SUBSTRING(t3.mulai_smt, 1, 4) = ?', [$v]))
      ->when($npm !== '', fn ($q) => $q->where('t3.nipd', 'like', "{$npm}%"))
      ->groupBy('t1.id_reg_pd')
      ->selectRaw('t1.id_reg_pd, SUM(t2.uang)::bigint AS total');

    $dibayar = DB::table('bak_kppmhs as k')
      ->join('bak_kppmhs_dtl as d', 'd.kppmhs', '=', 'k.id')
      ->where('k.thn_smt', $f['id_smt'])
      ->groupBy('k.id_reg_pd')
      ->selectRaw('k.id_reg_pd, SUM(d.uang)::bigint AS dibayar');

    $baris = DB::query()
      ->fromSub($tagihan, 'tg')
      ->leftJoinSub($dibayar, 'kp', 'kp.id_reg_pd', '=', 'tg.id_reg_pd')
      ->join('tbl_mhs as m', 'm.id_reg_pd', '=', 'tg.id_reg_pd')
      ->join('tbl_sms as s', 's.id_sms', '=', 'm.id_sms')
      ->selectRaw('
        tg.id_reg_pd, m.nipd, m.nm_pd, m.mulai_smt, s.jenjang_didik, s.nm_lemb,
        tg.total, COALESCE(kp.dibayar, 0) AS dibayar, tg.total - COALESCE(kp.dibayar, 0) AS sisa
      ');

    return DB::query()->fromSub($baris, 'r')
      ->when(($f['status'] ?? '') === '0', fn ($q) => $q->where('r.sisa', '>', 0))
      ->when(($f['status'] ?? '') === '1', fn ($q) => $q->where('r.sisa', '<=', 0));
  }

  private function urut(Builder $q): Builder
  {
    return $q->select('r.*')
      ->orderBy('r.jenjang_didik')->orderBy('r.nm_lemb')->orderBy('r.nipd');
  }

  public function daftar(array $f): LengthAwarePaginator
  {
    return $this->urut($this->query($f))->paginate((int) ($f['limit'] ?? 25))->withQueryString();
  }

  public function semua(array $f): Collection
  {
    return $this->urut($this->query($f))->get();
  }

  /**
   * Rincian satu mahasiswa di satu semester: rincian struk tagihan dan
   * rincian pembayaran KPP yang membentuk angka total / dibayar di laporan.
   */
  public function rincian(string $idReg, string $idSmt): ?array
  {
    $mhs = DB::table('tbl_mhs as m')
      ->join('tbl_sms as s', 's.id_sms', '=', 'm.id_sms')
      ->where('m.id_reg_pd', $idReg)
      ->first(['m.nipd', 'm.nm_pd', 'm.mulai_smt', 's.jenjang_didik', 's.nm_lemb']);

    if (!$mhs) {
      return null;
    }

    $tagihan = DB::table('bak_tagihan as t1')
      ->join('bak_tagihan_dtl as t2', 't2.id_tagihan', '=', 't1.id')
      ->where('t1.id_reg_pd', $idReg)
      ->where('t1.thnsmt', $idSmt)
      ->orderBy('t1.input_date')->orderBy('t1.struk_id')->orderBy('t2.id')
      ->get([
        't1.struk_id', 't1.input_date', DB::raw('t1.lunas::int AS lunas'), 't1.lunas_date',
        DB::raw('t2.ma::bigint AS ma'), 't2.keterangan', 't2.uang',
      ]);

    $pembayaran = DB::table('bak_kppmhs as k')
      ->join('bak_kppmhs_dtl as d', 'd.kppmhs', '=', 'k.id')
      ->leftJoin('bak_bank as b', DB::raw('b.id::text'), '=', DB::raw('k.bank::text'))
      ->where('k.id_reg_pd', $idReg)
      ->where('k.thn_smt', $idSmt)
      ->orderBy('k.create_date')->orderBy('k.angsur_ke')->orderBy('d.kpp_dtl')
      ->get([
        'd.kpp_dtl', 'k.create_date', DB::raw('k.angsur_ke::int AS angsur_ke'), 'b.nm_bank',
        DB::raw('d.ma::bigint AS ma'), 'd.keterangan', 'd.uang',
      ]);

    return ['mahasiswa' => $mhs, 'tagihan' => $tagihan, 'pembayaran' => $pembayaran];
  }

  /**
   * Ringkasan tanpa filter status, agar angka lunas & belum lunas tampil berdampingan.
   *
   * @return array{mahasiswa: int, lunas_mhs: int, belum_mhs: int, total: int, dibayar: int, sisa: int}
   */
  public function ringkasan(array $f): array
  {
    $r = $this->query(array_diff_key($f, ['status' => 1]))->selectRaw('
      COUNT(*) AS mahasiswa,
      COUNT(*) FILTER (WHERE r.sisa <= 0) AS lunas_mhs,
      COALESCE(SUM(r.total), 0) AS total,
      COALESCE(SUM(r.dibayar), 0) AS dibayar,
      COALESCE(SUM(r.sisa) FILTER (WHERE r.sisa > 0), 0) AS sisa
    ')->first();

    return [
      'mahasiswa' => (int) $r->mahasiswa,
      'lunas_mhs' => (int) $r->lunas_mhs,
      'belum_mhs' => (int) $r->mahasiswa - (int) $r->lunas_mhs,
      'total' => (int) $r->total,
      'dibayar' => (int) $r->dibayar,
      'sisa' => (int) $r->sisa,
    ];
  }
}
