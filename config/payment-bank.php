<?php

/*
|--------------------------------------------------------------------------
| Webservice H2H bank (menu Payment Bank)
|--------------------------------------------------------------------------
|
| Dipakai halaman uji INQUIRY / PAYMENT / REVERSAL (port SITAMA v1
| /inquirybmi). Request & balasan berupa JWT HS256 yang ditandatangani
| dengan key yang sama dengan JWT_SECRET di endpoint.ummetro.h2hbmi.
|
*/

return [
  'muamalat' => [
    'url' => env('API_URL_BMI'),
    'key' => env('API_KEY_BMI'),
    'timeout' => (int) env('API_TIMEOUT_BMI', 30),
  ],
];
