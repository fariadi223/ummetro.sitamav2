<?php

namespace App\Repositories\Mak;

use App\Models\Mak\MataAnggaranModel as BiayaModel;
use App\Models\Servicemahasiswa\PsstudiModel;
use App\Models\Tagihan\SppRinciModel;
use App\Services\SimakuService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Data biaya (mata anggaran / tabel bak_mak).
 *
 * Diadaptasi dari App\Repositories\Mak\BiayaRepository di endpoint SITAMA:
 * - filter yang diizinkan: sms (prodi), angkatan, ma, sumber_id;
 * - pencarian pada nama & kode tanpa membedakan huruf besar/kecil;
 * - filter hanya dipasang bila diisi (endpoint lama memasang semua kunci);
 * - urutan tetap (angkatan terbaru, lalu kode & nama), bukan id UUID yang acak.
 */
class BiayaRepository
{
  public function __construct(private SimakuService $simaku)
  {
  }

  /** Nilai kolom kelas => [kode singkat, nama]. */
  public const KELAS = [
    '0' => ['R', 'Kelas reguler'],
    '1' => ['P', 'Kelas konversi'],
    '2' => ['B', 'Kelas B'],
    '99' => ['Z', 'Paket'],
  ];

  /** Nilai kolom prasarat => [label, warna teks]. 0 = tanpa prasarat. */
  public const PRASARAT = [
    1 => ['Entri KRS', 'success'],
    2 => ['Cetak KPU', 'warning'],
  ];

  private const CACHE_SIMAKU_GAGAL = 'simaku.coa.level6.gagal';

  /** Awalan kode COA rekening pendapatan yang boleh dipakai sebagai biaya. */
  private const KODE_PENDAPATAN = '4';

  /** Kunci request => kolom tabel. */
  private const FILTER = [
    'sms' => 'sms',
    'angkatan' => 'angkatan',
    'ma' => 'ma',
    'sumber_id' => 'sumber_id',
  ];

  public function getAll(array $request): LengthAwarePaginator
  {
    $keyword = trim((string) ($request['search'] ?? ''));
    $perPage = min(100, max(1, (int) ($request['limit'] ?? 10)));

    return BiayaModel::query()
      ->where($this->filter($request))
      ->when($keyword !== '', function ($query) use ($keyword) {
        $query->where(function ($q) use ($keyword) {
          $q->where('nama', 'ILIKE', "%{$keyword}%")
            ->orWhere('kode', 'ILIKE', "%{$keyword}%");
        });
      })
      ->with(['prodi' => fn ($q) => $q->select('id_sms', 'kode_prodi', 'nm_lemb', 'jenjang_didik')])
      ->orderByDesc('angkatan')
      ->orderBy('kode')
      ->orderBy('nama')
      ->paginate($perPage)
      ->withQueryString();
  }

  /**
   * Ambil hanya kunci yang diizinkan dan berisi nilai.
   */
  public function filter(array $request): array
  {
    $hasil = [];

    foreach (self::FILTER as $kunci => $kolom) {
      $nilai = $request[$kunci] ?? null;

      if ($nilai !== null && $nilai !== '') {
        $hasil[$kolom] = $nilai;
      }
    }

    return $hasil;
  }

  public function getByID(string $id): ?BiayaModel
  {
    return BiayaModel::with('prodi')->find($id);
  }

  /**
   * Pilihan untuk form filter.
   */
  public function pilihanProdi(): Collection
  {
    return PsstudiModel::query()
      ->whereIn('id_sms', BiayaModel::query()->select('sms')->distinct())
      ->orderBy('jenjang_didik')
      ->orderBy('nm_lemb')
      ->get(['id_sms', 'kode_prodi', 'nm_lemb', 'jenjang_didik']);
  }

  public function pilihanAngkatan(): Collection
  {
    return BiayaModel::query()->distinct()->orderByDesc('angkatan')->pluck('angkatan');
  }

