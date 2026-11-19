<?php

namespace App\Support;

/**
 * Perhitungan tahun akademik / semester.
 *
 * Port dari library SITAMA v1 application/libraries/Thnakademik.php.
 * Kode semester (id_smt / thnsmt) berformat YYYYS, S = 1 (Ganjil) / 2 (Genap).
 */
class TahunAkademik
{
  /**
   * Semester aktif (Thnakademik::aktive):
   * Agu–Des = Ganjil tahun ini, Feb–Jul = Genap tahun lalu, Januari = Ganjil tahun lalu.
   */
  public static function aktif(): string
  {
    $now = now();

    return match (true) {
      $now->month >= 8 => $now->year . '1',
      $now->month >= 2 => ($now->year - 1) . '2',
      default => ($now->year - 1) . '1',
    };
  }

  /** "20251" => "2025/2026 - Ganjil". */
  public static function nama(string $idSmt): string
  {
    $thn = (int) substr($idSmt, 0, 4);

    return $thn . '/' . ($thn + 1) . ' - ' . (substr($idSmt, 4, 1) === '2' ? 'Genap' : 'Ganjil');
  }

  /**
   * Pilihan semester dari ($tahunAkhir) mundur ke 2015, Genap lebih dulu.
   *
   * @return array<string, string> id_smt => nama
   */
  public static function pilihan(?int $tahunAkhir = null): array
  {
    $hasil = [];

    for ($x = $tahunAkhir ?? (int) date('Y'); $x >= 2015; $x--) {
      $hasil[$x . '2'] = self::nama($x . '2');
      $hasil[$x . '1'] = self::nama($x . '1');
    }

    return $hasil;
  }

  /**
   * Semester ke-berapa mahasiswa pada $idSmt (Thnakademik::listsmtmhs).
   *
   * @param string $mulai mulai_smt mahasiswa, mis. "20231"
   */
  public static function semesterMahasiswa(string $mulai, ?string $idSmt = null): int
  {
    $idSmt ??= self::aktif();
    $thn = (int) substr($idSmt, 0, 4) - (int) substr($mulai, 0, 4);
    $semz = substr($idSmt, 4, 1);
    $semx = substr($mulai, 4, 1);

    if ($semz === '1') {
      $hasil = $semx === '2' ? $thn * 2 : $thn * 2 + 1;
    } else {
      $hasil = $semx === '2' ? $thn * 2 + 1 : $thn * 2 + 2;
    }

    return max(0, $hasil);
  }

  /**
   * Kode semester (YYYYS) dari semester mahasiswa ke-$ke (Thnakademik::getidsmtms).
   * Dipertahankan apa adanya dari v1 supaya hasilnya sama.
   */
  public static function idSmtKe(string $mulai, int $ke): string
  {
    if ($ke <= 1) {
      return $mulai;
    }

    $thn = (int) substr($mulai, 0, 4);
    $smt = (int) substr($mulai, 4, 1);
    $ix = $smt === 2 ? 1 : 2;

    for ($i = 1; $i <= $ke; $i++) {
      if ($i > $ix) {
        $smt++;
      }

      if ($smt > 2) {
        $thn++;
        $smt = 1;
      }

      if ($i === $ix) {
        $smt = $ix === 1 ? 2 : $smt + 1;
      }
    }

    return $thn . $smt;
  }
}
