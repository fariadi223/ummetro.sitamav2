<?php

namespace App\Http\Controllers\operator;

use App\Http\Controllers\Controller;
use App\Repositories\Pembayaran\PenerimaanYudisiumRepository;
use App\Support\TahunAkademik;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * Penerimaan struk yudisium (port SITAMA v1 /admin/penerimaan_yudisium).
 * Daftar memakai GET + paginasi; cari & klik lunas memakai AJAX (JSON).
 */
class PenerimaanYudisiumController extends Controller
{
  public function __construct(private PenerimaanYudisiumRepository $yudisium)
  {
  }

  public function index(Request $request): View
  {
    $request->validate([
      'search' => ['nullable', 'string', 'max:100'],
      'psstudi' => ['nullable', 'string', 'max:10'],
      'dari' => ['nullable', 'date'],
      'sampai' => ['nullable', 'date'],
      'limit' => ['nullable', 'integer', 'in:10,25,50,100'],
    ]);
    $filter = $request->only(['search', 'psstudi', 'dari', 'sampai', 'limit']);

    return view('content.operator.penerimaan-yudisium.index', [
      'data' => $this->yudisium->daftar($filter),
      'ringkasan' => $this->yudisium->ringkasan(),
      'filter' => $filter,
      'prodi' => $this->yudisium->pilihanProdi(),
      'semester' => TahunAkademik::pilihan((int) date('Y') + 1),
      'semesterAktif' => TahunAkademik::aktif(),
    ]);
  }

  public function mahasiswa(Request $request): JsonResponse
  {
    $data = $request->validate(['npm' => ['required', 'string', 'max:20'], 'id_smt' => ['required', 'regex:/^\d{4}[12]$/']]);
    $mhs = $this->yudisium->cariMahasiswa(trim($data['npm']), $data['id_smt']);

    return $mhs
      ? response()->json($mhs)
      : response()->json(['message' => 'Mahasiswa dengan NPM ini tidak ditemukan.'], 404);
  }

  public function store(Request $request): JsonResponse
  {
    $data = $request->validate(['npm' => ['required', 'string', 'max:20']], ['npm.required' => 'NPM mahasiswa belum diisi.']);

    try {
      $this->yudisium->lunasi(trim($data['npm']));

      return response()->json(['message' => 'Data pelunasan yudisium berhasil disimpan.']);
    } catch (RuntimeException $e) {
      return response()->json(['message' => $e->getMessage()], 422);
    }
  }
}
