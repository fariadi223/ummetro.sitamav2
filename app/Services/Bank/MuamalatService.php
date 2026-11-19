<?php

namespace App\Services\Bank;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Klien webservice H2H Bank Muamalat (endpoint.ummetro.h2hbmi).
 *
 * Port dari SITAMA v1 controllers/Inquirybmi.php. Badan request adalah JWT
 * HS256 (bukan JSON) dan balasannya juga JWT HS256 dengan key yang sama.
 * Kode ERR (lihat self::ERR) mengikuti endpoint.ummetro.h2hbmi; "00" = sukses.
 * Nilai BILL dari bank sudah termasuk 2 digit sen (mis. 51500000 = Rp 515.000).
 */
class MuamalatService
{
  public const ERR_SUKSES = '00';

  public const ERR = [
    '00' => 'Sukses',
    '12' => 'Transaksi tidak valid',
    '15' => 'Nomor VA / tagihan tidak ditemukan',
    '88' => 'Tagihan sudah dibayar',
    '99' => 'Transaksi mencurigakan (suspect)',
  ];

  public function host(): ?string
  {
    return config('payment-bank.muamalat.url');
  }

  public function inquiry(string $vano): array
  {
    return $this->kirim([
      'CCY' => '360',
      'VANO' => $vano,
      'TRXDATE' => date('YmdHis'),
      'METHOD' => 'INQUIRY',
      'USERNAME' => '',
      'PASSWORD' => '',
      'CHANNELID' => '1',
      'REFNO' => $this->refno(),
    ]);
  }

  /** Pelunasan: jumlah dibayar = jumlah tagihan hasil inquiry (BILL apa adanya). */
  public function payment(string $vano, string $bill, string $nama): array
  {
    $trxdate = date('YmdHis');

    return $this->kirim([
      'CCY' => '360',
      'VANO' => $vano,
      'TRXDATE' => $trxdate,
      'METHOD' => 'PAYMENT',
      'USERNAME' => '',
      'PASSWORD' => '',
      'CHANNELID' => '1',
      'BILL' => $bill,
      'PAYMENT' => $bill,
      'CUSTNAME' => $nama,
      'REFNO' => $this->refno(),
    ]) + ['TRXDATE' => $trxdate];
  }

  /** Pembatalan pembayaran; $pymtdate = TRXDATE saat payment (YmdHis). */
  public function reversal(string $vano, string $bill, string $nama, string $pymtdate): array
  {
    return $this->kirim([
      'CCY' => '360',
      'VANO' => $vano,
      'TRXDATE' => date('YmdHis'),
      'METHOD' => 'REVERSAL',
      'USERNAME' => '',
      'PASSWORD' => '',
      'CHANNELID' => '1',
      'BILL' => $bill,
      'PAYMENT' => $bill,
      'CUSTNAME' => $nama,
      'PYMTDATE' => $pymtdate,
      'REFNO' => date('YmdHis'),
    ]);
  }

  private function kirim(array $payload): array
  {
    $url = $this->host();
    $key = (string) config('payment-bank.muamalat.key');

    if (!$url || $key === '') {
      throw new RuntimeException('API_URL_BMI / API_KEY_BMI belum diatur di .env.');
    }

    $token = JWT::encode($payload, $key, 'HS256');

    try {
      $balasan = Http::timeout(config('payment-bank.muamalat.timeout', 30))
        ->withBody($token, 'application/x-www-form-urlencoded')
        ->post($url);
    } catch (ConnectionException $e) {
      throw new RuntimeException('Webservice Muamalat tidak dapat dihubungi: ' . $e->getMessage());
    }

    if ($balasan->failed()) {
      throw new RuntimeException('Webservice Muamalat membalas HTTP ' . $balasan->status() . '.');
    }

    try {
      $data = (array) JWT::decode(trim($balasan->body()), new Key($key, 'HS256'));
    } catch (Throwable $e) {
      throw new RuntimeException('Balasan webservice tidak valid: ' . $e->getMessage());
    }

    // Jejak transaksi uji (PAYMENT/REVERSAL mengubah status tagihan sungguhan).
    Log::info('H2H Muamalat ' . $payload['METHOD'], [
      'vano' => $payload['VANO'],
      'refno' => $payload['REFNO'],
      'bill' => $payload['BILL'] ?? null,
      'err' => $data['ERR'] ?? null,
      'user' => auth()->user()?->username,
    ]);

    return $data + ['request' => $token];
  }

  private function refno(): string
  {
    return time() . random_int(10, 100);
  }
}
