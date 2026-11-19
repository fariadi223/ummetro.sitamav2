@extends('layouts/layoutMaster')

@section('title', 'Data Biaya')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset(mix('assets/vendor/libs/select2/select2.css')) }}">
<link rel="stylesheet" href="{{ asset(mix('assets/vendor/libs/sweetalert2/sweetalert2.css')) }}">
@endsection

@section('vendor-script')
<script src="{{ asset(mix('assets/vendor/libs/select2/select2.js')) }}"></script>
<script src="{{ asset(mix('assets/vendor/libs/sweetalert2/sweetalert2.js')) }}"></script>
@endsection

@section('page-script')
<script>
  $(function () {
    // Select2 untuk isian filter. Opsi kosong ("Semua") dipakai sebagai
    // placeholder, dan tombol x mengosongkan filter.
    $('.select2-filter').each(function () {
      var $el = $(this);
      $el.wrap('<div class="position-relative"></div>').select2({
        width: '100%',
        allowClear: true,
        placeholder: $el.find('option[value=""]').text(),
        dropdownParent: $el.parent(),
      });
    });

    // Kirim form otomatis setiap filter berubah (pilih maupun klik x).
    // Pencarian teks tetap dikirim dengan Enter / tombol Terapkan.
    $('.select2-filter').on('change', function () {
      this.form.submit();
    });

    // Select2 di modal tambah: dropdown ditempel ke modal supaya kotak
    // pencariannya bisa diketik.
    $('#modalTambahBiaya, #modalUbahBiaya').each(function () {
      var $m = $(this);
      $m.find('.select2-modal').each(function () {
        var $el = $(this);
        $el.select2({
          width: '100%',
          placeholder: $el.data('placeholder') || $el.find('option[value=""]').text(),
          closeOnSelect: !$el.prop('multiple'),
          dropdownParent: $m,
        });
      });
    });

    // Pilih semua / kosongkan prodi di modal tambah.
    $('[data-sms-semua]').on('click', function () {
      var $s = $('#tambah_sms');
      $s.val($s.find('option').map(function () { return this.value; }).get()).trigger('change');
    });
    $('[data-sms-kosong]').on('click', function () {
      $('#tambah_sms').val(null).trigger('change');
    });

    // Buka ulang modal tambah bila penyimpanan gagal validasi.
    var $modal = $('#modalTambahBiaya');
    if ($modal.data('buka') == 1) {
      bootstrap.Modal.getOrCreateInstance($modal[0]).show();
    }

    // Modal ubah: isi form dari data baris, kunci kolom bila sudah dipakai.
    var $ubah = $('#modalUbahBiaya');
    function isiUbah(b) {
      var $f = $ubah.find('form');
      $f.attr('action', String($ubah.data('action')).replace('__ID__', b.id));
      $f.find('[name=_id]').val(b.id);
      $f.find('[name=_dipakai]').val(b.dipakai ? '1' : '0');
      $f.find('[name=_ma_label]').val(b.ma_label || '');

      // Rekening lama mungkin tidak ada di daftar SIMAKU.
      var $ma = $f.find('[name=ma]');
      if (b.ma && !$ma.find('option').filter(function () { return this.value == b.ma; }).length) {
        $ma.append(new Option(b.ma_label || b.ma, b.ma));
      }

      $.each(['ma', 'sms', 'angkatan', 'kelas', 'uang', 'sumber_id', 'prasarat'], function (_, k) {
        $f.find('[name=' + k + ']').val(b[k] == null ? '' : String(b[k])).trigger('change');
      });

      $.each(['kredit', 'praktik', 'pilihmhs'], function (_, k) {
        $f.find('[name=' + k + ']').prop('checked', !!b[k]);
      });

      $f.find('[data-kunci]').prop('disabled', !!b.dipakai);
      $ubah.find('[data-info-dipakai]').toggleClass('d-none', !b.dipakai).toggleClass('d-flex', !!b.dipakai);
    }

    $(document).on('click', '[data-ubah-biaya]', function () {
      $ubah.find('.is-invalid').removeClass('is-invalid');
      $ubah.find('.invalid-feedback').remove();
      isiUbah($(this).data('ubah-biaya'));
      bootstrap.Modal.getOrCreateInstance($ubah[0]).show();
    });

    if ($ubah.data('buka') == 1) {
      isiUbah($ubah.data('lama'));
      bootstrap.Modal.getOrCreateInstance($ubah[0]).show();
    }

    // Konfirmasi hapus.
    $(document).on('submit', 'form[data-hapus-biaya]', function (e) {
      var form = this;
      if (form.dataset.yakin) return;
      e.preventDefault();
      Swal.fire({
        title: 'Hapus biaya?',
        text: form.dataset.hapusBiaya,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, hapus',
        cancelButtonText: 'Batal',
        customClass: { confirmButton: 'btn btn-danger me-2', cancelButton: 'btn btn-label-secondary' },
        buttonsStyling: false,
      }).then(function (r) {
        if (r.isConfirmed) {
          form.dataset.yakin = '1';
          form.submit();
        }
      });
    });
  });
