<?php

namespace App\Repositories\Laporan;

use App\Models\Kpp\KppDetailModel;
use App\Repositories\Tagihan\TagihanRepository;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Laporan rincian tagihan SPP mahasiswa.
 *
 * Port dari SITAMA v1 Report::rinci_tagihan / Ekpordata::rincian_tagihan
 * dan M_sistem::QueryTagihanDtl.
 * Tabel: bak_tagihan (struk), bak_tagihan_dtl (rincian), tbl_mhs, tbl_sms, bak_mak (nama MA).
 */
class LaporanSppRepository
{
  public const STATUS = ['0' => 'Belum Lunas', '1' => 'Lunas'];

  private function query(array $f): Builder
  {
    $npm = trim((string) ($f['npm'] ?? ''));

    return DB::table('bak_tagihan as t1')
      ->join('bak_tagihan_dtl as t2', 't2.id_tagihan', '=', 't1.id')
      ->join('tbl_mhs as t3', 't3.id_reg_pd', '=', 't1.id_reg_pd')
      ->join('tbl_sms as t4', 't4.id_sms', '=', 't3.id_sms')
      ->whereNotIn('t2.ma', TagihanRepository::MA_DISEMBUNYIKAN)
      ->where('t1.thnsmt', $f['id_smt'])
      ->when($f['id_sms'] ?? null, fn ($q, $v) => $q->where('t3.id_sms', $v))
      ->when($f['angkatan'] ?? null, fn ($q, $v) => $q->whereRaw('SUBSTRING(t3.mulai_smt, 1, 4) = ?', [$v]))
      ->when(isset($f['status']) && $f['status'] !== '', fn ($q) => $q->where('t1.lunas', (int) $f['status']))
      ->when($f['ma'] ?? null, fn ($q, $v) => $q->where('t2.ma', $v))
      ->when($npm !== '', fn ($q) => $q->where('t3.nipd', 'like', "{$npm}%"))
      // Rentang tanggal lunas; batas atas eksklusif (+1 hari) agar jam di hari terakhir ikut.
      ->when($f['dari'] ?? null, fn ($q, $v) => $q->where('t1.lunas_date', '>=', $v))
      ->when($f['sampai'] ?? null, fn ($q, $v) => $q->where('t1.lunas_date', '<', date('Y-m-d', strtotime("{$v} +1 day"))));
  }

  private function kolom(Builder $q): Builder
  {
    return $q->select([
      't2.id', 't1.id AS id_tagihan', 't1.thnsmt', DB::raw('t1.lunas::int AS lunas'), 't1.lunas_date', 't1.struk_id', 't1.api_key',
      't2.ma', 't2.uang', 't2.keterangan',
      't3.nipd', 't3.nm_pd', 't3.mulai_smt',
      't4.jenjang_didik', 't4.nm_lemb',
    ])
      ->orderBy('t4.jenjang_didik')->orderBy('t4.nm_lemb')->orderBy('t3.nipd')->orderBy('t2.id');
  }

  public function daftar(array $f): LengthAwarePaginator
  {
    $hasil = $this->kolom($this->query($f))->paginate((int) ($f['limit'] ?? 25))->withQueryString();
    $this->tautkanKpp($hasil->getCollection());

    return $hasil;
  }

  public function semua(array $f): Collection
  {
    return $this->kolom($this->query($f))->get();
  }

