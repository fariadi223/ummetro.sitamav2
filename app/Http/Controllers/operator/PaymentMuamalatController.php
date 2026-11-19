<?php

namespace App\Http\Controllers\operator;

use App\Http\Controllers\Controller;
use App\Services\Bank\MuamalatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * Cek tagihan & pembayaran Bank Muamalat lewat webservice H2H
 * (port SITAMA v1 /inquirybmi: inqury, payment, reversal).
 */
class PaymentMuamalatController extends Controller
{
  public function __construct(private MuamalatService $bank)
  {
  }

  public function index(): View
  {
    return view('content.operator.payment-bank.muamalat', [
      'host' => $this->bank->host(),
      'kodeErr' => MuamalatService::ERR,
    ]);
  }

  public function inquiry(Request $request): JsonResponse
  {
    $v = $request->validate(['vano' => ['required', 'digits_between:8,20']]);

    return $this->jalankan(fn () => $this->bank->inquiry($v['vano']));
  }

  public function payment(Request $request): JsonResponse
  {
    $v = $request->validate([
      'vano' => ['required', 'digits_between:8,20'],
      'bill' => ['required', 'digits_between:1,20'],
      'name' => ['required', 'string', 'max:100'],
    ]);

    return $this->jalankan(fn () => $this->bank->payment($v['vano'], $v['bill'], $v['name']));
  }

  public function reversal(Request $request): JsonResponse
  {
    $v = $request->validate([
      'vano' => ['required', 'digits_between:8,20'],
      'bill' => ['required', 'digits_between:1,20'],
      'name' => ['required', 'string', 'max:100'],
      'pymtdate' => ['required', 'digits:14'],
    ]);

    return $this->jalankan(fn () => $this->bank->reversal($v['vano'], $v['bill'], $v['name'], $v['pymtdate']));
  }

  private function jalankan(callable $aksi): JsonResponse
  {
    try {
      $data = $aksi();
    } catch (RuntimeException $e) {
      return response()->json(['message' => $e->getMessage()], 502);
    }

    $err = (string) ($data['ERR'] ?? '');
    $data['sukses'] = $err === MuamalatService::ERR_SUKSES;
    $data['pesan'] = MuamalatService::ERR[$err] ?? ('Kode ERR ' . ($err ?: '-'));

    return response()->json($data);
  }
}