</script>
@endsection

@section('content')
<div class="pg-header mb-4">
  <div class="pg-header-main">
    <div class="pg-header-icon">
      <i class="ti ti-receipt-2"></i>
    </div>
    <div class="flex-grow-1 min-w-0">
      <div class="pg-header-crumb">
        <a href="{{ route('operator.dashboard') }}">Dashboard</a>
        <i class="ti ti-chevron-right"></i>
        <span>Data Biaya</span>
      </div>
      <h4 class="pg-header-title">Data Biaya</h4>
      <p class="pg-header-sub">Mata anggaran biaya kuliah per program studi dan angkatan.</p>
    </div>
  </div>

  <div class="pg-header-stats">
    <div class="pg-stat">
      <span class="pg-stat-icon"><i class="ti ti-list-details"></i></span>
      <div>
        <strong>{{ number_format($data->total(), 0, ',', '.') }}</strong>
        <small>Data biaya{{ array_filter(\Illuminate\Support\Arr::except($filter, 'limit')) ? ' (terfilter)' : '' }}</small>
      </div>
    </div>
    <div class="pg-stat">
      <span class="pg-stat-icon is-info"><i class="ti ti-school"></i></span>
      <div>
        <strong>{{ number_format($prodi->count(), 0, ',', '.') }}</strong>
        <small>Program studi</small>
      </div>
    </div>
    <div class="pg-stat">
      <span class="pg-stat-icon is-success"><i class="ti ti-calendar"></i></span>
      <div>
        <strong>{{ number_format($angkatan->count(), 0, ',', '.') }}</strong>
        <small>Angkatan</small>
      </div>
    </div>
    <div class="pg-stat">
      <span class="pg-stat-icon is-danger"><i class="ti ti-coins"></i></span>
      <div>
        <strong>{{ number_format($sumberDana->count(), 0, ',', '.') }}</strong>
        <small>Sumber dana</small>
      </div>
    </div>
  </div>
</div>

@if (session('sukses'))
<div class="alert alert-success alert-dismissible d-flex align-items-center mb-4" role="alert">
  <i class="ti ti-circle-check me-2"></i>
  <span>{{ session('sukses') }}</span>
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
</div>
@endif

@if (session('gagal'))
<div class="alert alert-danger alert-dismissible d-flex align-items-center mb-4" role="alert">
  <i class="ti ti-alert-circle me-2"></i>
  <span>{{ session('gagal') }}</span>
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
</div>
@endif

