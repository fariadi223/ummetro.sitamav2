<?php

namespace App\Http\Controllers\operator;

use App\Exports\LaporanTotalTagihanExport;
use App\Http\Controllers\Controller;
use App\Repositories\Laporan\LaporanSppRepository;
use App\Repositories\Laporan\LaporanTotalTagihanRepository;
use App\Support\TahunAkademik;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Laporan total tagihan mahasiswa (port SITAMA v1 /report/total_tagihan). */
class LaporanTotalTagihanController extends Controller
{
  public function __construct(
    private LaporanTotalTagihanRepository $laporan,
    private LaporanSppRepository $spp,
  )
  {
  }

  public function index(Request $request): View
  {
    $filter = $this->filter($request);

    return view('content.operator.laporan.total-tagihan', [
      'data' => $this->laporan->daftar($filter),
      'ringkasan' => $this->laporan->ringkasan($filter),
      'filter' => $filter,
      'semester' => TahunAkademik::pilihan((int) date('Y') + 1),
      'prodi' => $this->spp->pilihanProdi(),
      'angkatan' => range((int) date('Y'), 2015),
    ]);
  }

  /** Rincian tagihan & pembayaran satu mahasiswa di semester terpilih (JSON untuk modal). */
  public function detail(Request $request, string $idReg): JsonResponse
  {
    $idSmt = $this->filter($request)['id_smt'];
    $r = $this->laporan->rincian($idReg, $idSmt);
    abort_if(!$r, 404, 'Mahasiswa tidak ditemukan.');

    $tgl = fn ($v) => $v ? date('d-m-Y', strtotime($v)) : null;
    $m = $r['mahasiswa'];
    $total = (int) $r['tagihan']->sum('uang');
    $dibayar = (int) $r['pembayaran']->sum('uang');

    return response()->json([
      'semester' => TahunAkademik::nama($idSmt),
      'npm' => trim((string) $m->nipd),
      'nama' => trim((string) $m->nm_pd),
      'angkatan' => substr(trim((string) $m->mulai_smt), 0, 4),
      'prodi' => trim((string) $m->jenjang_didik) . ' ' . trim((string) $m->nm_lemb),
      'total' => $total,
      'dibayar' => $dibayar,
      'sisa' => $total - $dibayar,
      'tagihan' => $r['tagihan']->map(fn ($t) => [
        'struk_id' => $t->struk_id,
        'tanggal' => $tgl($t->input_date),
        'ma' => (int) $t->ma,
        'keterangan' => trim((string) $t->keterangan),
        'uang' => (int) $t->uang,
        'lunas' => (int) $t->lunas === 1,
        'tgl_lunas' => $tgl($t->lunas_date),
      ])->values(),
      'pembayaran' => $r['pembayaran']->map(fn ($p) => [
        'kpp_dtl' => (int) $p->kpp_dtl,
        'tanggal' => $tgl($p->create_date),
        'angsur_ke' => $p->angsur_ke,
        'bank' => $p->nm_bank ? trim($p->nm_bank) : null,
        'ma' => (int) $p->ma,
        'keterangan' => trim((string) $p->keterangan),
        'uang' => (int) $p->uang,
      ])->values(),
    ]);
  }

  public function export(Request $request): BinaryFileResponse
  {
    $filter = $this->filter($request);

    return Excel::download(
      new LaporanTotalTagihanExport($this->laporan->semua($filter), TahunAkademik::nama($filter['id_smt'])),
      'Laporan_total_tagihan_' . $filter['id_smt'] . '_' . date('Ymd_His') . '.xlsx'
    );
  }

  private function filter(Request $request): array
  {
    $request->validate([
      'id_smt' => ['nullable', 'regex:/^\d{4}[12]$/'],
      'id_sms' => ['nullable', 'uuid'],
      'angkatan' => ['nullable', 'digits:4'],
      'status' => ['nullable', 'in:0,1'],
      'npm' => ['nullable', 'string', 'max:20'],
      'limit' => ['nullable', 'integer', 'in:10,25,50,100'],
    ]);

    // Tahun akademik wajib; default semester aktif seperti v1.
    return ['id_smt' => $request->input('id_smt') ?: TahunAkademik::aktif()]
      + $request->only(['id_sms', 'angkatan', 'status', 'npm', 'limit']);
  }
}
