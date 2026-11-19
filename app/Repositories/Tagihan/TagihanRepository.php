<?php

namespace App\Repositories\Tagihan;

use App\Models\Mak\MataAnggaranModel as BiayaModel;
use App\Models\Pembayaran\LunasSppModel;
use App\Models\Tagihan\SppModel;
use App\Models\Tagihan\SppRinciModel;
use App\Support\TahunAkademik;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Pembuatan struk tagihan mahasiswa.
 *
 * Port dari SITAMA v1 Admin::tagihan* / dispen* / prasarat* dan M_sistem.
 * Tabel: bak_tagihan (struk), bak_tagihan_dtl (rincian), bak_tagihan_potongan
 * (dispensasi), bak_lns (status prasarat), bak_mak (biaya), tbl_nil + tbl_mk (KRS).
 *
 * Belum dipindahkan: pengecekan / jurnal balik piutang di SIMAKU
 * (ueu_tbl_jurnal_spm). Sebagai gantinya rincian dengan post_piutang = 1
 * dianggap sudah dijurnal dan tidak bisa dihapus.
 */
class TagihanRepository
{
  /** MA "SPP per SKS" — selalu dihitung per SKS (v1: Admin::$mak1). */
  public const MA_SKS = 746;

  /** MA yang disembunyikan dari rincian struk & KPP (v1: "SEMENTARA"). */
  public const MA_DISEMBUNYIKAN = [843, 841];

  public const SARAT = [
    1 => 'Ent KRS',
    2 => 'Cet KPU',
    0 => 'Non',
  ];

  /* ------------------------------------------------------------------
   | Mahasiswa
   * ------------------------------------------------------------------ */

  /** Admin::search_mhs. Hanya mahasiswa di tbl_mhs (bukan data MSSQL lama). */
  public function cariMahasiswa(string $npm, string $idSmt): ?array
  {
    $m = DB::table('tbl_mhs as t1')
      ->join('tbl_sms as t2', 't2.id_sms', '=', 't1.id_sms')
      ->where('t1.nipd', $npm)
      ->select('t1.id_reg_pd', 't1.id_sms', 't1.nipd', 't1.nm_pd', 't1.mulai_smt', 't2.jenjang_didik', 't2.nm_lemb', 't2.kode_prodi')
      ->get();

    if ($m->count() !== 1) {
      return null;
    }

    $m = $m->first();

    return [
      'idreg' => $m->id_reg_pd,
      'idsms' => $m->id_sms,
      'npm' => trim($m->nipd),
      'nmmhs' => trim($m->nm_pd),
      'nmprg' => trim($m->jenjang_didik) . ' ' . trim($m->nm_lemb),
      'thmsk' => substr((string) $m->mulai_smt, 0, 4),
      'mulai' => trim((string) $m->mulai_smt),
      'smmhs' => TahunAkademik::semesterMahasiswa(trim((string) $m->mulai_smt), $idSmt),
    ];
  }

  private function mahasiswa(string $idReg, ?string $idSms = null): ?object
  {
    return DB::table('tbl_mhs')
      ->where('id_reg_pd', $idReg)
      ->when($idSms, fn ($q) => $q->where('id_sms', $idSms))
      ->first(['id_reg_pd', 'id_sms', 'nipd', 'nm_pd', 'mulai_smt']);
  }

  /* ------------------------------------------------------------------
   | Proses struk (Admin::tagihan_proses)
   * ------------------------------------------------------------------ */

  /**
   * Buat / perbarui struk belum lunas untuk mahasiswa, semester, dan sarat.
   *
   * @return array{id: string, nama: string}
   * @throws RuntimeException bila prasarat tidak terpenuhi
   */
  public function prosesStruk(string $idReg, string $idSms, string $idSmt, int $sarat, int $smMhs): array
  {
    $mhs = $this->mahasiswa($idReg, $idSms) ?? throw new RuntimeException('Mahasiswa tidak ditemukan.');

    $this->cekPrasarat($mhs, $idSmt, $sarat, $smMhs);

    return DB::transaction(function () use ($mhs, $idSmt, $sarat, $smMhs) {
      $data = [
        'id_reg_pd' => $mhs->id_reg_pd,
        'thn_akad' => substr(TahunAkademik::nama($idSmt), 0, 9),
        'smt_akad' => (int) substr($idSmt, 4, 1),
        'thn_tghn' => date('Y'),
        'bln_tghn' => (int) date('m'),
        'sarat' => $sarat,
        'input_date' => date('Y-m-d'),
        'mhssmt' => $smMhs,
        'thnsmt' => $idSmt,
        'lunas' => 0,
      ];

      // Struk belum lunas dengan semester & sarat sama dipakai ulang.
      $struk = SppModel::query()
        ->where(['id_reg_pd' => $mhs->id_reg_pd, 'thnsmt' => $idSmt, 'sarat' => $sarat, 'lunas' => 0])
        ->orderByDesc('struk_id')
        ->first();

      if ($struk) {
        $struk->update($data);
      } else {
        $struk = SppModel::create($data);
      }

      // Bersihkan struk belum lunas lain yang tidak punya rincian.
      SppModel::query()
        ->where('id_reg_pd', $mhs->id_reg_pd)
        ->where('id', '!=', $struk->id)
        ->where('lunas', 0)
        ->whereNotExists(fn ($q) => $q->from('bak_tagihan_dtl')->whereColumn('bak_tagihan_dtl.id_tagihan', 'bak_tagihan.id'))
        ->delete();

      return ['id' => $struk->id, 'nama' => trim($mhs->nm_pd)];
    });
  }

