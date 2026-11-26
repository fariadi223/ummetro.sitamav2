<?php

namespace App\Http\Controllers\operator;

use App\Exports\LaporanSppExport;
use App\Http\Controllers\Controller;
use App\Repositories\Laporan\LaporanSppRepository;
use App\Repositories\Laporan\SinkronJurnalLogRepository;
use App\Repositories\Mak\BiayaRepository;
use App\Services\SimakuService;
use App\Services\SinkronUnitJurnalService;
use App\Support\UnitProdi;
use App\Support\TahunAkademik;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use RuntimeException;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Laporan rincian tagihan SPP (port SITAMA v1 /report/rinci_tagihan). */
class LaporanSppController extends Controller
{
  public function __construct(
    private LaporanSppRepository $laporan,
    private BiayaRepository $biaya,
    private SimakuService $simaku,
    private SinkronUnitJurnalService $sinkron,
    private SinkronJurnalLogRepository $log,
  )
  {
  }

  public function index(Request $request): View
  {
    $filter = $this->filter($request);

    return view('content.operator.laporan.spp', [
      'data' => $this->laporan->daftar($filter),
      'ringkasan' => $this->laporan->ringkasan($filter),
      'filter' => $filter,
      'semester' => TahunAkademik::pilihan((int) date('Y') + 1),
      'prodi' => $this->laporan->pilihanProdi(),
      'mataAnggaran' => $this->biaya->pilihanMataAnggaran(),
      'angkatan' => range((int) date('Y'), 2015),
    ]);
  }

  /** Detail satu record KPP (JSON) berdasarkan primary key kpp_dtl. */
  public function detail(int $kppDtl): JsonResponse
  {
    return response()->json($this->dataDetail($kppDtl));
  }

  /**
   * Sinkron unit jurnal SIMAKU (id_uk + kodeunit) untuk rincian KPP ini.
   * Unit dipilih operator di modal; awalnya diisi otomatis sesuai nama prodi.
   */
  public function sinkronUnit(Request $request, int $kppDtl): JsonResponse
  {
    $idUk = (int) $request->validate(['id_uk' => ['required', 'integer', 'min:1']])['id_uk'];
    $data = $this->dataDetail($kppDtl);
    $jurnal = $data['jurnal'];

    if (!in_array($jurnal['status'], ['terjurnal', 'selisih'], true) || empty($jurnal['bukti'])) {
      return response()->json(['message' => $jurnal['pesan'] ?: 'Jurnal belum ada, unit tidak bisa disinkronkan.'], 422);
    }

    if (!collect($data['unit']['pilihan'] ?? [])->contains('id_unit', $idUk)) {
      return response()->json(['message' => 'Unit tidak dikenal di SIMAKU.'], 422);
    }

    $k = $this->laporan->detailKpp($kppDtl)->induk ?? null;
    $kunci = trim((string) ($k->key_tagihan ?? ''));
    $bukti = (string) $jurnal['bukti'];
    $target = collect($data['unit']['pilihan'])->firstWhere('id_unit', $idUk);
    $logId = $this->log->mulai([
      'sumber' => 'manual',
      'id_smt' => $k ? trim((string) $k->thn_smt) : null,
      'id_sms' => $k->id_sms ?? null,
      'prodi' => $data['prodi'],
      'filter' => ['kpp_dtl' => $kppDtl],
      'id_uk' => $idUk,
      'unit_kode' => $target['kode'] ?? null,
      'unit_nama' => $target['nama'] ?? null,
      'force' => true,
      'total' => 1,
    ] + $this->operator());
    $detail = [
      'npm' => $data['npm'],
      'key_bank' => $kunci,
      'bukti' => $bukti,
      'id_uk_lama' => collect($jurnal['baris'])->pluck('id_uk')->unique()->sort()->implode(','),
    ];

    try {
      $hasil = $this->simaku->ubahUnitJurnal($kunci, $bukti, $idUk);
    } catch (RuntimeException $e) {
      $this->log->tambahHasil($logId, ['gagal' => 1], [$detail + ['hasil' => 'gagal', 'baris' => null, 'pesan' => $e->getMessage()]]);
      $this->log->selesai($logId);

      return response()->json(['message' => $e->getMessage()], 502);
    }

    $this->log->tambahHasil($logId, ['diubah' => 1], [$detail + ['hasil' => 'diubah', 'baris' => (int) ($hasil['diubah'] ?? 0), 'pesan' => null]]);
    $this->log->selesai($logId);

    $baru = $this->dataDetail($kppDtl);
    $baru['message'] = 'Unit jurnal diperbarui ke ' . ($hasil['unit'] ?? 'unit terpilih')
      . ' (kodeunit ' . ($hasil['kodeunit'] ?? '-') . ', ' . (int) ($hasil['diubah'] ?? 0) . ' baris).';

    return response()->json($baru);
  }

