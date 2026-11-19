<?php

/*
|--------------------------------------------------------------------------
| Koneksi ke Neo Feeder (web service PDDikti)
|--------------------------------------------------------------------------
|
| Dipakai App\Services\NeofeederService untuk mengambil token lalu menarik
| data mahasiswa lulus.
|
| Alur web service-nya dua langkah: act=GetToken menukar username/password
| dengan JWT berumur pendek, lalu token itu disertakan pada setiap act
| pengambilan data. Badan permintaan dikirim sebagai JSON mentah.
|
*/

return [
  'url' => env('NEOFEEDER_URL', 'https://neofeeder.ummetro.ac.id/ws/live2.php'),

  'username' => env('NEOFEEDER_USERNAME'),
  'password' => env('NEOFEEDER_PASSWORD'),

  // Nama act pengambilan data lulusan. Dibuat bisa diatur karena nama act
  // di luar daftar baku Neo Feeder bisa berbeda antar pemasangan.
  'act_lulus' => env('NEOFEEDER_ACT_LULUS', 'ExportDataMahasiswaLulus'),

  'content_type' => env('NEOFEEDER_CONTENT_TYPE', 'application/json'),
  'timeout' => (int) env('NEOFEEDER_TIMEOUT', 180),

  // Verifikasi sertifikat dibiarkan aktif: badan permintaannya memuat
  // username/password Neo Feeder, jadi sambungan yang tidak diverifikasi
  // membuat kredensial itu bisa disadap. Kalau server memakai sertifikat
  // yang tidak cocok dengan nama hostnya, perbaiki sertifikatnya; mematikan
  // NEOFEEDER_VERIFY_SSL hanya pantas sebagai pengukur sementara saat
  // menelusuri masalah, bukan untuk dipakai menetap.
  'verify' => (bool) env('NEOFEEDER_VERIFY_SSL', true),

  // Banyak baris per permintaan saat menarik seluruh data (mode --semua).
  'per_halaman' => (int) env('NEOFEEDER_PER_HALAMAN', 500),

  /*
  |--------------------------------------------------------------------------
  | Peta program studi
  |--------------------------------------------------------------------------
  |
  | Kunci = kode program studi di tabel `prodi` milik aplikasi ini.
  | Nilai  = id_prodi (UUID) yang dipakai Neo Feeder untuk prodi yang sama.
  |
  | Dipakai dua arah oleh neofeeder:import-lulus: menyusun filter permintaan
  | dari --prodi, dan menentukan kode_prg tiap baris balasan. Baris yang
  | id_prodi-nya tidak ada di peta ini dilewati, bukan ditebak -- kode_prg
  | yang salah akan membuat alumni tidak terjaring filter prodi di halaman
  | admin maupun di laporan tracer study.
  |
  | Tambahkan prodi lain di sini. UUID-nya bisa dilihat dari kolom id_prodi
  | pada hasil neofeeder:export-lulus tanpa filter.
  |
  */

  'prodi' => [
    '86904' => 'd075b139-91f6-4a2e-b3db-24cc748731ca', // Pendidikan Profesi Guru
  ],

  // Tempat berkas JSON hasil ditulis, relatif terhadap storage/app.
  // Bukan storage/app/public: isinya data pribadi (nama, NIK, tanggal lahir)
  // dan tidak boleh bisa diunduh langsung dari web.
  'folder_hasil' => env('NEOFEEDER_FOLDER_HASIL', 'neofeeder'),
];