  /**
   * Pengecekan prasarat v1, dipertahankan apa adanya:
   * - sarat 1 (Ent KRS): bak_lns semester sebelumnya, lunas_sks = 1 bila bukan semester 1;
   * - sarat 2 (Cet KPU): bak_lns semester ini, lunas_reg = 1 bila bukan semester 1;
   * - bila tidak ada & mahasiswa tidak cuti, struk hanya ditolak saat syarat
   *   registrasi ulang PMB (20251, sarat 1) belum lunas — sama seperti v1.
   */
  private function cekPrasarat(object $mhs, string $idSmt, int $sarat, int $smMhs): void
  {
    if ($sarat === 0) {
      return;
    }

    $regUlangOk = true;
    if ($idSmt === '20251' && $sarat === 1) {
      $regUlangOk = (int) DB::table('tbl_mhsmaru_register')
        ->where('nipd_register', trim($mhs->nipd))
        ->whereRaw("lunas = '1'")
        ->value('registrasi_uang') > 0;
    }

    $pesan = 'Error Prasarat';
    $kunci = ['id_reg_pd' => $mhs->id_reg_pd, 'id_sms' => $mhs->id_sms, 'id_smt' => $idSmt];

    if ($sarat === 1) {
      $kunci['id_smt'] = $smMhs > 1 ? TahunAkademik::idSmtKe(trim((string) $mhs->mulai_smt), $smMhs - 1) : $idSmt;
      if ($smMhs !== 1) {
        $kunci['lunas_sks'] = 1;
        $pesan .= ': Prasarat Pelunasan Cetak KHS';
      }
    }

    if ($sarat === 2 && $smMhs !== 1) {
      $kunci['lunas_reg'] = 1;
      $pesan .= ': Prasarat Pelunasan Entri KRS';
    }

    if (LunasSppModel::query()->where($kunci)->exists()) {
      return;
    }

    $cuti = DB::table('tbl_akm')
      ->where('nipd', trim($mhs->nipd))
      ->where('id_smt', $kunci['id_smt'])
      ->where('id_stat_mhs', 'C')
      ->exists();

    if (!$cuti && !$regUlangOk) {
      throw new RuntimeException($pesan);
    }
  }

  private function struk(string $idTagihan, string $idReg): SppModel
  {
    return SppModel::query()->where(['id' => $idTagihan, 'id_reg_pd' => $idReg])->first()
      ?? throw new RuntimeException('ID struk kedaluwarsa! Klik tombol Proses Struk kembali.');
  }

  /* ------------------------------------------------------------------
   | Rincian biaya prodi (Admin::rincian_result, biaya_edit_uang)
   * ------------------------------------------------------------------ */

  public function rincianBiaya(?string $idSms, ?string $angkatan, ?string $kelas, ?string $sarat): Collection
  {
    if (!$idSms || !$angkatan) {
      return collect();
    }

    return BiayaModel::query()
      ->where('sms', $idSms)
      ->where('angkatan', $angkatan)
      ->when($kelas !== null && $kelas !== '', fn ($q) => $q->where('kelas', $kelas))
      ->when($sarat !== null && $sarat !== '', fn ($q) => $q->where('prasarat', $sarat))
      ->orderBy('ma')
      ->get(['id', 'ma', 'kode', 'nama', 'uang', 'kredit', 'praktik', 'prasarat']);
  }

  public function ubahNominalBiaya(string $id, int $uang): void
  {
    BiayaModel::query()->whereKey($id)->update(['uang' => $uang]);
  }