  /** Jumlah rincian per permintaan sinkron massal (tiap rincian 1–2 panggilan SIMAKU). */
  private const BATCH_SINKRON = 20;

  /**
   * Rencana sinkron massal unit jurnal untuk filter aktif: jumlah rincian
   * lunas dan unit SIMAKU tujuan (dicocokkan dari nama prodi).
   */
  public function rencanaSinkronJurnal(Request $request): JsonResponse
  {
    [$filter, $prodi, $unit] = $this->siapSinkron($request);

    return response()->json([
      'total' => $this->sinkron->rincian($filter)->count(),
      'prodi' => trim($prodi->jenjang_didik) . ' ' . trim($prodi->nm_lemb),
      'semester' => TahunAkademik::nama($filter['id_smt']),
      'rentang' => trim($this->labelRentang($filter), ' ·'),
      'unit' => Arr::only($unit, ['id_unit', 'kode', 'nama']),
    ]);
  }

  /**
   * Sinkron massal unit jurnal SIMAKU (versi web command sitama:sinkron-unit-spp),
   * satu batch per permintaan mulai dari `offset`; halaman memanggil berulang
   * sampai `selesai`.
   */
  public function sinkronJurnal(Request $request): JsonResponse
  {
    $v = $request->validate([
      'offset' => ['required', 'integer', 'min:0'],
      'log_id' => ['nullable', 'integer'],
    ]);
    $offset = (int) $v['offset'];
    [$filter, $prodi, $unit] = $this->siapSinkron($request);

    $rincian = $this->sinkron->rincian($filter);
    $batch = $rincian->slice($offset, self::BATCH_SINKRON);
    $idUk = (int) $unit['id_unit'];

    // Batch pertama membuka log baru; batch berikutnya melanjutkan log yang sama.
    $logId = $this->logSinkron($v['log_id'] ?? null, $offset, fn () => [
      'sumber' => 'web',
      'id_smt' => $filter['id_smt'],
      'id_sms' => $filter['id_sms'],
      'prodi' => trim($prodi->jenjang_didik) . ' ' . trim($prodi->nm_lemb),
      'filter' => Arr::except($filter, ['id_smt', 'id_sms']),
      'id_uk' => $idUk,
      'unit_kode' => $unit['kode'] ?? null,
      'unit_nama' => $unit['nama'] ?? null,
      'total' => $rincian->count(),
    ]);

    $hasil = $this->sinkron->proses($logId, $batch, $idUk);
    $berikut = $offset + $batch->count();
    $selesai = $berikut >= $rincian->count();

    if ($selesai) {
      $this->log->selesai($logId);
    }

    return response()->json([
      'log_id' => $logId,
      'total' => $rincian->count(),
      'offset' => $berikut,
      'selesai' => $selesai,
      'hitung' => $hasil['hitung'],
      'galat' => $hasil['galat'],
    ]);
  }

