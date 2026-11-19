<?php

namespace App\Http\Controllers\operator;

use App\Exports\TagihanPmbExport;
use App\Http\Controllers\Controller;
use App\Repositories\Tagihan\TagihanPmbRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Pembuatan struk tagihan registrasi ulang calon mahasiswa baru
 * (port SITAMA v1 /admin/tagihan_pmb). Daftar memakai GET + paginasi,
 * aksi memakai AJAX dan membalas JSON {message}.
 */
class TagihanPmbController extends Controller
{
  private const NO_TES = ['required', 'string', 'max:30'];

  public function __construct(private TagihanPmbRepository $pmb)
  {
  }

  public function index(Request $request): View
  {
    $filter = $this->filter($request);

    return view('content.operator.tagihan-pmb.index', [
      'data' => $this->pmb->daftar($filter),
      // Ringkasan tanpa filter status: dipakai untuk angka di tab Semua / Belum / Lunas.
      'ringkasan' => $this->pmb->ringkasan(array_diff_key($filter, ['status' => 1])),
      'filter' => $filter,
      'prodi' => $this->pmb->pilihanProdi(),
      'tahun' => range((int) date('Y') + 1, 2015),
    ]);
  }

  public function export(Request $request): BinaryFileResponse
  {
    return Excel::download(
      new TagihanPmbExport($this->pmb->semua($this->filter($request))),
      'Ekpor_slip_tagihan_penmaru_' . date('Ymd_His') . '.xlsx'
    );
  }

  public function peserta(Request $request): JsonResponse
  {
    $data = $request->validate(['no_tes' => self::NO_TES]);
    $peserta = $this->pmb->cariPeserta(strtoupper(trim($data['no_tes'])));

    return $peserta
      ? response()->json($peserta)
      : response()->json(['message' => 'Peserta dengan nomor ini tidak ditemukan.'], 404);
  }

  public function biaya(Request $request): JsonResponse
  {
    $data = $request->validate(['no_tes' => self::NO_TES, 'prodi' => ['required', 'string', 'max:10']]);

    return response()->json(['uang' => $this->pmb->biaya($data['no_tes'], $data['prodi'])]);
  }

  public function store(Request $request): JsonResponse
  {
    $data = $request->validate([
      'no_tes' => self::NO_TES,
      'prodi' => ['required', 'string', 'max:10'],
      'uang' => ['required', 'integer', 'min:1', 'max:999999999999'],
    ], ['prodi.required' => 'Pilih program studi.', 'uang.min' => 'Jumlah uang belum diisi.']);

    return $this->jalankan(fn () => $this->pmb->buat($data['no_tes'], $data['prodi'], (int) $data['uang'], (string) Auth::id()),
      'Tagihan registrasi ulang dibuat.');
  }

  public function tidakLulus(Request $request): JsonResponse
  {
    $data = $request->validate(['no_tes' => self::NO_TES]);

    return $this->jalankan(fn () => $this->pmb->tandaiTidakLulus($data['no_tes']), 'Peserta ditandai tidak lulus.');
  }

  public function update(Request $request): JsonResponse
  {
    $data = $request->validate(['no_tes' => self::NO_TES, 'uang' => ['required', 'integer', 'min:1', 'max:999999999999']]);

    return $this->jalankan(fn () => $this->pmb->ubahUang($data['no_tes'], (int) $data['uang']), 'Nominal tagihan diubah.');
  }

  public function destroy(Request $request): JsonResponse
  {
    $data = $request->validate(['no_tes' => self::NO_TES]);

    return $this->jalankan(fn () => $this->pmb->hapus($data['no_tes']), 'Tagihan dihapus.');
  }

  public function tutup(Request $request): JsonResponse
  {
    $data = $request->validate(['no_tes' => self::NO_TES]);

    return $this->jalankan(fn () => $this->pmb->tutup($data['no_tes']), 'Tagihan ditutup (lunas).');
  }

  private function filter(Request $request): array
  {
    $request->validate([
      'tahun' => ['nullable', 'digits:4'],
      'status' => ['nullable', 'in:0,1'],
      'id_sms' => ['nullable', 'uuid'],
      'search' => ['nullable', 'string', 'max:100'],
      'limit' => ['nullable', 'integer', 'in:10,25,50,100'],
    ]);

    // Tahun masuk default tahun ini (seperti v1); "semua" dikirim sebagai string kosong.
    return $request->only(['status', 'id_sms', 'search', 'limit'])
      + ['tahun' => $request->has('tahun') ? $request->input('tahun') : date('Y')];
  }

  /** Jalankan aksi; RuntimeException jadi 422 dengan pesannya. */
  private function jalankan(callable $aksi, string $pesan): JsonResponse
  {
    try {
      $aksi();

      return response()->json(['message' => $pesan]);
    } catch (RuntimeException $e) {
      return response()->json(['message' => $e->getMessage()], 422);
    }
  }
}
