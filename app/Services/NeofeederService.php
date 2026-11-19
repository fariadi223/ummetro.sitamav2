<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Pembungkus web service Neo Feeder (ws/live2.php).
 *
 * Dua langkah: act=GetToken menukar username/password dengan JWT berumur
 * pendek, lalu token itu disertakan pada setiap act pengambilan data.
 *
 * CATATAN. Layanan ini hanya bisa dihubungi dari jaringan yang punya akses ke
 * port 3003 host neofeeder. Dari luar, nama hostnya dilayani Cloudflare yang
 * tidak meneruskan port itu, sehingga permintaan berhenti sebagai timeout --
 * bukan tanda kredensial atau act-nya salah.
 *
 * Bentuk balasan web service ini mengikuti pola Feeder: amplop berisi
 * error_code / error_desc / data. Karena bentuk pastinya belum pernah
 * terlihat dari pemasangan ini, pembacaannya dibuat memaklumi beberapa
 * variasi yang masuk akal (lihat ambilToken dan ambilBaris), dan balasan
 * mentahnya selalu bisa dilihat lewat opsi --raw pada command.
 */
class NeofeederService
{
  /** Badan balasan terakhir, apa adanya. Dipakai command untuk opsi --raw. */
  private ?string $balasanMentah = null;

  /** Token hasil GetToken, disimpan supaya satu proses cukup sekali ambil. */
  private ?string $token = null;

  public function balasanMentah(): ?string
  {
    return $this->balasanMentah;
  }

  public function url(): string
  {
    $url = trim((string) config('neofeeder.url'));

    if ($url === '') {
      throw new RuntimeException('NEOFEEDER_URL belum diisi di .env.');
    }

    return $url;
  }

  /**
   * Tukar username/password dengan token.
   *
   * Hasilnya disimpan di properti: satu kali jalan command bisa memanggil
   * beberapa halaman data, dan tidak ada gunanya mengambil token berulang.
   */
  public function token(bool $paksaBaru = false): string
  {
    if ($this->token !== null && !$paksaBaru) {
      return $this->token;
    }

    $username = (string) config('neofeeder.username');
    $password = (string) config('neofeeder.password');

    if ($username === '' || $password === '') {
      throw new RuntimeException(
        'NEOFEEDER_USERNAME / NEOFEEDER_PASSWORD belum diisi di .env.'
      );
    }

    $balasan = $this->kirim([
      'act' => 'GetToken',
      'username' => $username,
      'password' => $password,
    ]);

    $token = $this->ambilToken($balasan);

    if ($token === null) {
      throw new RuntimeException(
        'Balasan GetToken tidak memuat token. Kunci yang dikirim server: '
        . ($this->daftarKunci($balasan) ?: '(bukan objek)')
        . '. Lihat balasan mentahnya dengan --raw.'
      );
    }

    return $this->token = $token;
  }

  /**
   * Tarik satu halaman data mahasiswa lulus.
   *
   * @return array{baris: array<int, mixed>, pesan: ?string, kunci_amplop: string, kolom: array<int, string>}
   */
  public function mahasiswaLulus(string $filter = '', string $order = '', int $limit = 5, int $offset = 0): array
  {
    $balasan = $this->kirim([
      'act' => (string) config('neofeeder.act_lulus'),
      'token' => $this->token(),
      'filter' => $filter,
      'order' => $order,
      // Dikirim sebagai string, menyamai contoh permintaan yang dipakai
      // operator ("limit":"5"). Beberapa pemasangan Feeder menolak angka.
      'limit' => (string) $limit,
      'offset' => (string) $offset,
    ]);

    $baris = $this->ambilBaris($balasan);

    return [
      'baris' => $baris,
      'pesan' => $this->ambilPesan($balasan),
      'kunci_amplop' => $this->daftarKunci($balasan),
      'kolom' => $this->kolomBaris($baris),
    ];
  }