  /**
   * Id log sinkron web: buka baru bila offset 0, selain itu pakai log_id
   * milik operator yang sama dan belum selesai.
   */
  private function logSinkron(?int $logId, int $offset, callable $data): int
  {
    if ($offset === 0 || !$logId) {
      return $this->log->mulai($data() + $this->operator());
    }

    $log = $this->log->cari($logId);
    abort_if(!$log || $log->selesai_at || (string) $log->user_id !== (string) Auth::id(), 422, 'Log sinkron tidak valid, ulangi sync dari awal.');

    return $logId;
  }

  /** @return array{user_id: ?string, user_nama: ?string} */
  private function operator(): array
  {
    $user = Auth::user();

    return ['user_id' => $user ? (string) Auth::id() : null, 'user_nama' => $user->name ?? null];
  }

  /** Riwayat sinkron unit jurnal SIMAKU. */
  public function logSinkronJurnal(Request $request): View
  {
    $f = $request->validate([
      'id_smt' => ['nullable', 'regex:/^\d{4}[12]$/'],
      'id_sms' => ['nullable', 'uuid'],
      'sumber' => ['nullable', 'in:' . implode(',', array_keys(SinkronJurnalLogRepository::SUMBER))],
    ]);

    return view('content.operator.laporan.spp-sinkron-log', [
      'data' => $this->log->daftar($f),
      'filter' => $f,
      'semester' => TahunAkademik::pilihan((int) date('Y') + 1),
      'prodi' => $this->laporan->pilihanProdi(),
    ]);
  }

  /** Detail per bukti satu log sinkron (JSON). */
  public function detailLogSinkronJurnal(Request $request, int $log): JsonResponse
  {
    $hasil = $request->validate(['hasil' => ['nullable', 'in:' . implode(',', array_keys(SinkronJurnalLogRepository::HASIL))]])['hasil'] ?? null;
    abort_if(!$this->log->cari($log), 404, 'Log tidak ditemukan.');

    return response()->json($this->log->detail($log, $hasil));
  }

  /**
   * Filter wajib untuk sinkron massal: prodi dan rentang tanggal lunas.
   *
   * @return array{0: array, 1: object, 2: array} filter, prodi, unit SIMAKU tujuan
   */
  private function siapSinkron(Request $request): array
  {
    $filter = $this->filter($request);
    $request->validate([
      'id_sms' => ['required'],
      'dari' => ['required'],
      'sampai' => ['required'],
    ], [
      'id_sms.required' => 'Pilih program studi dulu.',
      'dari.required' => 'Isi tanggal lunas mulai.',
      'sampai.required' => 'Isi tanggal lunas akhir.',
    ]);

    $prodi = $this->laporan->pilihanProdi()->firstWhere('id_sms', $filter['id_sms']);
    abort_if(!$prodi, 422, 'Program studi tidak ditemukan.');

    try {
      $unit = UnitProdi::cocokkan($this->simaku->unit(), (string) $prodi->jenjang_didik, (string) $prodi->nm_lemb);
    } catch (RuntimeException $e) {
      abort(502, 'Daftar unit SIMAKU tidak dapat diambil: ' . $e->getMessage());
    }
    abort_if(!$unit, 422, 'Unit SIMAKU yang cocok dengan prodi ini tidak ditemukan. Gunakan command sitama:sinkron-unit-spp --unit=…');

    // Laporan bisa difilter "Belum lunas", tetapi yang disinkron selalu rincian lunas.
    unset($filter['status'], $filter['limit']);

    return [$filter, $prodi, $unit];
  }

