<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Port v1 Ekpordata::rincian_kip_saldo — mutasi saldo satu mahasiswa. */
class SaldoUktRincianExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnFormatting, ShouldAutoSize
{
  private int $no = 0;

  /** @param array{mahasiswa: object, mutasi: Collection, saldo: int, digunakan: int, sisa: int} $r */
  public function __construct(private array $r)
  {
  }

  public function collection(): Collection
  {
    return $this->r['mutasi'];
  }

  public function headings(): array
  {
    $m = $this->r['mahasiswa'];

    return [
      ['UNIVERSITAS MUHAMMADIYAH METRO'],
      ['LAPORAN RINCIAN SALDO MAHASISWA'],
      [],
      ['NPM', trim((string) $m->nipd)],
      ['NAMA', trim((string) $m->nm_pd)],
      ['PROGRAM STUDI', trim((string) $m->jenjang_didik) . ' ' . trim((string) $m->nm_lemb)],
      ['SISA SALDO', $this->r['sisa']],
      [],
      ['NO', 'TAHUN AKADEMIK', 'SEMESTER MHS', 'URAIAN', 'DEBET', 'KREDIT', 'SALDO'],
    ];
  }

  public function map($r): array
  {
    return [++$this->no, $r->semester, $r->sem, $r->uraian, $r->debet, $r->kredit, $r->saldo];
  }

  public function columnFormats(): array
  {
    return ['E' => '#,##0', 'F' => '#,##0', 'G' => '#,##0'];
  }

  public function styles(Worksheet $sheet): array
  {
    $sheet->mergeCells('A1:G1');
    $sheet->mergeCells('A2:G2');
    $sheet->getStyle('B4')->getNumberFormat()->setFormatCode('@');
    $sheet->getStyle('B7')->getNumberFormat()->setFormatCode('#,##0');
    $sheet->getStyle('B4:B7')->getAlignment()->setHorizontal('left');

    return [
      1 => ['font' => ['bold' => true, 'size' => 12]],
      2 => ['font' => ['size' => 10]],
      'A4:A7' => ['font' => ['bold' => true]],
      9 => ['font' => ['bold' => true]],
    ];
  }
}