  /**
   * Tarik seluruh data dengan permintaan berulang.
   *
   * Berhenti ketika server mengirim balasan yang lebih pendek dari ukuran
   * halaman -- penanda baku bahwa datanya sudah habis. $batasHalaman menjaga
   * dari server yang mengabaikan offset dan mengirim halaman yang sama terus.
   *
   * $kabar dipanggil tiap halaman dengan (jumlah baris terkumpul), supaya
   * command bisa menampilkan kemajuannya.
   *
   * @return array{baris: array<int, mixed>, permintaan: int, pesan: ?string, kunci_amplop: string, kolom: array<int, string>, kena_batas: bool}
   */
  public function semuaMahasiswaLulus(
    string $filter = '',
    string $order = '',
    ?int $perHalaman = null,
    int $offset = 0,
    int $batasHalaman = 200,
    ?callable $kabar = null
  ): array {
    $perHalaman = max(1, $perHalaman ?? (int) config('neofeeder.per_halaman'));

    $semua = [];
    $permintaan = 0;
    $pesan = null;
    $kunciAmplop = '';
    $kolom = [];

    while ($permintaan < $batasHalaman) {
      $hasil = $this->mahasiswaLulus($filter, $order, $perHalaman, $offset);
      $permintaan++;

      $pesan = $hasil['pesan'] ?? $pesan;
      $kunciAmplop = $hasil['kunci_amplop'];
      $kolom = $kolom !== [] ? $kolom : $hasil['kolom'];

      $jumlah = count($hasil['baris']);

      foreach ($hasil['baris'] as $b) {
        $semua[] = $b;
      }

      if ($kabar !== null) {
        $kabar(count($semua));
      }

      // Halaman terakhir selalu lebih pendek dari ukuran halaman; halaman
      // kosong berarti datanya habis tepat di batas sebelumnya.
      if ($jumlah < $perHalaman) {
        break;
      }

      $offset += $perHalaman;
    }

    return [
      'baris' => $semua,
      'permintaan' => $permintaan,
      'pesan' => $pesan,
      'kunci_amplop' => $kunciAmplop,
      'kolom' => $kolom,
      'kena_batas' => $permintaan >= $batasHalaman,
      'per_halaman' => $perHalaman,
    ];
  }

  /**
   * Kirim satu permintaan dan kembalikan balasannya sebagai array.
   *
   * @param  array<string, mixed>  $muatan
   * @return mixed
   */
  private function kirim(array $muatan)
  {
    $this->balasanMentah = null;

    try {
      $respons = Http::withBody(json_encode($muatan), (string) config('neofeeder.content_type'))
        ->withOptions(['verify' => config('neofeeder.verify')])
        ->timeout((int) config('neofeeder.timeout'))
        ->post($this->url());
    } catch (ConnectionException $e) {
      // Pesan bawaan Guzzle panjang dan membingungkan operator, jadi
      // sebabnya yang paling sering disebut lebih dulu.
      throw new RuntimeException(
        'Tidak bisa menghubungi Neo Feeder di ' . $this->url() . '. '
        . 'Periksa apakah mesin ini punya akses ke port tersebut '
        . '(dari luar jaringan kampus biasanya tertutup). Pesan asli: '
        . $e->getMessage(),
        0,
        $e
      );
    }

    $this->balasanMentah = $respons->body();

    // Kredensial dan token tidak pernah ikut dicatat: yang berguna untuk
    // penelusuran hanya act dan status balasannya.
    Log::info('Neofeeder: permintaan terkirim.', [
      'url' => $this->url(),
      'act' => $muatan['act'] ?? null,
      'status' => $respons->status(),
      'panjang_balasan' => strlen((string) $this->balasanMentah),
    ]);

    if ($respons->failed()) {
      throw new RuntimeException(
        'Neo Feeder menjawab HTTP ' . $respons->status() . '. Lihat balasan mentahnya dengan --raw.'
      );
    }

    $data = json_decode((string) $this->balasanMentah, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
      throw new RuntimeException(
        'Balasan Neo Feeder bukan JSON yang sah (' . json_last_error_msg() . '). '
        . 'Lihat balasan mentahnya dengan --raw.'
      );
    }

    $this->pastikanTidakGalat($data);

    return $data;
  }

