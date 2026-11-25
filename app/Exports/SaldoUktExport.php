<?php

namespace App\Exports;

use App\Repositories\Saldo\SaldoUktRepository;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Port v1 Ekpordata::saldo_spp — saldo UKT & beasiswa per mahasiswa. */
class SaldoUktExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnFormatting, ShouldAutoSize
{
  private int $no = 0;

  public function __construct(private Collection $rows)
  {
  }

  public function collection(): Collection
  {
    return $this->rows;
  }

  public function headings(): array
  {
    return [
      ['UNIVERSITAS MUHAMMADIYAH METRO'],
      ['LAPORAN SALDO UKT & BEASISWA MAHASISWA · per ' . date('d-m-Y')],
      [],
      ['NO', 'NAMA PRODI', 'ANGKATAN', 'NPM', 'NAMA MAHASISWA', 'JENIS', 'JUMLAH SALDO', 'SUDAH DIGUNAKAN', 'SISA SALDO'],
    ];
  }

  public function map($r): array
  {
    $jenis = collect(explode(',', (string) $r->jenis))
      ->filter(fn ($j) => $j !== '')
      ->map(fn ($j) => SaldoUktRepository::JENIS[(int) $j] ?? $j)
      ->implode(', ');

    return [
      ++$this->no,
      trim($r->jenjang_didik) . ' ' . trim($r->nm_lemb),
      substr(trim((string) $r->mulai_smt), 0, 4),
      trim($r->nipd),
      trim((string) $r->nm_pd),
      $jenis,
      (int) $r->saldo,
      (int) $r->digunakan,
      (int) $r->sisa,
    ];
  }

  public function columnFormats(): array
  {
    return ['D' => NumberFormat::FORMAT_TEXT, 'G' => '#,##0', 'H' => '#,##0', 'I' => '#,##0'];
  }

  public function styles(Worksheet $sheet): array
  {
    $sheet->mergeCells('A1:I1');
    $sheet->mergeCells('A2:I2');

    return [
      1 => ['font' => ['bold' => true, 'size' => 12]],
      2 => ['font' => ['size' => 10]],
      4 => ['font' => ['bold' => true]],
    ];
  }
}
