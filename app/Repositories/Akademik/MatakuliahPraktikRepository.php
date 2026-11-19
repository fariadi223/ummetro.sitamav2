<?php

namespace App\Repositories\Akademik;

use App\Models\Akademik\MatakuliahModel;
use App\Models\Akademik\NilaiModel;
use App\Models\Servicemahasiswa\PsstudiModel;
use App\Support\TahunAkademik;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Sebaran matakuliah praktik.
 *
 * Diadaptasi dari SITAMA v1 (Akademik::matakuliah, M_sistem::QuerySebaranMak):
 * - daftar matakuliah yang diambil mahasiswa (tbl_nil) pada satu semester,
 *   dikelompokkan per matakuliah, dengan jumlah peserta;
 * - filter angkatan (4 digit awal mulai_smt) dan prodi mahasiswa;
 * - penanda praktik = kolom tbl_nil.acara_prak, diubah serentak untuk semua
 *   baris id_mk + id_smt (sama seperti v1).
 *
 * Bedanya dengan v1: acara_prak tidak ikut di GROUP BY, sehingga matakuliah
 * yang barisnya campuran (sebagian praktik) tampil satu baris dengan status
 * "sebagian", bukan dua baris.
 */
class MatakuliahPraktikRepository
{
  public function getAll(array $filter): LengthAwarePaginator
  {
    $keyword = mb_strtolower(trim((string) ($filter['search'] ?? '')));
    $perPage = min(100, max(1, (int) ($filter['limit'] ?? 25)));

    return NilaiModel::query()
      ->from('tbl_nil as n')
      ->join('tbl_mk as m', 'm.id_mk', '=', 'n.id_mk')
      ->join('tbl_mhs as s', 's.id_reg_pd', '=', 'n.id_reg_pd')
      ->where('n.id_smt', $filter['id_smt'])
      ->when($filter['sms'] ?? null, fn ($q, $v) => $q->where('s.id_sms', $v))
      ->when($filter['angkatan'] ?? null, fn ($q, $v) => $q->whereRaw('left(s.mulai_smt::text, 4) = ?', [(string) $v]))
      ->when($filter['status'] ?? null, fn ($q, $v) => $v === 'praktik'
        ? $q->havingRaw('sum(case when n.acara_prak = 1 then 1 else 0 end) > 0')
        : $q->havingRaw('sum(case when n.acara_prak = 1 then 1 else 0 end) = 0'))
      ->when($keyword !== '', function ($q) use ($keyword) {
        $q->where(function ($q) use ($keyword) {
          $q->whereRaw('lower(m.kode_mk) like ?', ["%{$keyword}%"])
            ->orWhereRaw('lower(m.nm_mk) like ?', ["%{$keyword}%"]);
        });
      })
      ->groupBy('n.id_mk', 'n.id_smt', 'm.kode_mk', 'm.nm_mk', 'm.sks_mk', 'm.mk_pel', 'm.id_sms')
      ->selectRaw('n.id_mk, n.id_smt, m.kode_mk, m.nm_mk, m.sks_mk, m.mk_pel, m.id_sms,
        count(*) as peserta,
        sum(case when n.acara_prak = 1 then 1 else 0 end) as praktik')
      ->orderBy('m.mk_pel')
      ->orderBy('m.kode_mk')
      ->paginate($perPage)
      ->withQueryString();
  }

  /**
   * Tandai / hapus tanda praktik untuk semua peserta matakuliah di semester itu.
   *
   * @return int jumlah baris tbl_nil yang diubah
   */
  public function setPraktik(string $idMk, string $idSmt, bool $praktik): int
  {
    return NilaiModel::query()
      ->where('id_mk', $idMk)
      ->where('id_smt', $idSmt)
      ->update(['acara_prak' => $praktik ? 1 : 0]);
  }

  public function namaMatakuliah(string $idMk): ?string
  {
    $mk = MatakuliahModel::query()->find($idMk, ['kode_mk', 'nm_mk']);

    return $mk ? trim($mk->kode_mk) . ' ' . trim($mk->nm_mk) : null;
  }

  /**
   * Semester akademik aktif (port Thnakademik::aktive v1):
   * Feb–Jul = Genap tahun lalu, Agu–Des = Ganjil tahun ini, Januari = Ganjil tahun lalu.
   */
  public static function semesterAktif(): string
  {
    return TahunAkademik::aktif();
  }

  /** id_smt => "2025/2026 - Genap", dari tahun ini mundur ke 2015. */
  public function pilihanSemester(): array
  {
    return TahunAkademik::pilihan();
  }

  public function pilihanAngkatan(): array
  {
    return range((int) date('Y'), 2015);
  }

  public function pilihanProdi(): Collection
  {
    return PsstudiModel::query()
      ->whereNotNull('nm_lemb')
      ->orderBy('jenjang_didik')
      ->orderBy('nm_lemb')
      ->get(['id_sms', 'nm_lemb', 'jenjang_didik']);
  }
}
