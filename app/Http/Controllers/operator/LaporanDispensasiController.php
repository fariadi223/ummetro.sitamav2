<?php

namespace App\Http\Controllers\operator;

use App\Exports\LaporanDispensasiExport;
use App\Http\Controllers\Controller;
use App\Repositories\Laporan\LaporanDispensasiRepository;
use App\Repositories\Laporan\LaporanSppRepository;
use App\Repositories\Mak\BiayaRepository;
use App\Support\TahunAkademik;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Laporan rincian dispensasi pembayaran mahasiswa (port SITAMA v1 /report/rinci_dispensasi). */
class LaporanDispensasiController extends Controller
{
  public function __construct(
    private LaporanDispensasiRepository $laporan,
    private LaporanSppRepository $spp,
    private BiayaRepository $biaya,
  )
  {
  }

  public function index(Request $request): View
  {
    $filter = $this->filter($request);

    return view('content.operator.laporan.dispensasi', [
      'data' => $this->laporan->daftar($filter),
      'ringkasan' => $this->laporan->ringkasan($filter),
      'filter' => $filter,
      'semester' => TahunAkademik::pilihan((int) date('Y') + 1),
      'prodi' => $this->spp->pilihanProdi(),
      'mataAnggaran' => $this->biaya->pilihanMataAnggaran(),
      'angkatan' => range((int) date('Y'), 2015),
    ]);
  }

  public function export(Request $request): BinaryFileResponse
  {
    $filter = $this->filter($request);

    return Excel::download(
      new LaporanDispensasiExport($this->laporan->semua($filter), TahunAkademik::nama($filter['id_smt'])),
      'Laporan_dispensasi_' . $filter['id_smt'] . '_' . date('Ymd_His') . '.xlsx'
    );
  }

  private function filter(Request $request): array
  {
    $request->validate([
      'id_smt' => ['nullable', 'regex:/^\d{4}[12]$/'],
      'id_sms' => ['nullable', 'uuid'],
      'angkatan' => ['nullable', 'digits:4'],
      'ma' => ['nullable', 'integer'],
      'status' => ['nullable', 'in:0,1'],
      'npm' => ['nullable', 'string', 'max:20'],
      'limit' => ['nullable', 'integer', 'in:10,25,50,100'],
    ]);

    // Tahun akademik wajib; default semester aktif seperti v1.
    return ['id_smt' => $request->input('id_smt') ?: TahunAkademik::aktif()]
      + $request->only(['id_sms', 'angkatan', 'ma', 'status', 'npm', 'limit']);
  }
}