{{-- Card filter --}}
<div class="card mb-4">
  <div class="card-header pb-0">
    <h5 class="card-title mb-0"><i class="ti ti-filter ti-sm me-1"></i>Filter</h5>
  </div>
  <div class="card-body">
    <form id="filter-biaya" method="GET" action="{{ route('operator.biaya.index') }}" class="row g-3 align-items-end">
      <div class="col-12 col-md-6 col-xl-3">
        <label class="form-label" for="search">Cari</label>
        <input type="search" id="search" name="search" class="form-control"
          placeholder="Nama atau kode" value="{{ $filter['search'] ?? '' }}">
      </div>

      <div class="col-12 col-md-6 col-xl-3">
        <label class="form-label" for="sms">Program Studi</label>
        <select id="sms" name="sms" class="form-select select2-filter">
          <option value="">Semua prodi</option>
          @foreach ($prodi as $p)
          <option value="{{ $p->id_sms }}" @selected(($filter['sms'] ?? '') === $p->id_sms)>
            {{ trim($p->jenjang_didik) }} - {{ trim($p->nm_lemb) }}
          </option>
          @endforeach
        </select>
      </div>

      <div class="col-6 col-md-3 col-xl-2">
        <label class="form-label" for="angkatan">Angkatan</label>
        <select id="angkatan" name="angkatan" class="form-select select2-filter">
          <option value="">Semua</option>
          @foreach ($angkatan as $a)
          <option value="{{ $a }}" @selected(($filter['angkatan'] ?? '') == $a)>{{ $a }}</option>
          @endforeach
        </select>
      </div>

      <div class="col-6 col-md-3 col-xl-2">
        <label class="form-label" for="sumber_id">Sumber Dana</label>
        <select id="sumber_id" name="sumber_id" class="form-select select2-filter">
          <option value="">Semua</option>
          @foreach ($sumberDana as $s)
          <option value="{{ $s->sumber_id }}" @selected(($filter['sumber_id'] ?? '') == $s->sumber_id)>{{ $s->sumber_nama }}</option>
          @endforeach
        </select>
      </div>

      <div class="col-12 col-md-6 col-xl-2">
        <label class="form-label" for="ma">Mata Anggaran</label>
        <select id="ma" name="ma" class="form-select select2-filter">
          <option value="">Semua</option>
          @foreach ($mataAnggaran as $m)
          <option value="{{ $m->ma }}" @selected(($filter['ma'] ?? '') == $m->ma)>{{ trim($m->nama) }}</option>
          @endforeach
        </select>
      </div>

      <div class="col-12 d-flex gap-2">
        <button type="submit" class="btn btn-primary">
          <i class="ti ti-filter ti-xs me-1"></i>Terapkan
        </button>
        <a href="{{ route('operator.biaya.index') }}" class="btn btn-label-secondary">Reset</a>
      </div>
    </form>
  </div>
</div>

