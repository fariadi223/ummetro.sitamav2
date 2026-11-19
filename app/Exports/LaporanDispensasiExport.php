<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Port v1 Ekpordata::rincian_dispensasi (label kolom dispensasi/tagihan diperbaiki). */
class LaporanDispensasiExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnFormatting, ShouldAutoSize
{
  private int $no = 0;

  public function __construct(private Collection $rows, private string $semester)
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
      ['LAPORAN RINCIAN DISPENSASI · ' . $this->semester],
      [],
      ['NO', 'ANGKATAN', 'NAMA PRODI', 'NPM', 'NAMA MAHASISWA', 'NAMA REKENING', 'BIAYA POKOK', 'DISPENSASI', 'TAGIHAN'],
    ];
  }

  public function map($r): array
  {
    return [
      ++$this->no,
      substr(trim((string) $r->mulai_smt), 0, 4),
      trim($r->jenjang_didik) . ' ' . trim($r->nm_lemb),
      trim($r->nipd),
      trim((string) $r->nm_pd),
      trim((string) $r->uraian),
      (int) $r->uang_pokok,
      (int) $r->uang_potongan,
      (int) $r->tagihan,
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
