<?php

namespace App\Services;

use App\Repositories\Laporan\LaporanSppRepository;
use App\Repositories\Laporan\SinkronJurnalLogRepository;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Sinkron unit jurnal SIMAKU (id_uk + kodeunit) untuk rincian tagihan SPP
 * lunas. Dipakai command sitama:sinkron-unit-spp dan tombol "Sync jurnal
 * SIMAKU" di Laporan SPP. Tiap proses dicatat di bak_sinkron_jurnal_log.
 */
class SinkronUnitJurnalService
{
  public const DIUBAH = 'diubah';
  public const SUDAH = 'sudah';
  public const BELUM_JURNAL = 'belum_jurnal';
  public const GAGAL = 'gagal';

  public function __construct(
    private LaporanSppRepository $laporan,
    private SimakuService $simaku,
    private SinkronJurnalLogRepository $log,
  )
  {
  }

  /**
   * Rincian lunas yang punya payment key (jurnal SPM baru ada setelah
   * pembayaran). Bukti jurnal = bak_tagihan_dtl.id, key_bank = bak_tagihan.api_key.
   * Urutan stabil (lihat LaporanSppRepository::kolom), aman dipotong per batch.
   */
  public function rincian(array $filter): Collection
  {
    $filter = array_filter(['status' => '1'] + $filter, fn ($v) => $v !== null && $v !== '');

    return $this->laporan->semua($filter)
      ->filter(fn ($r) => trim((string) $r->api_key) !== '')
      ->unique(fn ($r) => trim($r->api_key) . '|' . $r->id)
      ->values();
  }

  /**
   * Sinkron satu rincian ke unit $idUk.
   *
   * @return array{hasil: string, id_uk_lama: ?string, baris: ?int}
   * @throws RuntimeException bila SIMAKU gagal
   */
  public function sinkron(string $kunci, string $bukti, int $idUk, bool $force = false, bool $dryRun = false): array
  {
    $jurnal = $this->simaku->jurnalSpm(['key_bank' => $kunci, 'bukti' => $bukti]);

    if ($jurnal->isEmpty()) {
      return ['hasil' => self::BELUM_JURNAL, 'id_uk_lama' => null, 'baris' => null];
    }

    $lama = $jurnal->map(fn ($b) => (int) ($b->id_uk ?? 0))->unique()->sort()->implode(',');

    if (!$force && $jurnal->every(fn ($b) => (int) ($b->id_uk ?? 0) === $idUk)) {
      return ['hasil' => self::SUDAH, 'id_uk_lama' => $lama, 'baris' => null];
    }

    $diubah = $dryRun ? null : (int) ($this->simaku->ubahUnitJurnal($kunci, $bukti, $idUk)['diubah'] ?? 0);

    return ['hasil' => self::DIUBAH, 'id_uk_lama' => $lama, 'baris' => $diubah];
  }

  /**
   * Sinkron sekumpulan rincian dan catat hasilnya ke log $logId.
   *
   * @param iterable<object> $rincian baris dari rincian()
   * @param callable|null $tiap dipanggil setelah tiap rincian: fn (string $hasil, object $r, ?string $pesan)
   * @return array{hitung: array<string,int>, galat: string[]}
   */
  public function proses(int $logId, iterable $rincian, int $idUk, bool $force = false, bool $dryRun = false, ?callable $tiap = null): array
  {
    $hitung = [self::DIUBAH => 0, self::SUDAH => 0, self::BELUM_JURNAL => 0, self::GAGAL => 0];
    $galat = [];
    $detail = [];

    foreach ($rincian as $r) {
      $kunci = trim((string) $r->api_key);
      $bukti = (string) $r->id;
      $npm = trim((string) $r->nipd);
      $pesan = null;

      try {
        $h = $this->sinkron($kunci, $bukti, $idUk, $force, $dryRun);
      } catch (RuntimeException $e) {
        $pesan = $e->getMessage();
        $h = ['hasil' => self::GAGAL, 'id_uk_lama' => null, 'baris' => null];
        $galat[] = "NPM {$npm} bukti {$bukti}: {$pesan}";
      }

      $hitung[$h['hasil']]++;
      $detail[] = ['npm' => $npm, 'key_bank' => $kunci, 'bukti' => $bukti, 'pesan' => $pesan] + $h;

      if ($tiap) {
        $tiap($h['hasil'], $r, $pesan);
      }
    }

    $this->log->tambahHasil($logId, $hitung, $detail);

    return ['hitung' => $hitung, 'galat' => $galat];
  }
}
