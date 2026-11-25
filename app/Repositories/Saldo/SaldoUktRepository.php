<?php

namespace App\Repositories\Saldo;

use App\Support\TahunAkademik;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Saldo UKT / beasiswa mahasiswa (port SITAMA v1 /admin/saldoukt).
 *
 * Sumber v1: Admin::saldoukt*, Ekpordata::saldo_spp / rincian_kip_saldo,
 * M_sistem::QuerySaldoUKT(Group), SUMDebetSaldo, SUMKreditSaldo.
 *
 * Tabel bak_saldoukt berisi mutasi saldo:
 *  - debet  = saldo masuk (beasiswa UKT/KIP) yang ditambahkan operator di halaman ini;
 *  - kredit = pemakaian saldo, ditulis proses KPP (id_kpp, id_rek, uraian terisi).
 *
 * Perbedaan dengan v1:
 *  - Total debet/kredit per mahasiswa diambil lewat satu query agregat (v1: 2 query per baris).
 *  - Ada kolom sisa saldo, filter angkatan/jenis/status, dan ringkasan.
 *  - Hapus hanya untuk baris debet dan ditolak bila saldo sudah terpakai melebihi sisa.
 */
class SaldoUktRepository
{
  /** id_jns_saldo => nama. 0 = data lama tanpa jenis. */
  public const JENIS = [2 => 'Beasiswa KIP', 1 => 'Beasiswa UKT', 0 => 'Saldo lain'];

  /** Jenis yang boleh dipilih saat menambah saldo (sama seperti v1). */
  public const JENIS_INPUT = [2 => 'Beasiswa KIP', 1 => 'Beasiswa UKT'];

  public const STATUS = ['1' => 'Masih ada sisa', '0' => 'Habis'];

  /* ------------------------------------------------------------------
   | Daftar per mahasiswa
   * ------------------------------------------------------------------ */

