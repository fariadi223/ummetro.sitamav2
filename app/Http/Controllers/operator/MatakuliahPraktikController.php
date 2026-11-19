<?php

namespace App\Http\Controllers\operator;

use App\Http\Controllers\Controller;
use App\Repositories\Akademik\MatakuliahPraktikRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MatakuliahPraktikController extends Controller
{
  public function __construct(private MatakuliahPraktikRepository $mk)
  {
  }

  public function index(Request $request): View
  {
    $request->validate([
      'id_smt' => ['nullable', 'regex:/^\d{4}[12]$/'],
      'sms' => ['nullable', 'uuid'],
      'angkatan' => ['nullable', 'digits:4'],
      'status' => ['nullable', 'in:praktik,bukan'],
      'search' => ['nullable', 'string', 'max:100'],
      'limit' => ['nullable', 'integer', 'in:10,25,50,100'],
    ]);

    $filter = $request->only(['id_smt', 'sms', 'angkatan', 'status', 'search', 'limit']);
    $filter['id_smt'] = $filter['id_smt'] ?? MatakuliahPraktikRepository::semesterAktif();

    $prodi = $this->mk->pilihanProdi();

    return view('content.operator.matakuliah-praktik.index', [
      'data' => $this->mk->getAll($filter),
      'filter' => $filter,
      'semester' => $this->mk->pilihanSemester(),
      'angkatan' => $this->mk->pilihanAngkatan(),
      'prodi' => $prodi,
      'prodiByID' => $prodi->keyBy('id_sms'),
    ]);
  }

  public function update(Request $request, string $idMk): RedirectResponse
  {
    $data = $request->validate([
      'id_smt' => ['required', 'regex:/^\d{4}[12]$/'],
      'praktik' => ['required', 'in:0,1'],
    ]);

    $nama = $this->mk->namaMatakuliah($idMk) ?? abort(404);
    $jumlah = $this->mk->setPraktik($idMk, $data['id_smt'], $data['praktik'] === '1');

    return back()->with('sukses', $data['praktik'] === '1'
      ? "\"{$nama}\" ditandai sebagai matakuliah praktik ({$jumlah} peserta)."
      : "Tanda praktik \"{$nama}\" dihapus ({$jumlah} peserta).");
  }
}
