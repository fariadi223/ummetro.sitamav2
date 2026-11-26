<?php
use App\Http\Controllers\admin\DashboardController;
use App\Http\Controllers\operator\BiayaController;
use App\Http\Controllers\operator\DashboardController as OperatorDashboardController;
use App\Http\Controllers\operator\LaporanDispensasiController;
use App\Http\Controllers\operator\LaporanSppController;
use App\Http\Controllers\operator\LaporanTotalTagihanController;
use App\Http\Controllers\operator\MatakuliahPraktikController;
use App\Http\Controllers\operator\PaymentMuamalatController;
use App\Http\Controllers\operator\PenerimaanYudisiumController;
use App\Http\Controllers\operator\SaldoUktController;
use App\Http\Controllers\operator\TagihanController;
use App\Http\Controllers\operator\TagihanPmbController;
use App\Http\Controllers\authentications\AuthController;
use Illuminate\Support\Facades\Route;


/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Sitama Universitas Muhammadiyah Metro.
|
*/

Route::redirect('/', '/login');

Route::middleware('guest')->group(function () {
  Route::get('login', [AuthController::class, 'showLogin'])->name('login');
  Route::post('login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1')
    ->name('login.attempt');

  Route::get('operator/login', [AuthController::class, 'showOperatorLogin'])->name('operator.login');
  Route::post('operator/login', [AuthController::class, 'operatorLogin'])
    ->middleware('throttle:5,1')
    ->name('operator.login.attempt');
});

Route::post('logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'role:admin'])
  ->prefix('admin')
  ->name('admin.')
  ->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
  });