  /* ------------------------------------------------------------------
   | Rincian struk (Admin::tagihan_result, tagihan_rinci_*)
   * ------------------------------------------------------------------ */

  private function queryRincian(): Builder
  {
    return DB::table('bak_tagihan as t1')
      ->join('bak_tagihan_dtl as t2', 't2.id_tagihan', '=', 't1.id')
      ->join('tbl_mhs as t3', 't3.id_reg_pd', '=', 't1.id_reg_pd')
      ->whereNotIn('t2.ma', self::MA_DISEMBUNYIKAN);
  }

  /** Semua rincian struk belum lunas milik mahasiswa. */
  public function rincianStruk(string $idReg): Collection
  {
    return $this->queryRincian()
      ->where('t1.id_reg_pd', $idReg)
      ->where('t1.lunas', 0)
      ->orderBy('t1.struk_id')
      ->orderBy('t2.id')
      ->get([
        't2.id', 't2.ma', 't2.uang', 't2.keterangan', 't1.id as id_tagihan', 't1.mhssmt', 't1.sarat', 't1.thnsmt',
        't3.nipd', DB::raw('t2.post_piutang::text as post_piutang'),
      ]);
  }

  /** Admin::tagihan_rinci_add — tambah satu biaya ke struk. */
  public function tambahRincian(string $idTagihan, string $idReg, string $idBiaya, string $idSmt): void
  {
    $this->struk($idTagihan, $idReg);
    $biaya = BiayaModel::query()->find($idBiaya) ?? throw new RuntimeException('Biaya tidak ditemukan.');

    if (SppRinciModel::query()->where(['id_tagihan' => $idTagihan, 'ma' => $biaya->ma])->exists()) {
      throw new RuntimeException('Biaya "' . trim($biaya->nama) . '" sudah ada di struk.');
    }

    $jumlah = (int) $biaya->uang;
    $uraian = trim($biaya->nama);

    if ((int) $biaya->ma === self::MA_SKS || (int) $biaya->kredit === 1) {
      $sks = (int) DB::table('tbl_nil as t1')
        ->join('tbl_mk as t2', 't2.id_mk', '=', 't1.id_mk')
        ->where(['t1.id_reg_pd' => $idReg, 't1.id_smt' => $idSmt])
        ->sum('t2.sks_mk');
      $jumlah = (int) $biaya->uang * $sks;
      $uraian .= "  ({$sks} SKS)";
    }

    if ((int) $biaya->praktik === 1) {
      $mk = DB::table('tbl_nil')
        ->where(['id_reg_pd' => $idReg, 'id_smt' => $idSmt, 'acara_prak' => 1])
        ->count();
      $jumlah = (int) $biaya->uang * $mk;
      $uraian = trim($biaya->nama) . "  ({$mk} MK)";
    }

    // Insert lewat query builder: id bak_tagihan_dtl berupa sequence bigint.
    SppRinciModel::query()->insert([
      'ma' => $biaya->ma,
      'id_tagihan' => $idTagihan,
      'uang' => $jumlah,
      'keterangan' => $uraian,
      'role_users' => 'penerimaan',
    ]);
  }

  private function rincian(int $id): object
  {
    return $this->queryRincian()
      ->where('t2.id', $id)
      ->first(['t2.id', 't2.ma', 't2.uang', 't2.keterangan', 't1.id_reg_pd', 't1.thnsmt', 't1.lunas', DB::raw('t2.post_piutang::text as post_piutang')])
      ?? throw new RuntimeException('Rincian struk tidak ditemukan.');
  }

  /**
   * Admin::tagihan_rinci_put — ubah nominal rincian. Selisih dari nominal
   * pokok dicatat sebagai dispensasi (bak_tagihan_potongan).
   */
  public function ubahRincian(int $id, int $uangBaru): void
  {
    $r = $this->rincian($id);

    if ((int) $r->lunas !== 0) {
      throw new RuntimeException('Struk sudah lunas.');
    }

    DB::transaction(function () use ($r, $uangBaru) {
      $kunci = ['id_reg_pd' => $r->id_reg_pd, 'thnsmt' => trim($r->thnsmt), 'ma' => $r->ma];
      $potongan = DB::table('bak_tagihan_potongan')->where($kunci)->first();
      $selisih = (int) $r->uang - $uangBaru;

      if (!$potongan && $selisih > 0) {
        DB::table('bak_tagihan_potongan')->insert($kunci + [
          'uang_pokok' => (int) $r->uang,
          'uraian' => 'Potongan ' . trim($r->keterangan),
          'uang_potongan' => $selisih,
          'post_piutang' => $r->post_piutang === '1' ? '1' : '0',
        ]);
      } elseif ($potongan) {
        DB::table('bak_tagihan_potongan')->where($kunci)->update([
          'uraian' => trim($r->keterangan),
          'uang_potongan' => (int) $potongan->uang_pokok - $uangBaru,
          'post_piutang' => $r->post_piutang === '1' ? '1' : '0',
        ]);
      }

      SppRinciModel::query()->whereKey($r->id)->update(['uang' => $uangBaru]);
    });
  }

