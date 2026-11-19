<?php

namespace App\Repositories\Tagihan;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Pembuatan struk tagihan registrasi ulang calon mahasiswa baru (Penmaru).
 *
 * Port dari SITAMA v1 Admin::tagihan_pmb* / search_pmb dan M_sistem
 * (QueryTagihanPMB, FetchMhsMaru, *MhsMaruReg).
 * Tabel: tbl_mhsmaru (peserta), tbl_mhsmaru_register (struk), bak_mak (biaya).
 *
 * Tidak dipindahkan: tampilan "srv2" (data MSSQL MHSMARU lama).
 */
class TagihanPmbRepository
{
  /** MA biaya registrasi ulang mahasiswa baru (v1: $rek6 = 873). */
  public const MA_REGISTRASI = 873;

  /** Kode bank yang dicatat saat tagihan ditutup manual (v1: tagihan_pmb_tutup). */
  public const BANK_TUTUP = '021004';

  /** auth_id bawaan v1 (ID SSO Kurniatul Muhdofar) bila id user bukan angka. */
  public const AUTH_ID_DEFAULT = 19576;

  public const STATUS = ['0' => 'Belum Lunas', '1' => 'Lunas'];

  /* ------------------------------------------------------------------
   | Daftar tagihan (QueryTagihanPMB)
   * ------------------------------------------------------------------ */

  private function query(array $f): Builder
  {
    $cari = strtolower(trim((string) ($f['search'] ?? '')));

    return DB::table('tbl_mhsmaru as t1')
      ->join('tbl_mhsmaru_register as t2', 't2.nom_tes', '=', 't1.no_tes')
      ->join('tbl_sms as t3', 't3.id_sms', '=', 't2.id_sms')
      ->whereNotNull('t2.registrasi_uang')
      ->when($f['tahun'] ?? null, fn ($q, $v) => $q->where('t1.thn_ajaran', $v))
      ->when($f['id_sms'] ?? null, fn ($q, $v) => $q->where('t2.id_sms', $v))
      ->when(isset($f['status']) && $f['status'] !== '', fn ($q) => $q->where('t2.lunas', $f['status']))
      ->when($cari !== '', fn ($q) => $q->where(fn ($w) => $w
        ->whereRaw('LOWER(t1.nama_lengkap) LIKE ?', ["%{$cari}%"])
        ->orWhereRaw('LOWER(t1.no_tes) LIKE ?', ["%{$cari}%"])));
  }

  private function kolom(Builder $q): Builder
  {
    return $q->select([
      't1.no_tes', 't1.nama_lengkap', 't1.thn_ajaran', 't1.tmpt_lahir', 't1.tgl_lahir', 't1.telepon_seluler',
      't2.registrasi_nomor', 't2.registrasi_uang', 't2.nipd_register', 't2.lunas_bank',
      DB::raw('t2.lunas::int AS lunas'),
      't3.jenjang_didik', 't3.nm_lemb',
    ])->orderBy('t2.registrasi_nomor')->orderBy('t1.no_tes');
  }

  public function daftar(array $f): LengthAwarePaginator
  {
    return $this->kolom($this->query($f))->paginate((int) ($f['limit'] ?? 25))->withQueryString();
  }

  /** Semua baris sesuai filter, untuk ekspor Excel. */
  public function semua(array $f): Collection
  {
    return $this->kolom($this->query($f))->get();
  }

  /** @return array{total: int, lunas: int, uang: int} */
  public function ringkasan(array $f): array
  {
    $r = $this->query($f)
      ->selectRaw("COUNT(*) AS total, COUNT(*) FILTER (WHERE t2.lunas = B'1') AS lunas, COALESCE(SUM(t2.registrasi_uang), 0) AS uang")
      ->first();

    return ['total' => (int) $r->total, 'lunas' => (int) $r->lunas, 'uang' => (int) $r->uang];
  }

  public function pilihanProdi(): Collection
  {
    return DB::table('tbl_sms')
      ->whereNotNull('nm_lemb')
      ->orderBy('jenjang_didik')->orderBy('nm_lemb')
      ->get(['id_sms', 'kode_prodi', 'jenjang_didik', 'nm_lemb']);
  }

  /* ------------------------------------------------------------------
   | Peserta (Admin::search_pmb)
   * ------------------------------------------------------------------ */

  public function cariPeserta(string $noTes): ?array
  {
    $p = DB::table('tbl_mhsmaru')->where('no_tes', $noTes)
      ->first(['no_tes', 'nama_lengkap', 'thn_ajaran', 'prg_1', 'prg_2', 'kelas', DB::raw('kelulusan::int AS kelulusan')]);

    if (!$p) {
      return null;
    }

    $reg = $this->register($noTes);
    $prodi = DB::table('tbl_sms')->whereIn('kode_prodi', array_filter([$p->prg_1, $p->prg_2]))
      ->get(['kode_prodi', 'jenjang_didik', 'nm_lemb'])->keyBy('kode_prodi');

    $pilihan = [];
    foreach (array_unique(array_filter([$p->prg_1, $p->prg_2])) as $kode) {
      if ($s = $prodi->get($kode)) {
        $pilihan[] = ['kode' => $kode, 'nama' => trim($s->jenjang_didik) . ' ' . trim($s->nm_lemb)];
      }
    }

    return [
      'no_tes' => $p->no_tes,
      'nama' => trim((string) $p->nama_lengkap),
      'angkatan' => (string) (int) $p->thn_ajaran,
      'kelulusan' => $p->kelulusan,
      'prodi' => $pilihan,
      'tagihan' => $reg ? [
        'prodi' => trim($reg->jenjang_didik) . ' ' . trim($reg->nm_lemb),
        'nomor' => $reg->registrasi_nomor,
        'uang' => (int) $reg->registrasi_uang,
        'lunas' => (int) $reg->lunas === 1,
        'npm' => $reg->nipd_register,
      ] : null,
    ];
  }