  /**
   * Amplop Feeder menandai kegagalan lewat error_code bukan nol, sedangkan
   * status HTTP-nya tetap 200. Jadi galatnya harus dibaca dari badan balasan.
   */
  private function pastikanTidakGalat($balasan): void
  {
    if (!is_array($balasan)) {
      return;
    }

    foreach (['error_code', 'errorCode', 'err'] as $kunci) {
      if (!array_key_exists($kunci, $balasan)) {
        continue;
      }

      $kode = $balasan[$kunci];

      // '0' dan 0 sama-sama berarti berhasil; selain itu dianggap galat.
      if ($kode === null || (string) $kode === '0' || $kode === false) {
        continue;
      }

      $pesan = $this->ambilPesan($balasan);

      throw new RuntimeException(
        'Neo Feeder menolak permintaan (' . $kunci . '=' . (string) $kode . ')'
        . ($pesan !== null && $pesan !== '' ? ': ' . $pesan : '.')
      );
    }
  }

  /** Cari token di beberapa posisi yang masuk akal. */
  private function ambilToken($balasan): ?string
  {
    if (is_string($balasan)) {
      return trim($balasan) !== '' ? trim($balasan) : null;
    }

    if (!is_array($balasan)) {
      return null;
    }

    // data.token (bentuk baku Feeder), lalu token di akar, lalu data berupa
    // string token langsung.
    $kandidat = [
      $balasan['data']['token'] ?? null,
      $balasan['token'] ?? null,
      is_string($balasan['data'] ?? null) ? $balasan['data'] : null,
      $balasan['data']['Token'] ?? null,
    ];

    foreach ($kandidat as $nilai) {
      if (is_string($nilai) && trim($nilai) !== '') {
        return trim($nilai);
      }
    }

    return null;
  }

  /**
   * Ambil daftar baris dari amplop balasan.
   *
   * @return array<int, mixed>
   */
  private function ambilBaris($balasan): array
  {
    if (!is_array($balasan)) {
      return [];
    }

    // Bentuk baku: data berisi senarai baris.
    if (isset($balasan['data']) && is_array($balasan['data'])) {
      $data = $balasan['data'];

      if (array_is_list($data)) {
        return $data;
      }

      // data berupa objek: cari senarai di dalamnya (mis. data.rows).
      foreach ($data as $isi) {
        if (is_array($isi) && array_is_list($isi)) {
          return $isi;
        }
      }

      // Satu baris tunggal yang tidak dibungkus senarai.
      return [$data];
    }

    // Tanpa amplop: balasan itu sendiri senarai baris.
    if (array_is_list($balasan)) {
      return $balasan;
    }

    return [];
  }

  private function ambilPesan($balasan): ?string
  {
    if (!is_array($balasan)) {
      return null;
    }

    foreach (['error_desc', 'errorDesc', 'message', 'pesan', 'msg'] as $kunci) {
      $nilai = $balasan[$kunci] ?? null;

      if (is_string($nilai) && trim($nilai) !== '') {
        return trim($nilai);
      }
    }

    return null;
  }

  /** Nama kunci di akar balasan, untuk pesan galat yang bisa ditindaklanjuti. */
  private function daftarKunci($balasan): string
  {
    return is_array($balasan) ? implode(', ', array_keys($balasan)) : '';
  }

  /**
   * Nama kolom pada baris pertama, dipakai command untuk menunjukkan field
   * apa saja yang dikirim server tanpa perlu membuka berkas hasilnya.
   *
   * @param  array<int, mixed>  $baris
   * @return array<int, string>
   */
  private function kolomBaris(array $baris): array
  {
    $pertama = $baris[0] ?? null;

    return is_array($pertama) ? array_map('strval', array_keys($pertama)) : [];
  }
}