  /**
   * Satu baris per kode MA: kode & nama diambil dari angkatan terbaru
   * (angkatan khusus 8888/9999 dipakai terakhir), karena format kode
   * rekening pernah berubah.
   */
  public function pilihanMataAnggaran(): Collection
  {
    $terbaru = BiayaModel::query()
      ->selectRaw('DISTINCT ON (ma) ma, kode, nama')
      ->orderBy('ma')
      ->orderByRaw('(angkatan >= 8888), angkatan DESC');

    return BiayaModel::query()
      ->fromSub($terbaru, 'mak')
      ->orderByRaw('trim(nama)')
      ->get();
  }

  /**
   * Rekening biaya untuk form tambah/ubah: rekening level 6 dari SIMAKU
   * (GET coa/level6) yang kodenya berawalan 4 (pendapatan). Bila SIMAKU tidak bisa diakses, dipakai mata anggaran
   * yang sudah ada di bak_mak dan pesan galatnya ikut dikembalikan.
   *
   * @return array{0: Collection, 1: ?string} [daftar {ma, kode, nama}, pesan galat]
   */
  public function rekeningBiaya(): array
  {
    // Setelah gagal, SIMAKU tidak dicoba lagi selama 5 menit supaya halaman
    // tidak menunggu timeout di setiap pemuatan.
    if ($galat = Cache::get(self::CACHE_SIMAKU_GAGAL)) {
      return [$this->rekeningPendapatan($this->pilihanMataAnggaran()), $galat];
    }

    try {
      $rekening = $this->simaku->rekeningLevel6()->map(fn ($r) => (object) $r);

      return [$this->rekeningPendapatan($rekening), null];
    } catch (RuntimeException $e) {
      Log::warning('Rekening SIMAKU tidak tersedia, memakai data lokal', ['pesan' => $e->getMessage()]);
      Cache::put(self::CACHE_SIMAKU_GAGAL, $e->getMessage(), now()->addMinutes(5));

      return [$this->rekeningPendapatan($this->pilihanMataAnggaran()), $e->getMessage()];
    }
  }

  /** Hanya rekening pendapatan: kode COA berawalan 4 (mis. 4.1.01.02.01.01). */
  private function rekeningPendapatan(Collection $rekening): Collection
  {
    return $rekening
      ->filter(fn ($r) => str_starts_with(ltrim((string) $r->kode), self::KODE_PENDAPATAN))
      ->values();
  }

  /**
   * Biaya dengan kombinasi MA + prodi + angkatan + kelas yang sama sudah ada?
   */
  public function sudahAda(array $data, ?string $kecualiId = null): bool
  {
    return BiayaModel::query()
      ->where('ma', $data['ma'])
      ->where('sms', $data['sms'])
      ->where('angkatan', $data['angkatan'])
      ->where('kelas', $data['kelas'])
      ->when($kecualiId, fn ($q) => $q->where('id', '!=', $kecualiId))
      ->exists();
  }

  /**
   * Simpan biaya baru. Kode & nama diambil dari rekening terpilih, nama
   * sumber dana dari sumber_id; prasarat memakai default tabel.
   */
  public function create(array $data, object $rekening): BiayaModel
  {
    $sumber = $this->pilihanSumberDana()->firstWhere('sumber_id', (int) $data['sumber_id']);

    return BiayaModel::create([
      'ma' => $data['ma'],
      'kode' => $rekening->kode,
      'nama' => trim($rekening->nama),
      'kelas' => $data['kelas'],
      'sms' => $data['sms'],
      'angkatan' => $data['angkatan'],
      'uang' => (int) $data['uang'],
      'sumber_id' => (int) $data['sumber_id'],
      'sumber_nama' => $sumber?->sumber_nama,
    ] + $this->opsi($data));
  }