  private function dataDetail(int $kppDtl): array
  {
    $detail = $this->laporan->detailKpp($kppDtl);
    abort_if(!$detail, 404, 'Rincian KPP tidak ditemukan.');

    $d = $detail->dtl;
    $k = $detail->induk;
    $tgl = fn ($v, $f = 'd-m-Y') => $v ? date($f, strtotime($v)) : null;
    $jurnal = $this->statusJurnal($k, (int) $d->ma, (int) $d->uang);

    return [
      'kpp_dtl' => (int) $d->kpp_dtl,
      'kppmhs' => $d->kppmhs,
      'ma' => (int) $d->ma,
      'keterangan' => trim((string) $d->keterangan),
      'uang' => (int) $d->uang,
      'semester' => $k ? TahunAkademik::nama((string) $k->thn_smt) : null,
      'smt_mhs' => $k->smt_mhs ?? null,
      'angsur_ke' => $k ? (int) $k->angsur_ke : null,
      'tanggal' => $tgl($k->create_date ?? null),
      'bank' => !empty($k->nm_bank) ? trim($k->nm_bank) : null,
      'struk_id' => $k->struk_id ?? null,
      'tgl_lunas' => $tgl($k->lunas_date ?? null),
      'npm' => $k ? trim((string) $k->nipd) : null,
      'nama' => $k ? trim((string) $k->nm_pd) : null,
      'angkatan' => $k ? substr(trim((string) $k->mulai_smt), 0, 4) : null,
      'prodi' => $k ? trim((string) $k->jenjang_didik) . ' ' . trim((string) $k->nm_lemb) : null,
      'id_rincian' => $k->id_rincian ?? null,
      'jurnal' => array_diff_key($jurnal, ['key_bank' => 1]),
      'unit' => $jurnal['baris']->isNotEmpty() && $k ? $this->pilihanUnit($k) : null,
    ];
  }

  /** Unit SIMAKU yang cocok dengan nama prodi mahasiswa, plus seluruh pilihan unit. */
  private function pilihanUnit(object $k): array
  {
    try {
      $unit = $this->simaku->unit();
    } catch (RuntimeException $e) {
      return ['saran' => null, 'pilihan' => [], 'galat' => 'Daftar unit SIMAKU tidak dapat diambil: ' . $e->getMessage()];
    }

    return [
      'saran' => UnitProdi::cocokkan($unit, (string) $k->jenjang_didik, (string) $k->nm_lemb),
      'pilihan' => $unit->map(fn ($u) => Arr::only($u, ['id_unit', 'kode', 'nama']))->values(),
      'galat' => null,
    ];
  }

