<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Mencocokkan prodi SITAMA (jenjang + nama) dengan unit SIMAKU berdasarkan
 * nama. Nama unit SIMAKU tidak seragam ("Prodi Akutansi (D3)",
 * "Prodi Magister Pend. Biologi", "Prodi PIAUD"), jadi singkatan dan salah
 * ketik umum dinormalkan dulu, lalu dibandingkan per kata plus jenjang.
 */
class UnitProdi
{
  private const GANTI = [
    'pend.' => 'pendidikan ', 'pend ' => 'pendidikan ', 'bhs.' => 'bahasa ', 'bimb.' => 'bimbingan ',
    'akutansi' => 'akuntansi', 'megister' => 'magister', 'fisiotrapi' => 'fisioterapi',
  ];

  private const SINGKATAN = [
    'piaud' => 'pendidikan islam anak usia dini',
    'pgsd' => 'pendidikan guru sekolah dasar',
    'ppg' => 'pendidikan profesi guru',
    'pai' => 'pendidikan agama islam',
    'kpi' => 'komunikasi penyiaran islam',
    'ipa' => 'pendidikan ilmu pengetahuan alam',
    'bk' => 'bimbingan konseling',
    'dokter' => 'kedokteran',
  ];

  private const ABAIKAN = ['prodi', 'program', 'studi', 'dan', 'ilmu', 's1', 's2', 'd3', 'magister', 'profesi'];

  /** Unit prodi terbaik untuk prodi ini, atau null bila tidak ada yang cukup mirip. */
  public static function cocokkan(Collection $unit, string $jenjang, string $nama): ?array
  {
    $jenjang = strtolower(trim($jenjang));
    $kataProdi = self::kata($nama);
    $terbaik = null;
    $skorTerbaik = 0.0;

    foreach ($unit as $u) {
      if (!str_starts_with(strtolower(ltrim($u['nama'])), 'prodi')) {
        continue;
      }

      $teks = self::normal($u['nama']);
      $kataUnit = self::kata($u['nama']);
      $sama = count(array_intersect($kataProdi, $kataUnit));
      $semua = count(array_unique(array_merge($kataProdi, $kataUnit)));
      $skor = $semua ? $sama / $semua : 0;

      // Jenjang: magister = S2, profesi = Profesi, D3/S1 bila disebut di nama unit.
      $jenjangUnit = match (true) {
        str_contains($teks, 'magister') => 's2',
        str_contains($teks, 'profesi') && !str_contains($teks, 'pendidikan profesi guru') => 'profesi',
        str_contains($teks, 'pendidikan profesi guru') => 'profesi',
        (bool) preg_match('/\bd3\b/', $teks) => 'd3',
        (bool) preg_match('/\bs1\b/', $teks) => 's1',
        default => null,
      };
      if ($jenjangUnit !== null) {
        $skor += $jenjangUnit === $jenjang ? 0.3 : -0.6;
      } elseif (in_array($jenjang, ['s2', 'profesi'], true)) {
        $skor -= 0.6;
      }

      if ($u['status'] === 'ok') {
        $skor += 0.05;
      }

      if ($skor > $skorTerbaik) {
        [$terbaik, $skorTerbaik] = [$u, $skor];
      }
    }

    return $skorTerbaik >= 0.5 ? $terbaik : null;
  }

  private static function normal(string $s): string
  {
    $s = ' ' . strtolower($s) . ' ';
    $s = strtr($s, self::GANTI);
    $s = preg_replace('/[^a-z0-9]+/', ' ', $s);
    foreach (self::SINGKATAN as $dari => $ke) {
      $s = preg_replace('/\b' . $dari . '\b/', $ke, $s);
    }

    return trim(preg_replace('/\s+/', ' ', $s));
  }

  /** @return string[] */
  private static function kata(string $s): array
  {
    return array_values(array_unique(array_diff(explode(' ', self::normal($s)), self::ABAIKAN, [''])));
  }
}