  /**
   * Checkbox opsi perhitungan: kredit (per SKS), praktik (per praktikum),
   * pilihmhs (dapat diinput mahasiswa; kolom bit, jadi dikirim '0'/'1').
   */
  private function opsi(array $data): array
  {
    return [
      'prasarat' => (int) ($data['prasarat'] ?? 0),
      'kredit' => !empty($data['kredit']) ? 1 : 0,
      'praktik' => !empty($data['praktik']) ? 1 : 0,
      'pilihmhs' => !empty($data['pilihmhs']) ? '1' : '0',
    ];
  }

  /**
   * Ubah biaya. Bila MA berubah, kode & nama ikut rekening yang baru.
   */
  public function update(BiayaModel $biaya, array $data, ?object $rekening): BiayaModel
  {
    $sumber = $this->pilihanSumberDana()->firstWhere('sumber_id', (int) $data['sumber_id']);

    $biaya->fill([
      'ma' => $data['ma'],
      'kelas' => $data['kelas'],
      'sms' => $data['sms'],
      'angkatan' => $data['angkatan'],
      'uang' => (int) $data['uang'],
      'sumber_id' => (int) $data['sumber_id'],
      'sumber_nama' => $sumber?->sumber_nama,
    ] + $this->opsi($data));

    if ($rekening && (string) $rekening->ma !== (string) $biaya->getOriginal('ma')) {
      $biaya->kode = $rekening->kode;
      $biaya->nama = trim($rekening->nama);
    }

    $biaya->save();

    return $biaya;
  }

  public function delete(BiayaModel $biaya): void
  {
    $biaya->delete();
  }

  /**
   * Biaya sudah dipakai di rincian tagihan (bak_tagihan_dtl)?
   *
   * Rincian tagihan hanya menyimpan kolom ma, jadi pemakaian dicocokkan
   * lewat MA + prodi + angkatan mahasiswa pemilik tagihan.
   */
  public function dipakai(BiayaModel $biaya): bool
  {
    return $this->queryPemakaian([$biaya->ma], [$biaya->sms])
      ->whereRaw('left(s.mulai_smt::text, 4) = ?', [(string) $biaya->angkatan])
      ->exists();
  }

  /**
   * Tandai setiap baris dengan atribut `dipakai` (satu query per halaman).
   */
  public function tandaiDipakai(iterable $rows): void
  {
    $rows = collect($rows);

    if ($rows->isEmpty()) {
      return;
    }

    $kunci = fn ($ma, $sms, $angkatan) => (string) (int) $ma . '|' . $sms . '|' . (string) (int) $angkatan;

    $terpakai = $this->queryPemakaian($rows->pluck('ma')->unique()->all(), $rows->pluck('sms')->filter()->unique()->all())
      ->distinct()
      ->selectRaw('d.ma, s.id_sms, left(s.mulai_smt::text, 4) as angkatan')
      ->get()
      ->mapWithKeys(fn ($r) => [$kunci($r->ma, $r->id_sms, $r->angkatan) => true]);

    foreach ($rows as $row) {
      $row->dipakai = $terpakai->has($kunci($row->ma, $row->sms, $row->angkatan));
    }
  }

  private function queryPemakaian(array $ma, array $sms)
  {
    return SppRinciModel::query()
      ->from('bak_tagihan_dtl as d')
      ->join('bak_tagihan as t', 't.id', '=', 'd.id_tagihan')
      ->join('tbl_mhs as s', 's.id_reg_pd', '=', 't.id_reg_pd')
      ->whereIn('d.ma', $ma)
      ->whereIn('s.id_sms', $sms);
  }

  public function pilihanSumberDana(): Collection
  {
    return BiayaModel::query()
      ->whereNotNull('sumber_id')
      ->selectRaw('sumber_id, max(sumber_nama) as sumber_nama')
      ->groupBy('sumber_id')
      ->orderByRaw('max(sumber_nama)')
      ->get();
  }
}