Route::middleware(['auth', 'role:operator'])
  ->prefix('operator')
  ->name('operator.')
  ->group(function () {
    Route::get('dashboard', [OperatorDashboardController::class, 'index'])->name('dashboard');
    Route::get('biaya', [BiayaController::class, 'index'])->name('biaya.index');
    Route::post('biaya', [BiayaController::class, 'store'])->name('biaya.store');
    Route::put('biaya/{id}', [BiayaController::class, 'update'])->whereUuid('id')->name('biaya.update');
    Route::delete('biaya/{id}', [BiayaController::class, 'destroy'])->whereUuid('id')->name('biaya.destroy');
    Route::get('biaya/matakuliah-praktik', [MatakuliahPraktikController::class, 'index'])->name('matakuliah-praktik.index');
    Route::patch('biaya/matakuliah-praktik/{idMk}', [MatakuliahPraktikController::class, 'update'])->whereUuid('idMk')->name('matakuliah-praktik.update');

    // Pembuatan struk tagihan (port v1 /admin/tagihan).
    Route::prefix('tagihan')->name('tagihan.')->controller(TagihanController::class)->group(function () {
      Route::get('/', 'index')->name('index');
      Route::get('mahasiswa', 'mahasiswa')->name('mahasiswa');
      Route::post('proses', 'proses')->name('proses');
      Route::get('biaya', 'biaya')->name('biaya');
      Route::put('biaya/{id}', 'ubahNominalBiaya')->whereUuid('id')->name('biaya.update');
      Route::get('mahasiswa/{idReg}', 'detail')->whereUuid('idReg')->name('detail');
      Route::get('mahasiswa/{idReg}/kpp', 'kpp')->whereUuid('idReg')->name('kpp');
      Route::delete('struk/{id}', 'hapusStruk')->whereUuid('id')->name('struk.destroy');
      Route::post('rincian', 'tambahRincian')->name('rincian.store');
      Route::put('rincian/{id}', 'ubahRincian')->whereNumber('id')->name('rincian.update');
      Route::delete('rincian/{id}', 'hapusRincian')->whereNumber('id')->name('rincian.destroy');
      Route::post('dispensasi', 'pakaiDispensasi')->name('dispensasi.pakai');
      Route::delete('dispensasi', 'hapusDispensasi')->name('dispensasi.destroy');
      Route::post('prasarat', 'tambahPrasarat')->name('prasarat.store');
      Route::put('prasarat/{idByr}', 'ubahPrasarat')->whereUuid('idByr')->name('prasarat.update');
    });

    // Pembuatan struk registrasi ulang Penmaru (port v1 /admin/tagihan_pmb).
    // Nomor tes mengandung "/", jadi dikirim lewat body/query, bukan segmen URL.
    Route::prefix('tagihan-pmb')->name('tagihan-pmb.')->controller(TagihanPmbController::class)->group(function () {
      Route::get('/', 'index')->name('index');
      Route::get('export', 'export')->name('export');
      Route::get('peserta', 'peserta')->name('peserta');
      Route::get('biaya', 'biaya')->name('biaya');
      Route::post('/', 'store')->name('store');
      Route::post('tidak-lulus', 'tidakLulus')->name('tidak-lulus');
      Route::put('/', 'update')->name('update');
      Route::delete('/', 'destroy')->name('destroy');
      Route::post('tutup', 'tutup')->name('tutup');
    });

    // Penerimaan struk yudisium (port v1 /admin/penerimaan_yudisium).
    Route::prefix('penerimaan-yudisium')->name('penerimaan-yudisium.')->controller(PenerimaanYudisiumController::class)->group(function () {
      Route::get('/', 'index')->name('index');
      Route::get('mahasiswa', 'mahasiswa')->name('mahasiswa');
      Route::post('/', 'store')->name('store');
    });

    // Laporan rincian tagihan SPP (port v1 /report/rinci_tagihan).
    Route::prefix('laporan/spp')->name('laporan.spp.')->controller(LaporanSppController::class)->group(function () {
      Route::get('/', 'index')->name('index');
      Route::get('export', 'export')->name('export');
      Route::get('kpp/{kppDtl}', 'detail')->whereNumber('kppDtl')->name('detail');
      Route::put('kpp/{kppDtl}/unit', 'sinkronUnit')->whereNumber('kppDtl')->name('sinkron-unit');
      Route::get('sinkron-jurnal', 'rencanaSinkronJurnal')->name('sinkron-jurnal.rencana');
      Route::post('sinkron-jurnal', 'sinkronJurnal')->name('sinkron-jurnal');
      Route::get('sinkron-jurnal/log', 'logSinkronJurnal')->name('sinkron-jurnal.log');
      Route::get('sinkron-jurnal/log/{log}', 'detailLogSinkronJurnal')->whereNumber('log')->name('sinkron-jurnal.log.detail');
    });

    // Laporan total tagihan per mahasiswa (port v1 /report/total_tagihan).
    Route::prefix('laporan/total-tagihan')->name('laporan.total-tagihan.')->controller(LaporanTotalTagihanController::class)->group(function () {
      Route::get('/', 'index')->name('index');
      Route::get('export', 'export')->name('export');
      Route::get('mahasiswa/{idReg}', 'detail')->whereUuid('idReg')->name('detail');
    });

    // Payment Bank: cek tagihan & pembayaran H2H Bank Muamalat (port v1 /inquirybmi).
    Route::prefix('payment-bank/muamalat')->name('payment-bank.muamalat.')->controller(PaymentMuamalatController::class)->group(function () {
      Route::get('/', 'index')->name('index');
      Route::post('inquiry', 'inquiry')->name('inquiry');
      Route::post('payment', 'payment')->name('payment');
      Route::post('reversal', 'reversal')->name('reversal');
    });

    // Laporan rincian dispensasi (port v1 /report/rinci_dispensasi).
    Route::prefix('laporan/dispensasi')->name('laporan.dispensasi.')->controller(LaporanDispensasiController::class)->group(function () {
      Route::get('/', 'index')->name('index');
      Route::get('export', 'export')->name('export');
    });
  });

// Saldo UKT untuk operator & pustik; URL & nama route tetap di area operator.
Route::middleware(['auth', 'role:operator,pustik'])
  ->prefix('operator')
  ->name('operator.')
  ->group(function () {
    // Saldo UKT / beasiswa mahasiswa (port v1 /admin/saldoukt).
    Route::prefix('saldo-ukt')->name('saldo-ukt.')->controller(SaldoUktController::class)->group(function () {
      Route::get('/', 'index')->name('index');
      Route::get('export', 'export')->name('export');
      Route::get('mahasiswa', 'mahasiswa')->name('mahasiswa');
      Route::get('mahasiswa/{idReg}', 'detail')->whereUuid('idReg')->name('detail');
      Route::get('mahasiswa/{idReg}/export', 'exportRincian')->whereUuid('idReg')->name('detail.export');
      Route::post('/', 'store')->name('store');
      Route::delete('{id}', 'destroy')->whereNumber('id')->name('destroy');
    });
  });