  private function query(array $f): Builder
  {
    $npm = trim((string) ($f['npm'] ?? ''));
    $jenis = $f['jenis'] ?? null;

    $agregat = DB::table('bak_saldoukt as s')
      ->groupBy('s.id_reg_pd')
      ->when($jenis !== null && $jenis !== '', fn ($q) => $q->havingRaw(
        'bool_or(s.debet > 0 AND COALESCE(s.id_jns_saldo, 0) = ?)', [(int) $jenis]
      ))
      ->selectRaw("
        s.id_reg_pd,
        COALESCE(SUM(s.debet), 0)::bigint AS saldo,
        COALESCE(SUM(s.kredit), 0)::bigint AS digunakan,
        STRING_AGG(DISTINCT COALESCE(s.id_jns_saldo, 0)::text, ',') FILTER (WHERE s.debet > 0) AS jenis,
        MAX(s.id) AS terakhir
      ");

    $baris = DB::query()
      ->fromSub($agregat, 'a')
      ->join('tbl_mhs as m', 'm.id_reg_pd', '=', 'a.id_reg_pd')
      ->join('tbl_sms as p', 'p.id_sms', '=', 'm.id_sms')
      ->whereNotNull('m.nipd')
      ->when($f['id_sms'] ?? null, fn ($q, $v) => $q->where('m.id_sms', $v))
      ->when($f['angkatan'] ?? null, fn ($q, $v) => $q->whereRaw('SUBSTRING(m.mulai_smt, 1, 4) = ?', [$v]))
      ->when($npm !== '', fn ($q) => $q->where(fn ($w) => $w
        ->where('m.nipd', 'like', "{$npm}%")
        ->orWhereRaw('m.nm_pd ILIKE ?', ['%' . $npm . '%'])))
      ->selectRaw('
        a.id_reg_pd, m.nipd, m.nm_pd, m.mulai_smt, p.jenjang_didik, p.nm_lemb,
        a.saldo, a.digunakan, a.saldo - a.digunakan AS sisa, a.jenis, a.terakhir
      ');

    return DB::query()->fromSub($baris, 'r')
      ->when(($f['status'] ?? '') === '1', fn ($q) => $q->where('r.sisa', '>', 0))
      ->when(($f['status'] ?? '') === '0', fn ($q) => $q->where('r.sisa', '<=', 0));
  }

  /** Terbaru diubah lebih dulu, supaya saldo yang baru ditambahkan langsung terlihat. */
  public function daftar(array $f): LengthAwarePaginator
  {
    return $this->query($f)->select('r.*')
      ->orderByDesc('r.terakhir')
      ->paginate((int) ($f['limit'] ?? 25))->withQueryString();
  }

  /** Untuk ekspor: urut prodi lalu NPM seperti laporan v1. */
  public function semua(array $f): Collection
  {
    return $this->query($f)->select('r.*')
      ->orderBy('r.jenjang_didik')->orderBy('r.nm_lemb')->orderBy('r.nipd')
      ->get();
  }

  /**
   * Ringkasan tanpa filter status agar jumlah "ada sisa" dan "habis" tampil berdampingan.
   *
   * @return array{mahasiswa: int, ada_sisa: int, habis: int, saldo: int, digunakan: int, sisa: int}
   */
  public function ringkasan(array $f): array
  {
    $r = $this->query(array_diff_key($f, ['status' => 1]))->selectRaw('
      COUNT(*) AS mahasiswa,
      COUNT(*) FILTER (WHERE r.sisa > 0) AS ada_sisa,
      COALESCE(SUM(r.saldo), 0) AS saldo,
      COALESCE(SUM(r.digunakan), 0) AS digunakan,
      COALESCE(SUM(r.sisa) FILTER (WHERE r.sisa > 0), 0) AS sisa
    ')->first();

    return [
      'mahasiswa' => (int) $r->mahasiswa,
      'ada_sisa' => (int) $r->ada_sisa,
      'habis' => (int) $r->mahasiswa - (int) $r->ada_sisa,
      'saldo' => (int) $r->saldo,
      'digunakan' => (int) $r->digunakan,
      'sisa' => (int) $r->sisa,
    ];
  }

  /* ------------------------------------------------------------------
   | Rincian mutasi satu mahasiswa (Admin::saldoukt_npm_result)
   * ------------------------------------------------------------------ */

  /**
   * @return array{mahasiswa: object, mutasi: Collection, saldo: int, digunakan: int, sisa: int}|null
   */
  public function rincian(string $idReg): ?array
  {
    $mhs = DB::table('tbl_mhs as m')
      ->join('tbl_sms as p', 'p.id_sms', '=', 'm.id_sms')
      ->where('m.id_reg_pd', $idReg)
      ->first(['m.id_reg_pd', 'm.nipd', 'm.nm_pd', 'm.mulai_smt', 'p.jenjang_didik', 'p.nm_lemb']);

    if (!$mhs) {
      return null;
    }

    // Urutan input (id) = urutan mutasi, sama seperti v1; saldo berjalan dihitung di sini.
    $berjalan = 0;
    $mutasi = DB::table('bak_saldoukt')
      ->where('id_reg_pd', $idReg)
      ->orderBy('id')
      ->get(['id', 'id_jns_saldo', 'debet', 'kredit', 'smt', 'sem', 'id_kpp', 'uraian'])
      ->map(function ($r) use (&$berjalan) {
        $debet = (int) round((float) $r->debet);
        $kredit = (int) round((float) $r->kredit);
        $berjalan += $debet - $kredit;
        $smt = trim((string) $r->smt);
        $uraian = trim((string) $r->uraian);

        return (object) [
          'id' => (int) $r->id,
          'smt' => $smt,
          'semester' => preg_match('/^\d{4}[12]$/', $smt) ? TahunAkademik::nama($smt) : $smt,
          'sem' => trim((string) $r->sem),
          'jenis' => $debet > 0 ? (self::JENIS[(int) $r->id_jns_saldo] ?? self::JENIS[0]) : null,
          'uraian' => $uraian !== '' ? $uraian : ($debet > 0 ? 'Saldo masuk' : 'Pemakaian saldo'),
          'debet' => $debet,
          'kredit' => $kredit,
          'saldo' => $berjalan,
          'kpp' => $r->id_kpp !== null,
          'bisa_hapus' => $debet > 0 && $kredit === 0,
        ];
      });

    $saldo = (int) $mutasi->sum('debet');
    $digunakan = (int) $mutasi->sum('kredit');

    return [
      'mahasiswa' => $mhs,
      'mutasi' => $mutasi,
      'saldo' => $saldo,
      'digunakan' => $digunakan,
      'sisa' => $saldo - $digunakan,
    ];
  }

  /** Ringkas saldo + apakah sudah ada saldo masuk pada semester $idSmt (untuk form tambah). */
  public function posisi(string $idReg, string $idSmt): array
  {
    $r = DB::table('bak_saldoukt')
      ->where('id_reg_pd', $idReg)
      ->selectRaw('
        COALESCE(SUM(debet), 0)::bigint AS saldo,
        COALESCE(SUM(kredit), 0)::bigint AS digunakan,
        COUNT(*) FILTER (WHERE debet > 0 AND smt = ?) AS masuk_smt
      ', [$idSmt])
      ->first();

    return [
      'saldo' => (int) $r->saldo,
      'digunakan' => (int) $r->digunakan,
      'sisa' => (int) $r->saldo - (int) $r->digunakan,
      'masuk_smt' => (int) $r->masuk_smt,
    ];
  }

  /* ------------------------------------------------------------------
   | Tambah & hapus (Admin::saldoukt_simpan / saldoukt_delete)
   * ------------------------------------------------------------------ */

  public function tambah(string $idReg, int $jenis, int $jumlah, string $idSmt, int $semMhs): int
  {
    if (!DB::table('tbl_mhs')->where('id_reg_pd', $idReg)->exists()) {
      throw new RuntimeException('Mahasiswa tidak ditemukan.');
    }

    return (int) DB::table('bak_saldoukt')->insertGetId([
      'id_reg_pd' => $idReg,
      'id_jns_saldo' => $jenis,
      'debet' => $jumlah,
      'kredit' => 0,
      'smt' => $idSmt,
      'sem' => (string) $semMhs,
      'uraian' => self::JENIS[$jenis] . ' ' . TahunAkademik::nama($idSmt),
    ]);
  }

  /**
   * Hapus satu saldo masuk. Ditolak bila baris itu pemakaian (kredit), atau bila
   * tanpa baris itu saldo yang sudah terpakai melebihi saldo masuk (sisa jadi minus).
   *
   * @return string id_reg_pd pemilik saldo
   */
  public function hapus(int $id): string
  {
    return DB::transaction(function () use ($id) {
      $row = DB::table('bak_saldoukt')->where('id', $id)->lockForUpdate()->first();

      if (!$row) {
        throw new RuntimeException('Data saldo tidak ditemukan.');
      }
      if ((float) $row->kredit > 0 || (float) $row->debet <= 0) {
        throw new RuntimeException('Hanya saldo masuk yang dapat dihapus; pemakaian saldo berasal dari KPP.');
      }

      $posisi = DB::table('bak_saldoukt')
        ->where('id_reg_pd', $row->id_reg_pd)
        ->lockForUpdate()
        ->get(['debet', 'kredit']);
      $sisa = $posisi->sum(fn ($r) => (float) $r->debet - (float) $r->kredit) - (float) $row->debet;

      if ($sisa < 0) {
        throw new RuntimeException(
          'Saldo ini sudah terpakai. Menghapusnya membuat sisa saldo minus Rp '
          . number_format(-$sisa, 0, ',', '.') . '.'
        );
      }

      DB::table('bak_saldoukt')->where('id', $id)->delete();

      return (string) $row->id_reg_pd;
    });
  }
}
