<?php

namespace App\Http\Controllers\operator;

use App\Exports\SaldoUktExport;
use App\Exports\SaldoUktRincianExport;
use App\Http\Controllers\Controller;
use App\Repositories\Laporan\LaporanSppRepository;
use App\Repositories\Saldo\SaldoUktRepository;
use App\Repositories\Tagihan\TagihanRepository;
use App\Support\TahunAkademik;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Saldo UKT / beasiswa mahasiswa (port SITAMA v1 /admin/saldoukt). */
class SaldoUktController extends Controller
{
  private const SMT = ['required', 'regex:/^\d{4}[12]$/'];

  public function __construct(
    private SaldoUktRepository $saldo,
    private TagihanRepository $tagihan,
    private LaporanSppRepository $spp,
  ) {
  }

  public function index(Request $request): View
  {
    $filter = $this->filter($request);

    return view('content.operator.saldo-ukt.index', [
      'data' => $this->saldo->daftar($filter),
      'ringkasan' => $this->saldo->ringkasan($filter),
      'filter' => $filter,
      'semester' => TahunAkademik::pilihan((int) date('Y') + 1),
      'semesterAktif' => TahunAkademik::aktif(),
      'prodi' => $this->spp->pilihanProdi(),
      'angkatan' => range((int) date('Y'), 2015),
    ]);
  }

  /** Cari mahasiswa berdasarkan NPM untuk form tambah saldo (v1 admin/search_mhs). */
  public function mahasiswa(Request $request): JsonResponse
  {
    $data = $request->validate(['npm' => ['required', 'string', 'max:20'], 'id_smt' => self::SMT]);
    $mhs = $this->tagihan->cariMahasiswa(trim($data['npm']), $data['id_smt']);

    if (!$mhs) {
      return response()->json(['message' => 'Mahasiswa dengan NPM ini tidak ditemukan.'], 404);
    }

    return response()->json($mhs + ['posisi' => $this->saldo->posisi($mhs['idreg'], $data['id_smt'])]);
  }

  /** Rincian mutasi saldo satu mahasiswa (JSON untuk modal). */
  public function detail(string $idReg): JsonResponse
  {
    $r = $this->saldo->rincian($idReg);
    abort_if(!$r, 404, 'Mahasiswa tidak ditemukan.');
    $m = $r['mahasiswa'];

    return response()->json([
      'id_reg_pd' => $m->id_reg_pd,
      'npm' => trim((string) $m->nipd),
      'nama' => trim((string) $m->nm_pd),
      'angkatan' => substr(trim((string) $m->mulai_smt), 0, 4),
      'prodi' => trim((string) $m->jenjang_didik) . ' ' . trim((string) $m->nm_lemb),
      'saldo' => $r['saldo'],
      'digunakan' => $r['digunakan'],
      'sisa' => $r['sisa'],
      'mutasi' => $r['mutasi']->values(),
    ]);
  }

  public function store(Request $request): JsonResponse
  {
    $data = $request->validate([
      'id_reg_pd' => ['required', 'uuid'],
      'id_smt' => self::SMT,
      'smmhs' => ['required', 'integer', 'min:1', 'max:30'],
      'jenis' => ['required', 'in:' . implode(',', array_keys(SaldoUktRepository::JENIS_INPUT))],
      'jumlah' => ['required', 'integer', 'min:1', 'max:999999999999'],
    ], [
      'id_reg_pd.required' => 'NPM mahasiswa belum diisi.',
      'smmhs.required' => 'Semester mahasiswa belum diisi.',
      'jumlah.required' => 'Jumlah saldo belum diisi.',
      'jumlah.min' => 'Jumlah saldo harus lebih dari 0.',
    ]);

    try {
      $this->saldo->tambah(
        $data['id_reg_pd'], (int) $data['jenis'], (int) $data['jumlah'], $data['id_smt'], (int) $data['smmhs']
      );
    } catch (RuntimeException $e) {
      return response()->json(['message' => $e->getMessage()], 422);
    }

    return response()->json([
      'message' => 'Saldo berhasil ditambahkan.',
      'posisi' => $this->saldo->posisi($data['id_reg_pd'], $data['id_smt']),
    ]);
  }

  public function destroy(int $id): JsonResponse
  {
    try {
      $this->saldo->hapus($id);
    } catch (RuntimeException $e) {
      return response()->json(['message' => $e->getMessage()], 422);
    }

    return response()->json(['message' => 'Saldo dihapus.']);
  }

  /** Ekspor daftar saldo per mahasiswa (v1 ekpordata/saldo_spp), mengikuti filter halaman. */
  public function export(Request $request): BinaryFileResponse
  {
    return Excel::download(
      new SaldoUktExport($this->saldo->semua($this->filter($request))),
      'Saldo_UKT_' . date('Ymd_His') . '.xlsx'
    );
  }

  /** Ekspor rincian mutasi satu mahasiswa (v1 ekpordata/rincian_kip_saldo). */
  public function exportRincian(string $idReg): BinaryFileResponse
  {
    $r = $this->saldo->rincian($idReg);
    abort_if(!$r, 404, 'Mahasiswa tidak ditemukan.');

    return Excel::download(
      new SaldoUktRincianExport($r),
      'Rincian_saldo_' . trim((string) $r['mahasiswa']->nipd) . '_' . date('Ymd_His') . '.xlsx'
    );
  }

  private function filter(Request $request): array
  {
    $request->validate([
      'id_sms' => ['nullable', 'uuid'],
      'angkatan' => ['nullable', 'digits:4'],
      'jenis' => ['nullable', 'in:' . implode(',', array_keys(SaldoUktRepository::JENIS))],
      'status' => ['nullable', 'in:0,1'],
      'npm' => ['nullable', 'string', 'max:50'],
      'limit' => ['nullable', 'integer', 'in:10,25,50,100'],
    ]);

    return $request->only(['id_sms', 'angkatan', 'jenis', 'status', 'npm', 'limit']);
  }
}
