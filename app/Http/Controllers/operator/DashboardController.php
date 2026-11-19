<?php

namespace App\Http\Controllers\operator;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
  public function index(Request $request): View
  {
    // Modul yang dibuka endpoint SITAMA untuk role adminpenerimaan.
    // Modul tanpa 'route' belum punya halaman dan tampil sebagai pintasan nonaktif.
    $modul = [
      ['ikon' => 'ti-receipt-2', 'judul' => 'Data Biaya', 'keterangan' => 'Mata anggaran biaya per prodi dan angkatan.', 'warna' => 'primary', 'route' => 'operator.biaya.index'],
      ['ikon' => 'ti-wallet', 'judul' => 'Saldo SPP', 'keterangan' => 'Saldo SPP mahasiswa dan referensinya.', 'warna' => 'success'],
      ['ikon' => 'ti-cash', 'judul' => 'Top Up Saldo', 'keterangan' => 'Riwayat dan input top up saldo SPP.', 'warna' => 'info'],
      ['ikon' => 'ti-brand-whatsapp', 'judul' => 'Pesan WhatsApp', 'keterangan' => 'Kirim tagihan dan broadcast ke mahasiswa.', 'warna' => 'warning'],
    ];

    return view('content.dashboard.operator', [
      'user' => $request->user(),
      'modul' => $modul,
    ]);
  }
}
