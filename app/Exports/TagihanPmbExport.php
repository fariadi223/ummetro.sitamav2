<?php

namespace App\Exports;

use App\Repositories\Tagihan\TagihanPmbRepository;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Port v1 Ekpordata::tagihan_camaba (Ekpor_slip_tagihan_penmaru.xlsx). */
class TagihanPmbExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnFormatting, ShouldAutoSize
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
      ['DAFTAR SLIP TAGIHAN REGISTRASI ULANG CALON MAHASISWA BARU'],
      [],
      ['NO', 'NO. PESERTA', 'NAMA LENGKAP', 'TMPT, TGL LAHIR', 'NO. TELP', 'PROGRAM STUDI', 'NPM', 'TAGIHAN (Rp.)', 'KET.'],
    ];
  }

  public function map($r): array
  {
    return [
      ++$this->no,
      $r->no_tes,
      trim((string) $r->nama_lengkap),
      trim((string) $r->tmpt_lahir) . ', ' . $r->tgl_lahir,
      $r->telepon_seluler,
      trim($r->jenjang_didik) . ' ' . trim($r->nm_lemb),
      $r->nipd_register,
      (int) $r->registrasi_uang,
      TagihanPmbRepository::STATUS[(string) $r->lunas] ?? '-',
    ];
  }

  public function columnFormats(): array
  {
    return ['H' => '#,##0', 'E' => NumberFormat::FORMAT_TEXT];
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
