<?php

namespace App\Console\Commands;

use App\Repositories\Laporan\LaporanSppRepository;
use App\Services\SimakuService;
use App\Support\TahunAkademik;
use App\Support\UnitProdi;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Sinkron massal unit jurnal SIMAKU (id_uk + kodeunit) untuk rincian tagihan
 * SPP yang sudah lunas, per tahun akademik dan prodi.
 *
 * Versi batch dari PUT /operator/laporan/spp/kpp/{kppDtl}/unit: unit dipilih
 * otomatis dari nama prodi (UnitProdi::cocokkan), atau dipaksa lewat --unit.
 *
 * Contoh:
 *   php artisan sitama:sinkron-unit-spp --smt=20251
 *   php artisan sitama:sinkron-unit-spp --smt=2025 --prodi=21401 --dry-run
 *   php artisan sitama:sinkron-unit-spp --smt=20251 --prodi=21401 --unit=12
 *   php artisan sitama:sinkron-unit-spp --smt=20251 --dari=2025-09-01 --sampai=2025-09-30
 */
class SinkronUnitJurnalSpp extends Command
{
  protected $signature = 'sitama:sinkron-unit-spp
    {--smt= : Tahun akademik: 5 digit (20251) untuk satu semester, 4 digit (2025) untuk Ganjil + Genap. Default semester aktif}
    {--prodi=* : Kode prodi (tbl_sms.kode_prodi, mis. 21401; boleh lebih dari satu). Kosong = semua prodi}
    {--unit= : Paksa id_uk SIMAKU (hanya bersama satu --prodi)}
    {--ma= : Batasi ke satu mata anggaran}
    {--dari= : Tanggal mulai pembayaran (lunas_date), format YYYY-MM-DD}
    {--sampai= : Tanggal akhir pembayaran (lunas_date, inklusif), format YYYY-MM-DD}
    {--force : Tetap kirim walau unit jurnal sudah sama}
    {--dry-run : Tampilkan rencana tanpa mengubah SIMAKU}';

  protected $description = 'Sinkron unit jurnal SIMAKU untuk rincian tagihan SPP lunas per tahun akademik & prodi';

  private array $hitung = ['diubah' => 0, 'sudah' => 0, 'belum_jurnal' => 0, 'gagal' => 0];

  /** @var array{dari: ?string, sampai: ?string} rentang tanggal lunas */
  private array $rentang = ['dari' => null, 'sampai' => null];

  public function __construct(private LaporanSppRepository $laporan, private SimakuService $simaku)
  {
    parent::__construct();
  }

  public function handle(): int
  {
    $semester = $this->semester();
    if ($semester === null) {
      $this->error('--smt harus berformat YYYY atau YYYYS (S = 1/2).');
      return self::INVALID;
    }

    $dari = $this->tanggal('dari');
    $sampai = $this->tanggal('sampai');
    if ($dari === false || $sampai === false) {
      $this->error('--dari / --sampai harus berformat YYYY-MM-DD.');
      return self::INVALID;
    }
    if ($dari !== null && $sampai !== null && $dari > $sampai) {
      $this->error('--dari tidak boleh lebih besar dari --sampai.');
      return self::INVALID;
    }
    $this->rentang = ['dari' => $dari, 'sampai' => $sampai];

    $prodi = $this->prodi();
    if ($prodi->isEmpty()) {
      $this->error('Prodi tidak ditemukan.');
      return self::INVALID;
    }

    $paksaUnit = $this->option('unit') !== null ? (int) $this->option('unit') : null;
    if ($paksaUnit !== null && $prodi->count() !== 1) {
      $this->error('--unit hanya bisa dipakai bersama tepat satu --prodi.');
      return self::INVALID;
    }

    try {
      $unit = $this->simaku->unit();
    } catch (RuntimeException $e) {
      $this->error('Daftar unit SIMAKU tidak dapat diambil: ' . $e->getMessage());
      return self::FAILURE;
    }

    if ($paksaUnit !== null && !$unit->contains('id_unit', $paksaUnit)) {
      $this->error("Unit {$paksaUnit} tidak dikenal di SIMAKU.");
      return self::INVALID;
    }

    if ($this->option('dry-run')) {
      $this->warn('DRY RUN: SIMAKU tidak diubah.');
    }

    if ($dari !== null || $sampai !== null) {
      $this->info('Tanggal bayar: ' . ($dari ?? 'awal') . ' s.d. ' . ($sampai ?? 'akhir'));
    }

    foreach ($semester as $idSmt) {
      $this->newLine();
      $this->info('== ' . TahunAkademik::nama($idSmt) . " ({$idSmt}) ==");

      foreach ($prodi as $p) {
        $label = trim($p->kode_prodi) . ' ' . trim($p->jenjang_didik) . ' ' . trim($p->nm_lemb);
        $target = $paksaUnit !== null
          ? $unit->firstWhere('id_unit', $paksaUnit)
          : UnitProdi::cocokkan($unit, (string) $p->jenjang_didik, (string) $p->nm_lemb);

        if (!$target) {
          $this->warn("[{$label}] unit SIMAKU tidak cocok, dilewati (pakai --unit).");
          continue;
        }

        $this->sinkronProdi($idSmt, $p->id_sms, $label, $target);
      }
    }

    $this->newLine();
    $this->table(['Diubah', 'Sudah sesuai', 'Belum terjurnal', 'Gagal'], [array_values($this->hitung)]);

    return $this->hitung['gagal'] > 0 ? self::FAILURE : self::SUCCESS;
  }

