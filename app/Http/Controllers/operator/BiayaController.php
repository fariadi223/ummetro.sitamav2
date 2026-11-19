<?php

namespace App\Http\Controllers\operator;

use App\Http\Controllers\Controller;
use App\Repositories\Mak\BiayaRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BiayaController extends Controller
{
  public function __construct(private BiayaRepository $biaya)
  {
  }

  public function index(Request $request): View
  {
    $request->validate([
      'search' => ['nullable', 'string', 'max:100'],
      'sms' => ['nullable', 'uuid'],
      'angkatan' => ['nullable', 'digits:4'],
      'ma' => ['nullable', 'string', 'max:20'],
      'sumber_id' => ['nullable', 'integer'],
      'limit' => ['nullable', 'integer', 'in:10,25,50,100'],
    ]);

    $filter = $request->only(['search', 'sms', 'angkatan', 'ma', 'sumber_id', 'limit']);

    $data = $this->biaya->getAll($filter);
    $this->biaya->tandaiDipakai($data->items());

    // Rekening untuk modal tambah (SIMAKU, atau data lokal bila gagal).
    [$rekening, $rekeningGalat] = $this->biaya->rekeningBiaya();

    return view('content.operator.biaya.index', [
      'data' => $data,
      'filter' => $filter,
      'prodi' => $this->biaya->pilihanProdi(),
      'angkatan' => $this->biaya->pilihanAngkatan(),
      'mataAnggaran' => $this->biaya->pilihanMataAnggaran(),
      'sumberDana' => $this->biaya->pilihanSumberDana(),
      'rekening' => $rekening,
      'rekeningGalat' => $rekeningGalat,
    ]);
  }

  public function store(Request $request): RedirectResponse
  {
    [$rekening] = $this->biaya->rekeningBiaya();
    $data = $this->validasi($request, 'tambah', $rekening, multiProdi: true);
    $prodiDipilih = array_values(array_unique($data['sms']));

    // Prodi yang sudah punya biaya ini (MA + angkatan + kelas sama) dilewati.
    $baru = array_values(array_filter($prodiDipilih, fn ($sms) => !$this->biaya->sudahAda(['sms' => $sms] + $data)));
    $dilewati = count($prodiDipilih) - count($baru);

    if (!$baru) {
      return back()
        ->withInput()
        ->withErrors(['ma' => 'Biaya ini sudah ada untuk semua prodi terpilih pada angkatan dan kelas yang sama.'], 'tambah');
    }

    $rek = $rekening->firstWhere('ma', (string) $data['ma']);
    $hasil = DB::transaction(fn () => array_map(
      fn ($sms) => $this->biaya->create(['sms' => $sms] + $data, $rek),
      $baru
    ));

    $pesan = 'Biaya "' . $hasil[0]->nama . '" berhasil ditambahkan untuk ' . count($hasil) . ' prodi.';
    if ($dilewati) {
      $pesan .= " {$dilewati} prodi dilewati karena biaya yang sama sudah ada.";
    }

    // Tampilkan daftar yang memuat data baru.
    return redirect()
      ->route('operator.biaya.index', array_filter([
        'sms' => count($hasil) === 1 ? $hasil[0]->sms : null,
        'angkatan' => $hasil[0]->angkatan,
      ]))
      ->with('sukses', $pesan);
  }

  public function update(Request $request, string $id): RedirectResponse
  {
    $biaya = $this->biaya->getByID($id) ?? abort(404);
    $dipakai = $this->biaya->dipakai($biaya);

    // Biaya yang sudah dipakai tagihan: rekening, prodi, dan angkatan dikunci.
    if ($dipakai) {
      $request->merge([
        'ma' => (string) (int) $biaya->ma,
        'sms' => $biaya->sms,
        'angkatan' => (string) (int) $biaya->angkatan,
      ]);
    }

    [$rekening] = $this->biaya->rekeningBiaya();
    $data = $this->validasi($request, 'ubah', $rekening, (string) (int) $biaya->ma);

    if ($this->biaya->sudahAda($data, $biaya->id)) {
      return back()
        ->withInput()
        ->withErrors(['ma' => 'Biaya ini sudah ada untuk prodi, angkatan, dan kelas yang sama.'], 'ubah');
    }

    $this->biaya->update($biaya, $data, $rekening->firstWhere('ma', (string) $data['ma']));

    return back()->with('sukses', 'Biaya "' . trim($biaya->nama) . '" berhasil diubah.');
  }

  public function destroy(string $id): RedirectResponse
  {
    $biaya = $this->biaya->getByID($id) ?? abort(404);

    if ($this->biaya->dipakai($biaya)) {
      return back()->with('gagal', 'Biaya "' . trim($biaya->nama) . '" tidak dapat dihapus karena sudah dipakai pada tagihan mahasiswa.');
    }

    $this->biaya->delete($biaya);

    return back()->with('sukses', 'Biaya "' . trim($biaya->nama) . '" berhasil dihapus.');
  }

  /**
   * Validasi form tambah/ubah. $maSekarang (form ubah) tetap diterima
   * walau tidak ada di daftar rekening SIMAKU.
   */
  private function validasi(Request $request, string $bag, $rekening, ?string $maSekarang = null, bool $multiProdi = false): array
  {
    $sumberDana = $this->biaya->pilihanSumberDana();
    $maValid = $rekening->pluck('ma')->map(fn ($v) => (string) $v)->push($maSekarang)->filter()->all();

    $aturanProdi = $multiProdi
      ? ['sms' => ['required', 'array', 'min:1'], 'sms.*' => ['required', 'uuid', Rule::exists('tbl_sms', 'id_sms')]]
      : ['sms' => ['required', 'uuid', Rule::exists('tbl_sms', 'id_sms')]];

    return $request->validateWithBag($bag, $aturanProdi + [
      'ma' => ['required', Rule::in($maValid)],
      'angkatan' => ['required', 'digits:4'],
      'kelas' => ['required', Rule::in(array_map('strval', array_keys(BiayaRepository::KELAS)))],
      'uang' => ['required', 'integer', 'min:0', 'max:999999999999'],
      'sumber_id' => ['required', Rule::in($sumberDana->pluck('sumber_id')->map(fn ($v) => (string) $v)->all())],
      'prasarat' => ['nullable', Rule::in(['0', ...array_map('strval', array_keys(BiayaRepository::PRASARAT))])],
      'kredit' => ['nullable', 'in:1'],
      'praktik' => ['nullable', 'in:1'],
      'pilihmhs' => ['nullable', 'in:1'],
    ], [
      'required' => ':attribute wajib diisi.',
      'in' => ':attribute tidak valid.',
      'exists' => ':attribute tidak ditemukan.',
      'uuid' => ':attribute tidak valid.',
      'digits' => ':attribute harus :digits digit.',
      'integer' => ':attribute harus berupa angka.',
      'min' => ':attribute minimal :min.',
      'array' => ':attribute tidak valid.',
      'max' => ':attribute terlalu besar.',
    ], [
      'ma' => 'Rekening biaya',
      'sms' => 'Program studi',
      'sms.*' => 'Program studi',
      'angkatan' => 'Angkatan',
      'kelas' => 'Kelas',
      'uang' => 'Nominal',
      'sumber_id' => 'Sumber dana',
      'prasarat' => 'Prasarat',
      'kredit' => 'Hitung per SKS',
      'praktik' => 'Hitung per Praktikum',
      'pilihmhs' => 'Dapat diinput mahasiswa',
    ]);
  }
}
