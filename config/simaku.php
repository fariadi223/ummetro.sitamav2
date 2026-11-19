<?php

/*
|--------------------------------------------------------------------------
| Koneksi ke endpoint SIMAKU (project endpoint.ummetro.simaku/endpoint)
|--------------------------------------------------------------------------
|
| Dipakai App\Services\SimakuService untuk mengambil rekening (COA) level 6
| sebagai mata anggaran biaya. Endpoint memvalidasi Bearer JWT HS256 yang
| ditandatangani dengan JWT_SECRET milik SIMAKU, jadi SIMAKU_JWT_SECRET di
| sini harus sama dengan nilai itu.
|
*/

return [
  // Basis URL, termasuk awalan /endpoint/.
  'url' => env('SIMAKU_URL', 'https://service-simaku.ummetro.dev/endpoint/'),

  'jwt_secret' => env('SIMAKU_JWT_SECRET'),

  // Opsional: dikirim sebagai header X-API-KEY bila diisi.
  'api_key' => env('SIMAKU_API_KEY'),

  'timeout' => (int) env('SIMAKU_TIMEOUT', 30),
  'verify' => (bool) env('SIMAKU_VERIFY_SSL', true),

  // Lama (menit) daftar rekening disimpan di cache.
  'cache_menit' => (int) env('SIMAKU_CACHE_MENIT', 60),
];
