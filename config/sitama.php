<?php

/*
|--------------------------------------------------------------------------
| Koneksi ke endpoint SITAMA (service-sitama.ummetro.ac.id)
|--------------------------------------------------------------------------
|
| Dipakai App\Services\SitamaService. Alurnya: POST auth/login dengan
| username/password menghasilkan JWT (data.access_token), lalu token itu
| dikirim sebagai Bearer pada setiap permintaan GET/POST/PUT berikutnya.
|
*/

return [
  // Basis URL API, termasuk awalan /api/. Path pada get/post/put relatif
  // terhadap URL ini, mis. 'akademik/prodi/all'.
  'url' => env('SITAMA_URL', 'https://service-sitama.ummetro.ac.id/api/'),

  'username' => env('SITAMA_USERNAME'),
  'password' => env('SITAMA_PASSWORD'),

  'timeout' => (int) env('SITAMA_TIMEOUT', 30),
  'verify' => (bool) env('SITAMA_VERIFY_SSL', true),

  // Kunci cache tempat token disimpan agar tidak login di setiap permintaan.
  'cache_key' => 'sitama.token',
];