  /**
   * Ringkasan tanpa filter status, supaya angka lunas & belum lunas
   * selalu terlihat berdampingan.
   *
   * @return array{rincian: int, mahasiswa: int, total: int, lunas: int, belum: int}
   */
  public function ringkasan(array $f): array
  {
    $r = $this->query(array_diff_key($f, ['status' => 1]))->selectRaw('
      COUNT(*) AS rincian,
      COUNT(DISTINCT t1.id_reg_pd) AS mahasiswa,
      COALESCE(SUM(t2.uang), 0) AS total,
      COALESCE(SUM(t2.uang) FILTER (WHERE t1.lunas = 1), 0) AS lunas
    ')->first();

    return [
      'rincian' => (int) $r->rincian,
      'mahasiswa' => (int) $r->mahasiswa,
      'total' => (int) $r->total,
      'lunas' => (int) $r->lunas,
      'belum' => (int) $r->total - (int) $r->lunas,
    ];
  }

  /**
   * Isi kpp_dtl (PK KppDetailModel) pada baris laporan: rincian KPP hasil
   * pelunasan struk (bak_kppmhs.key_tagihan = bak_tagihan.api_key) dengan MA
   * yang sama. Satu query untuk satu halaman; baris belum lunas -> null.
   */
  private function tautkanKpp(Collection $rows): void
  {
    $kunci = $rows->pluck('api_key')->map(fn ($v) => trim((string) $v))->filter()->unique()->values();
    $peta = $kunci->isEmpty() ? collect() : DB::table('bak_kppmhs as k')
      ->join('bak_kppmhs_dtl as d', 'd.kppmhs', '=', 'k.id')
      ->whereIn('k.key_tagihan', $kunci)
      ->groupBy('k.key_tagihan', 'd.ma')
      ->selectRaw('k.key_tagihan, d.ma::bigint AS ma, MIN(d.kpp_dtl) AS kpp_dtl')
      ->get()
      ->mapWithKeys(fn ($r) => [trim($r->key_tagihan) . '|' . (int) $r->ma => (int) $r->kpp_dtl]);

    $rows->each(fn ($r) => $r->kpp_dtl = $peta[trim((string) $r->api_key) . '|' . (int) $r->ma] ?? null);
  }

  /**
   * Satu record rincian KPP (KppDetailModel, berdasarkan primary key kpp_dtl)
   * beserta induk bak_kppmhs, mahasiswa, dan struk asalnya.
   */
  public function detailKpp(int $kppDtl): ?object
  {
    $dtl = KppDetailModel::query()->find($kppDtl);

    if (!$dtl) {
      return null;
    }

    $induk = DB::table('bak_kppmhs as k')
      ->join('tbl_mhs as m', 'm.id_reg_pd', '=', 'k.id_reg_pd')
      ->join('tbl_sms as s', 's.id_sms', '=', 'm.id_sms')
      ->leftJoin('bak_bank as b', DB::raw('b.id::text'), '=', DB::raw('k.bank::text'))
      ->leftJoin('bak_tagihan as t', 't.api_key', '=', 'k.key_tagihan')
      // Rincian struk dengan MA yang sama: id-nya dipakai sebagai "bukti" jurnal SIMAKU.
      ->leftJoin('bak_tagihan_dtl as td', fn ($j) => $j->on('td.id_tagihan', '=', 't.id')->where('td.ma', $dtl->ma))
      ->where('k.id', $dtl->kppmhs)
      // Satu pembayaran bisa melunasi beberapa struk (api_key sama): utamakan struk yang memuat MA ini.
      ->orderByRaw('td.id IS NULL')
      ->first([
        'k.thn_smt', 'k.smt_mhs', 'k.angsur_ke', 'k.create_date', 'b.nm_bank',
        'm.nipd', 'm.nm_pd', 'm.mulai_smt', 's.id_sms', 's.jenjang_didik', 's.nm_lemb',
        'k.key_tagihan', 't.struk_id', 't.lunas_date', 'td.id AS id_rincian',
      ]);

    return (object) ['dtl' => $dtl, 'induk' => $induk];
  }

  public function pilihanProdi(): Collection
  {
    return DB::table('tbl_sms')
      ->whereNotNull('nm_lemb')
      ->orderBy('jenjang_didik')->orderBy('nm_lemb')
      ->get(['id_sms', 'jenjang_didik', 'nm_lemb']);
  }
}