{{-- Card tabel --}}
<div class="card">
  <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
    <h5 class="card-title mb-0">Daftar Biaya</h5>
    <div class="d-flex flex-wrap align-items-center gap-2">
      <label class="form-label mb-0 text-nowrap" for="limit">Per halaman</label>
      {{-- Terhubung ke form filter lewat atribut form, supaya filter ikut terkirim. --}}
      <select id="limit" name="limit" form="filter-biaya" class="form-select w-auto" onchange="this.form.submit()">
        @foreach ([10, 25, 50, 100] as $n)
        <option value="{{ $n }}" @selected(($filter['limit'] ?? 10) == $n)>{{ $n }}</option>
        @endforeach
      </select>
      <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambahBiaya">
        <i class="ti ti-plus ti-xs me-1"></i>Tambah
      </button>
      <a href="{{ route('operator.matakuliah-praktik.index') }}" class="btn btn-label-primary">
        <i class="ti ti-flask ti-xs me-1"></i>Daftar Matakuliah Praktik
      </a>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-sm table-hover small mb-0">
      <thead>
        <tr>
          <th>Kode</th>
          <th>Nama Biaya</th>
          <th>Angkatan</th>
          <th>Sumber Dana</th>
          <th class="text-center">Prasarat</th>
          <th class="text-center">Kredit</th>
          <th class="text-center">Praktik</th>
          <th class="text-end">Nominal</th>
          <th class="text-center">Aksi</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($data as $row)
        <tr>
          <td class="text-nowrap"><code>{{ $row->kode }}</code></td>
          <td>
            <span class="d-block fw-medium">{{ trim($row->nama) }}</span>
            <small class="text-muted">
              @if ($row->prodi)
              {{ trim($row->prodi->jenjang_didik) }} - {{ trim($row->prodi->nm_lemb) }}
              @else
              -
              @endif
            </small>
          </td>
          <td class="text-nowrap">
            @php($kelas = \App\Repositories\Mak\BiayaRepository::KELAS[(string) $row->kelas] ?? null)
            <span class="d-block fw-medium">{{ $row->angkatan }}</span>
            <small class="text-muted">{{ $kelas ? "({$kelas[0]}) {$kelas[1]}" : ($row->kelas ?? '-') }}</small>
          </td>
          <td>{{ $row->sumber_nama ?: '-' }}</td>
          <td class="text-center text-nowrap">
            @php($prasarat = \App\Repositories\Mak\BiayaRepository::PRASARAT[(int) $row->prasarat] ?? null)
            @if ($prasarat)
            <span class="text-{{ $prasarat[1] }} fw-medium">{{ $prasarat[0] }}</span>
            @endif
          </td>
          @foreach (['kredit', 'praktik'] as $kolom)
          <td class="text-center">
            @if ((int) $row->{$kolom} > 0)
            <i class="ti ti-circle-check-filled text-success fs-5"></i>
            @endif
          </td>
          @endforeach
          <td class="text-end text-nowrap">Rp {{ number_format((float) $row->uang, 0, ',', '.') }}</td>
          <td class="text-center text-nowrap">
            <button type="button" class="btn btn-sm btn-icon btn-label-primary" title="Edit"
              data-ubah-biaya="{{ json_encode([
                'id' => $row->id,
                'dipakai' => (bool) $row->dipakai,
                'ma' => (string) (int) $row->ma,
                'ma_label' => trim($row->kode) . ' — ' . trim($row->nama),
                'sms' => $row->sms,
                'angkatan' => (string) (int) $row->angkatan,
                'kelas' => (string) (int) $row->kelas,
                'uang' => (int) $row->uang,
                'sumber_id' => $row->sumber_id,
                'prasarat' => (string) (int) $row->prasarat,
                'kredit' => (int) $row->kredit === 1,
                'praktik' => (int) $row->praktik === 1,
                'pilihmhs' => (string) $row->pilihmhs === '1',
              ]) }}">
              <i class="ti ti-pencil"></i>
            </button>
            @if ($row->dipakai)
            <span class="d-inline-block" tabindex="0" data-bs-toggle="tooltip" title="Sudah dipakai pada tagihan, tidak dapat dihapus">
              <button type="button" class="btn btn-sm btn-icon btn-label-secondary" disabled>
                <i class="ti ti-lock"></i>
              </button>
            </span>
            @else
            <form method="POST" action="{{ route('operator.biaya.destroy', $row->id) }}" class="d-inline"
              data-hapus-biaya="{{ trim($row->nama) }} ({{ $row->angkatan }}) akan dihapus permanen.">
              @csrf
              @method('DELETE')
              <button type="submit" class="btn btn-sm btn-icon btn-label-danger" title="Hapus">
                <i class="ti ti-trash"></i>
              </button>
            </form>
            @endif
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="9" class="text-center text-muted py-5">Tidak ada data biaya yang cocok dengan filter.</td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  @if ($data->total() > 0)
  <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
    <small class="text-muted">
      Menampilkan {{ number_format($data->firstItem(), 0, ',', '.') }}–{{ number_format($data->lastItem(), 0, ',', '.') }}
      dari {{ number_format($data->total(), 0, ',', '.') }} data
    </small>
    {{ $data->onEachSide(1)->links('pagination::bootstrap-5') }}
  </div>
  @endif
</div>

@include('content.operator.biaya._modal-tambah')
@include('content.operator.biaya._modal-ubah')
@endsection