  /**
   * Cek jurnal SPM di SIMAKU untuk satu rincian KPP. Terjurnal bila ada
   * pasangan debet (rekening bank) dan kredit ke MA rincian ini.
   */
  private function statusJurnal(?object $k, int $ma, int $uang): array
  {
    $kunci = trim((string) ($k->key_tagihan ?? ''));
    if ($kunci === '') {
      return ['status' => 'tanpa_kunci', 'pesan' => 'KPP tidak punya key_tagihan, jurnal tidak bisa dicocokkan.', 'baris' => collect()];
    }

    try {
      $bukti = $k->id_rincian ?? null;
      $baris = $this->simaku->jurnalSpm(['key_bank' => $kunci, 'bukti' => $bukti]);

      // Tanpa bukti: ambil bukti dari baris kredit ber-MA sama, lalu sertakan pasangan debetnya.
      if (!$bukti) {
        $bukti = $baris->first(fn ($r) => (int) $r->idrek6 === $ma && (int) $r->kredit > 0)->bukti ?? null;
        $baris = $baris->where('bukti', $bukti)->values();
      }
    } catch (RuntimeException $e) {
      return ['status' => 'galat', 'pesan' => 'SIMAKU tidak dapat dihubungi: ' . $e->getMessage(), 'baris' => collect()];
    }

    $kredit = (int) $baris->filter(fn ($r) => (int) $r->idrek6 === $ma)->sum('kredit');
    $debet = (int) $baris->sum('debet');
    $status = match (true) {
      $baris->isEmpty() => 'belum',
      $kredit === $uang && $debet === $uang => 'terjurnal',
      default => 'selisih',
    };

    // Nama COA & unit dari daftar SIMAKU (di-cache); kosong bila gagal.
    try {
      $coa = $this->simaku->rekeningLevel6()->keyBy(fn ($r) => (int) $r['ma']);
    } catch (RuntimeException) {
      $coa = collect();
    }
    try {
      $unit = $this->simaku->unit()->keyBy('id_unit');
    } catch (RuntimeException) {
      $unit = collect();
    }

    return [
      'status' => $status,
      'bukti' => $bukti,
      'key_bank' => $kunci,
      'pesan' => match ($status) {
        'belum' => 'Belum ada jurnal di SIMAKU untuk rincian ini.',
        'selisih' => 'Jurnal ditemukan, tetapi nominal debet/kredit tidak sama dengan KPP.',
        default => null,
      },
      'baris' => $baris->map(fn ($r) => [
        'id_trans' => (int) $r->id_trans,
        'idrek6' => (int) $r->idrek6,
        'rek_kode' => $coa[(int) $r->idrek6]['kode'] ?? null,
        'rek_nama' => $coa[(int) $r->idrek6]['nama'] ?? null,
        'debet' => (int) $r->debet,
        'kredit' => (int) $r->kredit,
        'tgl' => $r->tgl_trk ? date('d-m-Y', strtotime($r->tgl_trk)) : null,
        'bukti' => $r->bukti,
        'id_uk' => (int) ($r->id_uk ?? 0),
        'kodeunit' => (int) ($r->kodeunit ?? 0),
        'unit_nama' => $unit[(int) ($r->id_uk ?? 0)]['nama'] ?? null,
      ])->sortBy('id_trans')->values(),
    ];
  }

  public function export(Request $request): BinaryFileResponse
  {
    $filter = $this->filter($request);

    return Excel::download(
      new LaporanSppExport($this->laporan->semua($filter), TahunAkademik::nama($filter['id_smt']) . $this->labelRentang($filter)),
      'Laporan_rincian_tagihan_spp_' . $filter['id_smt'] . '_' . date('Ymd_His') . '.xlsx'
    );
  }

  /** Keterangan rentang tanggal lunas untuk judul ekspor, mis. " · Lunas 01-08-2026 s.d. 31-08-2026". */
  private function labelRentang(array $f): string
  {
    $tgl = fn ($v) => date('d-m-Y', strtotime($v));
    $dari = $f['dari'] ?? null;
    $sampai = $f['sampai'] ?? null;

    return match (true) {
      $dari && $sampai => ' · Lunas ' . $tgl($dari) . ' s.d. ' . $tgl($sampai),
      (bool) $dari => ' · Lunas sejak ' . $tgl($dari),
      (bool) $sampai => ' · Lunas s.d. ' . $tgl($sampai),
      default => '',
    };
  }

  private function filter(Request $request): array
  {
    $request->validate([
      'id_smt' => ['nullable', 'regex:/^\d{4}[12]$/'],
      'id_sms' => ['nullable', 'uuid'],
      'angkatan' => ['nullable', 'digits:4'],
      'status' => ['nullable', 'in:0,1'],
      'ma' => ['nullable', 'integer'],
      'npm' => ['nullable', 'string', 'max:20'],
      'dari' => ['nullable', 'date_format:Y-m-d'],
      'sampai' => array_filter(['nullable', 'date_format:Y-m-d', $request->filled('dari') ? 'after_or_equal:dari' : null]),
      'limit' => ['nullable', 'integer', 'in:10,25,50,100'],
    ]);

    // Tahun akademik wajib (tabel besar); default semester aktif seperti v1.
    return ['id_smt' => $request->input('id_smt') ?: TahunAkademik::aktif()]
      + $request->only(['id_sms', 'angkatan', 'status', 'ma', 'npm', 'dari', 'sampai', 'limit']);
  }
}
