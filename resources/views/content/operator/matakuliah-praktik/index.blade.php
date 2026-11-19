@extends('layouts/layoutMaster')

@section('title', 'Matakuliah Praktik')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset(mix('assets/vendor/libs/select2/select2.css')) }}">
@endsection

@section('vendor-script')
<script src="{{ asset(mix('assets/vendor/libs/select2/select2.js')) }}"></script>
@endsection

@section('page-script')
<script>
  $(function () {
    // Select2 untuk filter; opsi kosong dipakai sebagai placeholder.
    $('.select2-filter').each(function () {
      var $el = $(this);
      $el.wrap('<div class="position-relative"></div>').select2({
        width: '100%',
        allowClear: $el.find('option[value=""]').length > 0,
        placeholder: $el.find('option[value=""]').text() || undefined,
        dropdownParent: $el.parent(),
      });
    });

    // Filter pilihan langsung dikirim; pencarian teks lewat Enter / Terapkan.
    $('.select2-filter').on('change', function () {
      this.form.submit();
    });
  });
</script>
@endsection

@section('content')
@php($namaSemester = $semester[$filter['id_smt']] ?? $filter['id_smt'])

<div class="pg-header mb-4">
  <div class="pg-header-main">
    <div class="pg-header-icon">
      <i class="ti ti-flask"></i>
    </div>
    <div class="flex-grow-1 min-w-0">
      <div class="pg-header-crumb">
        <a href="{{ route('operator.dashboard') }}">Dashboard</a>
        <i class="ti ti-chevron-right"></i>
        <a href="{{ route('operator.biaya.index') }}">Data Biaya</a>
        <i class="ti ti-chevron-right"></i>
        <span>Matakuliah Praktik</span>
      </div>
      <h4 class="pg-header-title">Sebaran Matakuliah Praktik</h4>
      <p class="pg-header-sub">Tandai matakuliah praktik per semester, dasar tagihan biaya "Hitung per Praktikum".</p>
    </div>
  </div>

  <div class="pg-header-stats">
    <div class="pg-stat">
      <span class="pg-stat-icon"><i class="ti ti-calendar"></i></span>
      <div>
        <strong>{{ $namaSemester }}</strong>
        <small>Semester</small>
      </div>
    </div>
    <div class="pg-stat">
      <span class="pg-stat-icon is-info"><i class="ti ti-book"></i></span>
      <div>
        <strong>{{ number_format($data->total(), 0, ',', '.') }}</strong>
        <small>Matakuliah{{ array_filter(\Illuminate\Support\Arr::only($filter, ['sms', 'angkatan', 'status', 'search'])) ? ' (terfilter)' : '' }}</small>
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

{{-- Card filter --}}
<div class="card mb-4">
  <div class="card-header pb-0">
    <h5 class="card-title mb-0"><i class="ti ti-filter ti-sm me-1"></i>Filter</h5>
  </div>
  <div class="card-body">
    <form id="filter-mk" method="GET" action="{{ route('operator.matakuliah-praktik.index') }}" class="row g-3 align-items-end">
      <div class="col-12 col-md-6 col-xl-3">
        <label class="form-label" for="id_smt">Tahun Akademik</label>
        <select id="id_smt" name="id_smt" class="form-select select2-filter">
          @foreach ($semester as $id => $nama)
          <option value="{{ $id }}" @selected($filter['id_smt'] == $id)>{{ $nama }}</option>
          @endforeach
        </select>
      </div>

      <div class="col-6 col-md-3 col-xl-2">
        <label class="form-label" for="angkatan">Th Masuk</label>
        <select id="angkatan" name="angkatan" class="form-select select2-filter">
          <option value="">Semua</option>
          @foreach ($angkatan as $a)
          <option value="{{ $a }}" @selected(($filter['angkatan'] ?? '') == $a)>{{ $a }}</option>
          @endforeach
        </select>
      </div>

      <div class="col-6 col-md-3 col-xl-2">
        <label class="form-label" for="status">Status</label>
        <select id="status" name="status" class="form-select select2-filter">
          <option value="">Semua</option>
          <option value="praktik" @selected(($filter['status'] ?? '') === 'praktik')>Praktik</option>
          <option value="bukan" @selected(($filter['status'] ?? '') === 'bukan')>Bukan praktik</option>
        </select>
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

      <div class="col-12 col-md-6 col-xl-2">
        <label class="form-label" for="search">Cari Matakuliah</label>
        <input type="search" id="search" name="search" class="form-control"
          placeholder="Kode atau nama" value="{{ $filter['search'] ?? '' }}">
      </div>

      <div class="col-12 d-flex gap-2">
        <button type="submit" class="btn btn-primary">
          <i class="ti ti-filter ti-xs me-1"></i>Terapkan
        </button>
        <a href="{{ route('operator.matakuliah-praktik.index') }}" class="btn btn-label-secondary">Reset</a>
      </div>
    </form>
  </div>