  private function sinkronProdi(string $idSmt, string $idSms, string $label, array $target): void
  {
    // Rincian lunas saja: jurnal SPM baru ada setelah pembayaran.
    $rincian = $this->laporan->semua(array_filter([
      'id_smt' => $idSmt,
      'id_sms' => $idSms,
      'status' => '1',
      'ma' => $this->option('ma'),
      'dari' => $this->rentang['dari'],
      'sampai' => $this->rentang['sampai'],
    ], fn ($v) => $v !== null))
      // bukti jurnal = bak_tagihan_dtl.id, key_bank = bak_tagihan.api_key.
      ->filter(fn ($r) => trim((string) $r->api_key) !== '')
      ->unique(fn ($r) => trim($r->api_key) . '|' . $r->id)
      ->values();

    if ($rincian->isEmpty()) {
      return;
    }

    $idUk = (int) $target['id_unit'];
    $this->line("[{$label}] {$rincian->count()} rincian -> {$target['nama']} (id_uk {$idUk})");
    $bar = $this->output->createProgressBar($rincian->count());

    foreach ($rincian as $r) {
      $this->sinkronRincian(trim($r->api_key), (string) $r->id, $idUk, trim($r->nipd));
      $bar->advance();
    }

    $bar->finish();
    $this->newLine();
  }

  private function sinkronRincian(string $kunci, string $bukti, int $idUk, string $npm): void
  {
    try {
      $baris = $this->simaku->jurnalSpm(['key_bank' => $kunci, 'bukti' => $bukti]);
    } catch (RuntimeException $e) {
      $this->catatGagal($npm, $bukti, $e->getMessage());
      return;
    }

    if ($baris->isEmpty()) {
      $this->hitung['belum_jurnal']++;
      return;
    }

    if (!$this->option('force') && $baris->every(fn ($b) => (int) ($b->id_uk ?? 0) === $idUk)) {
      $this->hitung['sudah']++;
      return;
    }

    if ($this->option('dry-run')) {
      $this->hitung['diubah']++;
      return;
    }

    try {
      $this->simaku->ubahUnitJurnal($kunci, $bukti, $idUk);
      $this->hitung['diubah']++;
    } catch (RuntimeException $e) {
      $this->catatGagal($npm, $bukti, $e->getMessage());
    }
  }

  private function catatGagal(string $npm, string $bukti, string $pesan): void
  {
    $this->hitung['gagal']++;
    $this->newLine();
    $this->error("  NPM {$npm} bukti {$bukti}: {$pesan}");
  }

  /** @return string|null|false tanggal Y-m-d, null bila kosong, false bila tidak valid */
  private function tanggal(string $opsi): string|null|false
  {
    $nilai = trim((string) $this->option($opsi));
    if ($nilai === '') {
      return null;
    }

    $tgl = \DateTimeImmutable::createFromFormat('!Y-m-d', $nilai);

    return $tgl && $tgl->format('Y-m-d') === $nilai ? $nilai : false;
  }

  /** @return string[]|null daftar id_smt */
  private function semester(): ?array
  {
    $smt = trim((string) ($this->option('smt') ?: TahunAkademik::aktif()));

    return match (true) {
      (bool) preg_match('/^\d{4}[12]$/', $smt) => [$smt],
      (bool) preg_match('/^\d{4}$/', $smt) => ["{$smt}1", "{$smt}2"],
      default => null,
    };
  }

  private function prodi(): Collection
  {
    $kode = array_values(array_filter(array_map('trim', (array) $this->option('prodi'))));

    $prodi = DB::table('tbl_sms')
      ->whereNotNull('nm_lemb')
      ->when($kode, fn ($q) => $q->whereIn(DB::raw('TRIM(kode_prodi)'), $kode))
      ->orderBy('jenjang_didik')->orderBy('nm_lemb')
      ->get(['id_sms', 'kode_prodi', 'jenjang_didik', 'nm_lemb']);

    $tidakAda = array_diff($kode, $prodi->map(fn ($p) => trim((string) $p->kode_prodi))->all());
    if ($tidakAda) {
      $this->warn('Kode prodi tidak ditemukan: ' . implode(', ', $tidakAda));
    }

    return $prodi;
  }
}