  public function hapusRincian(int $id): void
  {
    $r = $this->rincian($id);

    if ((int) $r->lunas !== 0) {
      throw new RuntimeException('Struk sudah lunas.');
    }

    if ($r->post_piutang === '1') {
      throw new RuntimeException('Rincian sudah dijurnal piutang, tidak dapat dihapus.');
    }

    SppRinciModel::query()->whereKey($r->id)->delete();
  }

  /* ------------------------------------------------------------------
   | Riwayat struk (bak_tagihan)
   * ------------------------------------------------------------------ */

  /**
   * Semua struk mahasiswa (lunas & belum), terbaru dulu, dengan jumlah
   * rincian dan total nominal (semua MA, tanpa filter MA_DISEMBUNYIKAN).
   */
  public function riwayatStruk(string $idReg): Collection
  {
    return SppModel::query()
      ->from('bak_tagihan as t')
      ->leftJoin('bak_bank as b', DB::raw('b.id::text'), '=', DB::raw('t.bank::text'))
      ->where('t.id_reg_pd', $idReg)
      ->selectRaw('t.id, t.struk_id, t.thnsmt, t.thn_akad, t.smt_akad, t.sarat, t.mhssmt, t.input_date,
        t.lunas, t.lunas_date, t.bank, b.nm_bank,
        (select count(*) from bak_tagihan_dtl d where d.id_tagihan = t.id) as jumlah_rincian,
        (select coalesce(sum(d.uang), 0) from bak_tagihan_dtl d where d.id_tagihan = t.id) as total')
      ->orderByDesc('t.struk_id')
      ->get();
  }

  /** Hapus struk yang tidak punya rincian dan belum lunas. */
  public function hapusStruk(string $id, string $idReg): void
  {
    $struk = $this->struk($id, $idReg);

    if ((int) $struk->lunas === 1) {
      throw new RuntimeException('Struk sudah lunas, tidak dapat dihapus.');
    }

    if (SppRinciModel::query()->where('id_tagihan', $struk->id)->exists()) {
      throw new RuntimeException('Struk masih punya rincian, tidak dapat dihapus.');
    }

    $struk->delete();
  }

  /* ------------------------------------------------------------------
   | Dispensasi (Admin::dispen_result, tagihan_dispen_add, dispen_rinci_dlt)
   * ------------------------------------------------------------------ */

  public function dispensasi(string $idReg): Collection
  {
    return DB::table('bak_tagihan_potongan')
      ->where('id_reg_pd', $idReg)
      ->where('uang_potongan', '>', 0)
      ->orderBy('thnsmt')
      ->orderBy('ma')
      ->get(['thnsmt', 'ma', 'uraian', 'uang_pokok', 'uang_potongan', DB::raw('post_piutang::text as post_piutang')]);
  }

  /** Masukkan sisa dispensasi ke struk; dispensasi di-nol-kan. */
  public function pakaiDispensasi(string $idTagihan, string $idReg, string $ma, string $thnsmt): void
  {
    $this->struk($idTagihan, $idReg);
    $kunci = ['id_reg_pd' => $idReg, 'thnsmt' => $thnsmt, 'ma' => $ma];
    $dsp = DB::table('bak_tagihan_potongan')->where($kunci)->first(['uang_potongan', 'uraian', DB::raw('post_piutang::text as post_piutang')])
      ?? throw new RuntimeException('Dispensasi tidak ditemukan.');

    if ((int) $dsp->uang_potongan <= 0) {
      throw new RuntimeException('Sisa dispensasi sudah 0.');
    }

    DB::transaction(function () use ($idTagihan, $kunci, $dsp, $ma) {
      $rinci = SppRinciModel::query()->where(['id_tagihan' => $idTagihan, 'ma' => $ma])->first(['id', 'uang']);
      $data = [
        'keterangan' => $dsp->uraian,
        'post_piutang' => $dsp->post_piutang === '1' ? '1' : '0',
        'role_users' => 'penerimaan',
      ];

      if ($rinci) {
        SppRinciModel::query()->whereKey($rinci->id)->update($data + ['uang' => (int) $rinci->uang + (int) $dsp->uang_potongan]);
      } else {
        SppRinciModel::query()->insert($data + ['ma' => $ma, 'id_tagihan' => $idTagihan, 'uang' => (int) $dsp->uang_potongan]);
      }

      DB::table('bak_tagihan_potongan')->where($kunci)->update(['uang_potongan' => 0]);
    });
  }

  public function hapusDispensasi(string $idReg, string $ma, string $thnsmt): void
  {
    $n = DB::table('bak_tagihan_potongan')
      ->where(['id_reg_pd' => $idReg, 'ma' => $ma, 'thnsmt' => $thnsmt])
      ->whereRaw("post_piutang = '0'")
      ->delete();

    if ($n === 0) {
      throw new RuntimeException('Dispensasi tidak ditemukan atau sudah dijurnal.');
    }
  }

  /* ------------------------------------------------------------------
   | Status prasarat (Admin::prasarat_*)
   * ------------------------------------------------------------------ */

  public function prasarat(string $idReg): Collection
  {
    return DB::table('bak_lns as p1')
      ->join('tbl_mhs as p2', 'p2.id_reg_pd', '=', 'p1.id_reg_pd')
      ->join('tbl_sms as p3', 'p3.id_sms', '=', 'p2.id_sms')
      ->where('p1.id_reg_pd', $idReg)
      ->orderBy('p1.id_smt')
      ->get(['p1.id_byr', 'p1.id_smt', 'p1.lunas_reg', 'p1.lunas_sks', 'p1.tgl_setor', 'p1.tgl_pelunasan', 'p2.nipd', 'p2.nm_pd', 'p3.jenjang_didik', 'p3.nm_lemb']);
  }

  /** Tambah baris status prasarat untuk semester ini bila belum ada. */
  public function tambahPrasarat(string $idReg, string $idSmt): bool
  {
    $mhs = $this->mahasiswa($idReg) ?? throw new RuntimeException('Mahasiswa tidak ditemukan.');
    $data = [
      'id_sms' => $mhs->id_sms,
      'id_smt' => $idSmt,
      'id_reg_pd' => $mhs->id_reg_pd,
      'angkatan' => substr((string) $mhs->mulai_smt, 0, 4),
    ];

    if (LunasSppModel::query()->where($data)->exists()) {
      return false;
    }

    LunasSppModel::create($data);

    return true;
  }

  public function ubahPrasarat(string $idByr, bool $entriKrs, bool $cetakKpu): void
  {
    LunasSppModel::query()->whereKey($idByr)->update([
      'lunas_reg' => $entriKrs ? 1 : null,
      'lunas_sks' => $cetakKpu ? 1 : null,
    ]);
  }

  /* ------------------------------------------------------------------
   | KPP (Admin::kppmhs, tanpa MSSQL)
   * ------------------------------------------------------------------ */

  /**
   * Rekap KPP per MA per semester mahasiswa, dihitung langsung dari
   * bak_kppmhs + bak_kppmhs_dtl (v1 menulisnya ke bak_kppmhs_rekap).
   *
   * @return array{semester: int, baris: Collection}
   */
  public function kpp(string $idReg): array
  {
    $rows = DB::table('bak_kppmhs as t1')
      ->join('bak_kppmhs_dtl as t2', 't2.kppmhs', '=', 't1.id')
      ->where('t1.id_reg_pd', $idReg)
      ->whereNotIn('t2.ma', self::MA_DISEMBUNYIKAN)
      ->groupBy('t2.ma', 't1.smt_mhs')
      ->selectRaw('t2.ma, t1.smt_mhs, sum(t2.uang) as uang')
      ->get();

    $nama = BiayaModel::query()
      ->whereIn('ma', $rows->pluck('ma')->unique())
      ->selectRaw('DISTINCT ON (ma) ma, kode, nama')
      ->orderBy('ma')
      ->orderByDesc('angkatan')
      ->get()
      ->keyBy(fn ($r) => (string) (int) $r->ma);

    $baris = $rows->groupBy(fn ($r) => (string) (int) $r->ma)->map(fn ($g, $ma) => (object) [
      'ma' => $ma,
      'kode' => trim($nama[$ma]->kode ?? ''),
      'nama' => trim($nama[$ma]->nama ?? 'MA ' . $ma),
      'sem' => $g->mapWithKeys(fn ($r) => [(int) $r->smt_mhs => (int) $r->uang])->all(),
    ])->sortBy('kode', SORT_NATURAL)->values();

    return [
      'semester' => max(14, (int) $rows->max('smt_mhs')),
      'baris' => $baris,
    ];
  }
}