</div>

{{-- Card tabel --}}
<div class="card">
  <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
    <h5 class="card-title mb-0">Daftar Sebaran Matakuliah</h5>
    <div class="d-flex flex-wrap align-items-center gap-2">
      <label class="form-label mb-0 text-nowrap" for="limit">Per halaman</label>
      <select id="limit" name="limit" form="filter-mk" class="form-select w-auto" onchange="this.form.submit()">
        @foreach ([10, 25, 50, 100] as $n)
        <option value="{{ $n }}" @selected(($filter['limit'] ?? 25) == $n)>{{ $n }}</option>
        @endforeach
      </select>
      <a href="{{ route('operator.biaya.index') }}" class="btn btn-label-secondary">
        <i class="ti ti-arrow-left ti-xs me-1"></i>Data Biaya
      </a>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-sm table-hover small mb-0">
      <thead>
        <tr>
          <th class="text-center">#</th>
          <th>Kode MK</th>
          <th>Nama Matakuliah</th>
          <th class="text-center">SKS</th>
          <th class="text-center">Sem MK</th>
          <th class="text-center">Peserta</th>
          <th class="text-center">Praktik</th>
          <th class="text-center">Aksi</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($data as $row)
        @php($praktik = (int) $row->praktik)
        @php($semua = $praktik > 0 && $praktik >= (int) $row->peserta)
        @php($p = $prodiByID[$row->id_sms] ?? null)
        <tr>
          <td class="text-center text-muted">{{ $data->firstItem() + $loop->index }}</td>
          <td class="text-nowrap"><code>{{ trim($row->kode_mk) }}</code></td>
          <td>
            <span class="d-block fw-medium">{{ trim($row->nm_mk) }}</span>
            <small class="text-muted">{{ $p ? trim($p->jenjang_didik) . ' - ' . trim($p->nm_lemb) : '-' }}</small>
          </td>
          <td class="text-center">{{ (int) $row->sks_mk }}</td>
          <td class="text-center">{{ $row->mk_pel !== null ? (int) $row->mk_pel : '-' }}</td>
          <td class="text-center">{{ number_format((int) $row->peserta, 0, ',', '.') }}</td>
          <td class="text-center text-nowrap">
            @if ($semua)
            <i class="ti ti-circle-check-filled text-success fs-5" title="Praktik"></i>
            @elseif ($praktik > 0)
            <span class="text-warning fw-medium" title="Sebagian peserta ditandai praktik">Sebagian ({{ $praktik }}/{{ $row->peserta }})</span>
            @endif
          </td>
          <td class="text-center text-nowrap">
            <form method="POST" action="{{ route('operator.matakuliah-praktik.update', $row->id_mk) }}" class="d-inline">
              @csrf
              @method('PATCH')
              <input type="hidden" name="id_smt" value="{{ trim($row->id_smt) }}">
              @if ($semua)
              <input type="hidden" name="praktik" value="0">
              <button type="submit" class="btn btn-sm btn-label-danger" title="Hapus tanda praktik">
                <i class="ti ti-x ti-xs me-1"></i>Batal
              </button>
              @else
              <input type="hidden" name="praktik" value="1">
              <button type="submit" class="btn btn-sm btn-label-success" title="Tandai sebagai praktik">
                <i class="ti ti-check ti-xs me-1"></i>Praktik
              </button>
              @endif
            </form>
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="8" class="text-center text-muted py-5">Tidak ada matakuliah pada {{ $namaSemester }} yang cocok dengan filter.</td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  @if ($data->total() > 0)
  <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
    <small class="text-muted">
      Menampilkan {{ number_format($data->firstItem(), 0, ',', '.') }}–{{ number_format($data->lastItem(), 0, ',', '.') }}
      dari {{ number_format($data->total(), 0, ',', '.') }} matakuliah
    </small>
    {{ $data->onEachSide(1)->links('pagination::bootstrap-5') }}
  </div>
  @endif
</div>
@endsection
