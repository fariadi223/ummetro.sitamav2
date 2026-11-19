<?php

namespace App\Http\Controllers\operator;

use App\Http\Controllers\Controller;
use App\Repositories\Mak\BiayaRepository;
use App\Repositories\Tagihan\TagihanRepository;
use App\Support\TahunAkademik;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * Pembuatan struk tagihan mahasiswa (port SITAMA v1 /admin/tagihan).
 * Halaman memakai AJAX; semua aksi membalas JSON {message} atau data.
 */
class TagihanController extends Controller
{
  private const SMT = ['required', 'regex:/^\d{4}[12]$/'];

  public function __construct(private TagihanRepository $tagihan)
  {
  }

  public function index(): View
  {
    return view('content.operator.tagihan.index', [
      'semester' => TahunAkademik::pilihan((int) date('Y') + 1),
      'semesterAktif' => TahunAkademik::aktif(),
      'sarat' => TagihanRepository::SARAT,
      'kelas' => BiayaRepository::KELAS,
    ]);
  }

  public function mahasiswa(Request $request): JsonResponse
  {
    $data = $request->validate(['npm' => ['required', 'string', 'max:20'], 'id_smt' => self::SMT]);
    $mhs = $this->tagihan->cariMahasiswa(trim($data['npm']), $data['id_smt']);

    return $mhs
      ? response()->json($mhs)
      : response()->json(['message' => 'Mahasiswa dengan NPM ini tidak ditemukan.'], 404);
  }

  public function proses(Request $request): JsonResponse
  {
    $data = $request->validate([
      'id_reg_pd' => ['required', 'uuid'],
      'id_sms' => ['required', 'uuid'],
      'id_smt' => self::SMT,
      'sarat' => ['required', 'in:0,1,2'],
      'smmhs' => ['required', 'integer', 'min:0', 'max:30'],
    ], ['id_reg_pd.required' => 'NPM mahasiswa belum diisi.']);

    return $this->jalankan(fn () => $this->tagihan->prosesStruk(
      $data['id_reg_pd'], $data['id_sms'], $data['id_smt'], (int) $data['sarat'], (int) $data['smmhs']
    ));
  }

  public function biaya(Request $request): JsonResponse
  {
    $data = $request->validate([
      'id_sms' => ['nullable', 'uuid'],
      'angkatan' => ['nullable', 'digits:4'],
      'kelas' => ['nullable', 'in:' . implode(',', array_keys(BiayaRepository::KELAS))],
      'sarat' => ['nullable', 'in:0,1,2'],
    ]);

    return response()->json($this->tagihan->rincianBiaya(
      $data['id_sms'] ?? null, $data['angkatan'] ?? null, $data['kelas'] ?? null, $data['sarat'] ?? null
    ));
  }

  public function ubahNominalBiaya(Request $request, string $id): JsonResponse
  {
    $data = $request->validate(['uang' => ['required', 'integer', 'min:0', 'max:999999999999']]);
    $this->tagihan->ubahNominalBiaya($id, (int) $data['uang']);

    return response()->json(['message' => 'Nominal biaya diubah.']);
  }

  /** Rincian struk, dispensasi, dan prasarat milik mahasiswa sekaligus. */
  public function detail(string $idReg): JsonResponse
  {
    return response()->json([
      'rincian' => $this->tagihan->rincianStruk($idReg),
      'dispensasi' => $this->tagihan->dispensasi($idReg),
      'prasarat' => $this->tagihan->prasarat($idReg),
      'riwayat' => $this->tagihan->riwayatStruk($idReg),
    ]);
  }

  public function hapusStruk(Request $request, string $id): JsonResponse
  {
    $data = $request->validate(['id_reg_pd' => ['required', 'uuid']]);

    return $this->jalankan(fn () => $this->tagihan->hapusStruk($id, $data['id_reg_pd']));
  }

  public function tambahRincian(Request $request): JsonResponse
  {
    $data = $request->validate([
      'id_tagihan' => ['required', 'uuid'],
      'id_reg_pd' => ['required', 'uuid'],
      'id_biaya' => ['required', 'uuid'],
      'id_smt' => self::SMT,
    ], ['id_tagihan.required' => 'Klik Proses Struk dulu.']);

    return $this->jalankan(fn () => $this->tagihan->tambahRincian($data['id_tagihan'], $data['id_reg_pd'], $data['id_biaya'], $data['id_smt']));
  }

  public function ubahRincian(Request $request, int $id): JsonResponse
  {
    $data = $request->validate(['uang' => ['required', 'integer', 'min:0', 'max:999999999999']]);

    return $this->jalankan(fn () => $this->tagihan->ubahRincian($id, (int) $data['uang']));
  }

  public function hapusRincian(int $id): JsonResponse
  {
    return $this->jalankan(fn () => $this->tagihan->hapusRincian($id));
  }

  public function pakaiDispensasi(Request $request): JsonResponse
  {
    $data = $request->validate([
      'id_tagihan' => ['required', 'uuid'],
      'id_reg_pd' => ['required', 'uuid'],
      'ma' => ['required', 'integer'],
      'thnsmt' => self::SMT,
    ], ['id_tagihan.required' => 'Klik Proses Struk dulu.']);

    return $this->jalankan(fn () => $this->tagihan->pakaiDispensasi($data['id_tagihan'], $data['id_reg_pd'], (string) $data['ma'], $data['thnsmt']));
  }

  public function hapusDispensasi(Request $request): JsonResponse
  {
    $data = $request->validate([
      'id_reg_pd' => ['required', 'uuid'],
      'ma' => ['required', 'integer'],
      'thnsmt' => self::SMT,
    ]);

    return $this->jalankan(fn () => $this->tagihan->hapusDispensasi($data['id_reg_pd'], (string) $data['ma'], $data['thnsmt']));
  }

  public function tambahPrasarat(Request $request): JsonResponse
  {
    $data = $request->validate(['id_reg_pd' => ['required', 'uuid'], 'id_smt' => self::SMT]);

    return $this->jalankan(fn () => $this->tagihan->tambahPrasarat($data['id_reg_pd'], $data['id_smt'])
      ? ['message' => 'Status prasarat ditambahkan.']
      : ['message' => 'Status prasarat semester ini sudah ada.']);
  }

  public function ubahPrasarat(Request $request, string $idByr): JsonResponse
  {
    $data = $request->validate(['entri_krs' => ['nullable', 'in:1'], 'cetak_kpu' => ['nullable', 'in:1']]);

    return $this->jalankan(fn () => $this->tagihan->ubahPrasarat($idByr, !empty($data['entri_krs']), !empty($data['cetak_kpu'])));
  }

  public function kpp(string $idReg): View
  {
    return view('content.operator.tagihan._kpp', $this->tagihan->kpp($idReg));
  }

  /** Jalankan aksi; RuntimeException jadi 422 dengan pesannya. */
  private function jalankan(callable $aksi): JsonResponse
  {
    try {
      return response()->json($aksi() ?? ['message' => 'OK']);
    } catch (RuntimeException $e) {
      return response()->json(['message' => $e->getMessage()], 422);
    }
  }
}