  private function register(string $noTes): ?object
  {
    return DB::table('tbl_mhsmaru_register as t2')
      ->leftJoin('tbl_sms as t3', 't3.id_sms', '=', 't2.id_sms')
      ->where('t2.nom_tes', $noTes)
      ->first(['t2.registrasi_nomor', 't2.registrasi_uang', 't2.nipd_register', DB::raw('t2.lunas::int AS lunas'), 't3.jenjang_didik', 't3.nm_lemb']);
  }

  private function prodi(string $kodeProdi): ?object
  {
    return DB::table('tbl_sms')->where('kode_prodi', $kodeProdi)->first(['id_sms']);
  }

  /** Admin::tagihan_pmb_biaya: biaya MA 873 prodi & angkatan (tahun dari nomor tes). */
  public function biaya(string $noTes, string $kodeProdi): int
  {
    $prodi = $this->prodi($kodeProdi);
    if (!$prodi) {
      return 0;
    }

    return (int) DB::table('bak_mak')
      ->where(['sms' => $prodi->id_sms, 'angkatan' => explode('/', $noTes)[0], 'ma' => self::MA_REGISTRASI])
      ->value('uang');
  }

  /* ------------------------------------------------------------------
   | Aksi (Admin::tagihan_pmb_proses / _delete / _tutup)
   * ------------------------------------------------------------------ */

  /**
   * Buat struk registrasi ulang dan tandai peserta lulus.
   * Nomor registrasi: tahun + level (01 UMM, 02 lainnya, 09 konversi "P") + urut.
   */
  public function buat(string $noTes, string $kodeProdi, int $uang, ?string $authId): void
  {
    $this->pesertaAda($noTes);
    if ($this->register($noTes)) {
      throw new RuntimeException('Peserta ini sudah memiliki tagihan registrasi ulang.');
    }

    $prodi = $this->prodi($kodeProdi) ?? throw new RuntimeException('Program studi tidak ditemukan.');
    if ($uang <= 0) {
      throw new RuntimeException('Maaf, penetapan biaya tagihan registrasi ulang belum dibuat oleh BAK.');
    }

    $kode = explode('/', $noTes);
    if (count($kode) < 3) {
      throw new RuntimeException('Format nomor tes tidak dikenali (contoh: 2026/UMM/0001).');
    }
    $level = str_ends_with($noTes, 'P') ? '09' : ($kode[1] === 'UMM' ? '01' : '02');

    try {
      DB::transaction(function () use ($noTes, $prodi, $uang, $kode, $level, $authId) {
        DB::table('tbl_mhsmaru_register')->insert([
          'nom_tes' => $noTes,
          'id_sms' => $prodi->id_sms,
          'registrasi_nomor' => $kode[0] . $level . $kode[2],
          'registrasi_uang' => $uang,
          'registrasi_idrek6' => self::MA_REGISTRASI,
          'auth_id' => ctype_digit((string) $authId) ? (int) $authId : self::AUTH_ID_DEFAULT,
        ]);
        DB::table('tbl_mhsmaru')->where('no_tes', $noTes)->update(['kelulusan' => '1']);
      });
    } catch (UniqueConstraintViolationException) {
      throw new RuntimeException('Nomor registrasi ' . $kode[0] . $level . $kode[2] . ' sudah dipakai peserta lain.');
    }
  }

  /** v1: proses "tambah" tanpa prodi → peserta ditandai tidak lulus. */
  public function tandaiTidakLulus(string $noTes): void
  {
    $this->pesertaAda($noTes);
    if ($this->register($noTes)) {
      throw new RuntimeException('Hapus tagihan peserta ini terlebih dahulu.');
    }
    DB::table('tbl_mhsmaru')->where('no_tes', $noTes)->update(['kelulusan' => '0']);
  }

  public function ubahUang(string $noTes, int $uang): void
  {
    $this->belumLunas($noTes);
    DB::table('tbl_mhsmaru_register')->where('nom_tes', $noTes)->update(['registrasi_uang' => $uang]);
  }

  public function hapus(string $noTes): void
  {
    $this->belumLunas($noTes);
    DB::transaction(function () use ($noTes) {
      DB::table('tbl_mhsmaru_register')->where('nom_tes', $noTes)->delete();
      DB::table('tbl_mhsmaru')->where('no_tes', $noTes)->update(['kelulusan' => null]);
    });
  }

  public function tutup(string $noTes): void
  {
    $this->belumLunas($noTes);
    DB::table('tbl_mhsmaru_register')->where('nom_tes', $noTes)
      ->update(['lunas' => '1', 'lunas_bank' => self::BANK_TUTUP]);
  }

  private function pesertaAda(string $noTes): void
  {
    if (!DB::table('tbl_mhsmaru')->where('no_tes', $noTes)->exists()) {
      throw new RuntimeException('Peserta dengan nomor ini tidak ditemukan.');
    }
  }

  private function belumLunas(string $noTes): void
  {
    $reg = $this->register($noTes) ?? throw new RuntimeException('Tagihan peserta ini tidak ditemukan.');
    if ((int) $reg->lunas === 1) {
      throw new RuntimeException('Tagihan sudah lunas, tidak dapat diubah.');
    }
  }
}
