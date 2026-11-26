<?php

namespace App\Repositories\Laporan;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Log sinkron unit jurnal SIMAKU.
 * Tabel: bak_sinkron_jurnal_log (header per proses), bak_sinkron_jurnal_log_dtl (per bukti).
 */
class SinkronJurnalLogRepository
{
  public const SUMBER = ['web' => 'Web', 'command' => 'Command', 'manual' => 'Per rincian'];
  public const HASIL = ['diubah' => 'Diubah', 'sudah' => 'Sudah sesuai', 'belum_jurnal' => 'Belum terjurnal', 'gagal' => 'Gagal'];

  private const HEADER = 'bak_sinkron_jurnal_log';
  private const DETAIL = 'bak_sinkron_jurnal_log_dtl';

  /**
   * Buka log proses sinkron baru.
   *
   * @param array{sumber: string, user_id?: ?string, user_nama?: ?string, id_smt?: ?string, id_sms?: ?string,
   *   prodi?: ?string, filter?: array, id_uk: int, unit_kode?: ?string, unit_nama?: ?string,
   *   dry_run?: bool, force?: bool, total?: int} $data
   */
  public function mulai(array $data): int
  {
    $data['filter'] = isset($data['filter'])
      ? json_encode(array_filter($data['filter'], fn ($v) => $v !== null && $v !== ''))
      : null;

    return (int) DB::table(self::HEADER)->insertGetId($data + ['mulai_at' => now()]);
  }

  /** Tambah hitungan dan detail per bukti ke log. */
  public function tambahHasil(int $logId, array $hitung, array $detail): void
  {
    if (!$detail) {
      return;
    }

    DB::transaction(function () use ($logId, $hitung, $detail) {
      $waktu = now();
      foreach (array_chunk($detail, 200) as $potong) {
        DB::table(self::DETAIL)->insert(array_map(fn ($d) => [
          'log_id' => $logId,
          'npm' => $d['npm'],
          'key_bank' => $d['key_bank'],
          'bukti' => $d['bukti'],
          'hasil' => $d['hasil'],
          'id_uk_lama' => $d['id_uk_lama'],
          'baris' => $d['baris'],
          'pesan' => $d['pesan'],
          'created_at' => $waktu,
        ], $potong));
      }

      $naik = ['diproses' => DB::raw('diproses + ' . count($detail))];
      foreach (array_keys(self::HASIL) as $k) {
        $naik[$k] = DB::raw("{$k} + " . (int) ($hitung[$k] ?? 0));
      }
      DB::table(self::HEADER)->where('id', $logId)->update($naik);
    });
  }

  public function selesai(int $logId): void
  {
    DB::table(self::HEADER)->where('id', $logId)->update(['selesai_at' => now()]);
  }

  public function cari(int $logId): ?object
  {
    return DB::table(self::HEADER)->find($logId);
  }

  public function daftar(array $f): LengthAwarePaginator
  {
    return DB::table(self::HEADER)
      ->when($f['id_smt'] ?? null, fn ($q, $v) => $q->where('id_smt', $v))
      ->when($f['id_sms'] ?? null, fn ($q, $v) => $q->where('id_sms', $v))
      ->when($f['sumber'] ?? null, fn ($q, $v) => $q->where('sumber', $v))
      ->orderByDesc('id')
      ->paginate(25)
      ->withQueryString();
  }

  public function detail(int $logId, ?string $hasil = null): Collection
  {
    return DB::table(self::DETAIL)
      ->where('log_id', $logId)
      ->when($hasil, fn ($q, $v) => $q->where('hasil', $v))
      ->orderBy('id')
      ->get();
  }
}
