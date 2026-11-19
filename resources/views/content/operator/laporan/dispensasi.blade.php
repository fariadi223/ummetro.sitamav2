@extends('layouts/layoutMaster')

@section('title', 'Laporan Dispensasi')

@php
  $STATUS = \App\Repositories\Laporan\LaporanDispensasiRepository::STATUS;
  $status = (string) ($filter['status'] ?? '');
  $urlStatus = fn ($s) => route('operator.laporan.dispensasi.index', array_filter(
    ['status' => $s] + \Illuminate\Support\Arr::except($filter, ['status']),
    fn ($v) => $v !== null && $v !== ''
  ));
  $rp = fn ($n) => number_format((float) $n, 0, ',', '.');
@endphp

@section('vendor-style')
<link rel="stylesheet" href="{{ asset(mix('assets/vendor/libs/select2/select2.css')) }}">
@endsection

@section('vendor-script')
<script src="{{ asset(mix('assets/vendor/libs/select2/select2.js')) }}"></script>
@endsection

@section('page-style')
<style>
  .min-w-0 { min-width: 0 !important; }
  :root { --ld-head-h: 3.75rem; }

  .ld-sum { font-size: .75rem; color: var(--bs-secondary-color); }
  .ld-sum strong { font-size: .95rem; color: var(--bs-heading-color, var(--bs-body-color)); display: block; }

  .ld-filter { background: var(--bs-tertiary-bg, rgba(0,0,0,.015)); }
  .ld-filter .form-label { font-size: .75rem; margin-bottom: .25rem; }

  .ld-tabs-header { padding: 0 1rem; border-bottom: 1px solid var(--bs-border-color); }
  .ld-tabs {
    flex-wrap: nowrap; overflow-x: auto; overflow-y: hidden;
    border-bottom: 0; margin-bottom: -1px; scrollbar-width: none; gap: .25rem;
  }
  .ld-tabs::-webkit-scrollbar { display: none; }
  .ld-tabs .nav-link {
    display: inline-flex; align-items: center; gap: .4rem;
    min-height: var(--ld-head-h); padding: 0 .9rem;
    font-size: .8125rem; font-weight: 500; white-space: nowrap;
    border: 0; border-bottom: 2px solid transparent; border-radius: 0;
    background: transparent; color: var(--bs-secondary-color);
  }
  .ld-tabs .nav-link i { font-size: 1.05rem; }
  .ld-tabs .nav-link:hover { color: var(--bs-primary); }
  .ld-tabs .nav-link.active { color: var(--bs-primary); border-bottom-color: var(--bs-primary); box-shadow: none; }
  .ld-tabs .badge { font-size: .65rem; line-height: 1; padding: .3em .55em; }

  .ld-scroll { max-height: max(340px, calc(100vh - 430px)); overflow-y: auto; overflow-x: hidden; }
  .ld-scroll thead th {
    position: sticky; top: 0; z-index: 2;
    background: var(--bs-card-bg, var(--bs-body-bg));
    box-shadow: inset 0 -1px 0 var(--bs-border-color);
    white-space: nowrap;
  }
  .ld-table td { vertical-align: middle; }
  .ld-table .ld-wrap { overflow-wrap: anywhere; word-break: break-word; }
  .ld-meta { font-size: .75rem; color: var(--bs-secondary-color); }
  .ld-empty { padding: 2.5rem 1rem; text-align: center; color: var(--bs-secondary-color); }
  .ld-empty i { font-size: 2rem; display: block; margin-bottom: .4rem; opacity: .5; }

  @media (max-width: 575.98px) {
    .ld-scroll { max-height: none; }
  }
</style>
@endsection

@section('page-script')
<script>
  $(function () {
    $('.select2-filter').each(function () {
      var $el = $(this);
      $el.wrap('<div class="position-relative"></div>').select2({
        width: '100%', allowClear: true,
        placeholder: $el.find('option[value=""]').text(),
        dropdownParent: $el.parent(),
      });
    });
    // Filter langsung diterapkan saat pilihan berubah; NPM dengan Enter.
    $('#filter-dispensasi select').on('change', function () { this.form.submit(); });

    $('#btn-export').on('click', function () {
      var q = $('#filter-dispensasi').serialize();
      location.href = @json(route('operator.laporan.dispensasi.export')) + (q ? '?' + q : '');
    });
  });
</script>
@endsection

@section('content')
<div class="pg-header mb-4">
  <div class="pg-header-main">
    <div class="pg-header-icon">
      <i class="ti ti-discount-2"></i>
    </div>
    <div class="flex-grow-1 min-w-0">
      <div class="pg-header-crumb">
        <a href="{{ route('operator.dashboard') }}">Dashboard</a>
        <i class="ti ti-chevron-right"></i>
        <span>Laporan</span>
        <i class="ti ti-chevron-right"></i>
        <span>Dispensasi</span>
      </div>
      <h4 class="pg-header-title">Laporan Dispensasi Pembayaran Mahasiswa</h4>
      <p class="pg-header-sub">Biaya pokok, dispensasi, dan tagihan per rekening mahasiswa dalam satu tahun akademik.</p>
    </div>
  </div>

  <div class="pg-header-stats">
    <div class="pg-stat">
      <span class="pg-stat-icon is-info"><i class="ti ti-calendar"></i></span>
      <div>
        <strong>{{ $semester[$filter['id_smt']] ?? $filter['id_smt'] }}</strong>
        <small>Tahun akademik</small>
      </div>
    </div>
    <div class="pg-stat">
      <span class="pg-stat-icon"><i class="ti ti-users"></i></span>
      <div>
        <strong>{{ $rp($ringkasan['mahasiswa']) }}</strong>
        <small>Mahasiswa · {{ $rp($ringkasan['rincian']) }} rincian</small>
      </div>
    </div>
    <div class="pg-stat">
      <span class="pg-stat-icon is-warning"><i class="ti ti-discount-2"></i></span>
      <div>
        <strong>Rp {{ $rp($ringkasan['potongan']) }}</strong>
        <small>Total dispensasi</small>
      </div>
    </div>
    <div class="pg-stat">
      <span class="pg-stat-icon is-success"><i class="ti ti-receipt"></i></span>
      <div>
        <strong>Rp {{ $rp($ringkasan['tagihan']) }}</strong>
        <small>Tagihan setelah dispensasi</small>
      </div>
    </div>
  </div>
</div>

{{-- Filter + ringkasan --}}
<div class="card mb-3">
  <div class="ld-filter px-3 py-3 rounded-top">
    <form id="filter-dispensasi" method="GET" action="{{ route('operator.laporan.dispensasi.index') }}" class="row g-2 align-items-end" autocomplete="off">
      @if ($status !== '')
      <input type="hidden" name="status" value="{{ $status }}">
      @endif
      <div class="col-12 col-sm-6 col-lg-3 col-xxl-2">
        <label class="form-label" for="id_smt">Tahun Akademik</label>
        <select id="id_smt" name="id_smt" class="form-select">
          @foreach ($semester as $id => $nama)
          <option value="{{ $id }}" @selected($id == $filter['id_smt'])>{{ $nama }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-12 col-sm-6 col-lg-3 col-xxl">
        <label class="form-label" for="id_sms">Program Studi</label>
        <select id="id_sms" name="id_sms" class="form-select select2-filter">
          <option value="">Semua prodi</option>
          @foreach ($prodi as $p)
          <option value="{{ $p->id_sms }}" @selected(($filter['id_sms'] ?? '') === $p->id_sms)>
            {{ trim($p->jenjang_didik) }} {{ trim($p->nm_lemb) }}
          </option>
          @endforeach
        </select>
      </div>
      <div class="col-6 col-sm-3 col-lg-2 col-xxl-1">
        <label class="form-label" for="angkatan">Angkatan</label>
        <select id="angkatan" name="angkatan" class="form-select">
          <option value="">Semua</option>
          @foreach ($angkatan as $a)
          <option value="{{ $a }}" @selected(($filter['angkatan'] ?? '') == $a)>{{ $a }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-12 col-sm-9 col-lg-4 col-xxl">
        <label class="form-label" for="ma">Nama Rekening</label>
        <select id="ma" name="ma" class="form-select select2-filter">
          <option value="">Semua rekening</option>
          @foreach ($mataAnggaran as $m)
          <option value="{{ (int) $m->ma }}" @selected(($filter['ma'] ?? '') == (int) $m->ma)>{{ trim($m->nama) }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-6 col-sm-5 col-lg-3 col-xxl-2">
        <label class="form-label" for="npm">NPM</label>
        <div class="input-group input-group-merge">
          <span class="input-group-text"><i class="ti ti-search"></i></span>
          <input type="search" id="npm" name="npm" class="form-control" placeholder="NPM, lalu Enter"
            value="{{ $filter['npm'] ?? '' }}" inputmode="numeric">
        </div>
      </div>
      <div class="col-6 col-sm-3 col-lg-2 col-xxl-1">
        <label class="form-label" for="limit">Per halaman</label>
        <select id="limit" name="limit" class="form-select">
          @foreach ([10, 25, 50, 100] as $n)
          <option value="{{ $n }}" @selected(($filter['limit'] ?? 25) == $n)>{{ $n }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-12 col-sm-4 col-lg col-xxl-auto d-flex gap-2 justify-content-end">
        <a href="{{ route('operator.laporan.dispensasi.index') }}" class="btn btn-label-secondary" title="Reset filter">
          <i class="ti ti-refresh ti-xs"></i><span class="d-none d-lg-inline ms-1">Reset</span>
        </a>
        <button type="button" id="btn-export" class="btn btn-success">
          <i class="ti ti-file-spreadsheet ti-xs me-1"></i>Excel
        </button>
      </div>
    </form>
  </div>

  <div class="card-body border-top py-3">
    <div class="d-flex flex-wrap justify-content-between gap-3">
      <div class="ld-sum">Biaya pokok<strong>Rp {{ $rp($ringkasan['pokok']) }}</strong></div>
      <div class="ld-sum">Dispensasi<strong class="text-warning">− Rp {{ $rp($ringkasan['potongan']) }}</strong></div>
      <div class="ld-sum text-end">Tagihan<strong class="text-success">Rp {{ $rp($ringkasan['tagihan']) }}</strong></div>
    </div>
  </div>
</div>

{{-- Tabel rincian --}}
<div class="card">
  <div class="card-header ld-tabs-header">
    <ul class="nav nav-tabs ld-tabs" role="tablist">
      <li class="nav-item">
        <a class="nav-link @if ($status === '') active @endif" href="{{ $urlStatus(null) }}">
          <i class="ti ti-list"></i><span>Semua</span>
          <span class="badge rounded-pill bg-label-primary">{{ $rp($ringkasan['rincian']) }}</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link @if ($status === '1') active @endif" href="{{ $urlStatus('1') }}">
          <i class="ti ti-discount-2"></i><span>{{ $STATUS['1'] }}</span>
          <span class="badge rounded-pill bg-warning">{{ $rp($ringkasan['aktif']) }}</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link @if ($status === '0') active @endif" href="{{ $urlStatus('0') }}" title="Dispensasi sudah dimasukkan kembali ke struk atau nominalnya 0">
          <i class="ti ti-circle-check"></i><span>{{ $STATUS['0'] }}</span>
          <span class="badge rounded-pill bg-label-secondary">{{ $rp($ringkasan['nol']) }}</span>
        </a>
      </li>
    </ul>
  </div>

  <div class="ld-scroll">
    <table class="table table-sm table-hover small mb-0 ld-table">
      <thead>
        <tr>
          <th class="d-none d-sm-table-cell">#</th>
          <th>Mahasiswa</th>
          <th class="d-none d-lg-table-cell">Program Studi</th>
          <th>Rekening</th>
          <th class="text-end d-none d-md-table-cell">Biaya Pokok</th>
          <th class="text-end">Dispensasi</th>
          <th class="text-end">Tagihan</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($data as $i => $row)
        @php($prodiNama = trim($row->jenjang_didik) . ' ' . trim($row->nm_lemb))
        <tr>
          <td class="d-none d-sm-table-cell text-muted">{{ $data->firstItem() + $i }}</td>
          <td class="ld-wrap">
            <span class="ld-meta d-block">{{ trim($row->nipd) }} · {{ substr(trim((string) $row->mulai_smt), 0, 4) }}</span>
            <span class="fw-medium">{{ trim((string) $row->nm_pd) }}</span>
            <small class="d-block d-lg-none text-muted">{{ $prodiNama }}</small>
          </td>
          <td class="d-none d-lg-table-cell ld-wrap">{{ $prodiNama }}</td>
          <td class="ld-wrap">
            {{ trim((string) $row->uraian) ?: '—' }}
            <small class="d-block text-muted">MA {{ $row->ma }}@if ($row->terjurnal) · <span class="text-success">terjurnal</span>@endif</small>
          </td>
          <td class="text-end text-nowrap d-none d-md-table-cell">{{ $rp($row->uang_pokok) }}</td>
          <td class="text-end text-nowrap">
            @if ((int) $row->uang_potongan > 0)
            <span class="text-warning fw-medium">{{ $rp($row->uang_potongan) }}</span>
            @else
            <span class="text-muted">0</span>
            @endif
          </td>
          <td class="text-end text-nowrap fw-medium">
            {{ $rp($row->tagihan) }}
            <small class="d-block d-md-none text-muted fw-normal">pokok {{ $rp($row->uang_pokok) }}</small>
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="7" class="p-0">
            <div class="ld-empty"><i class="ti ti-report-off"></i>Tidak ada dispensasi yang cocok dengan filter.</div>
          </td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  @if ($data->total() > 0)
  <div class="card-footer border-top d-flex flex-wrap justify-content-between align-items-center gap-2 py-3">
    <small class="text-muted">
      {{ $rp($data->firstItem()) }}–{{ $rp($data->lastItem()) }} dari {{ $rp($data->total()) }} rincian
    </small>
    {{ $data->onEachSide(1)->links('pagination::bootstrap-5') }}
  </div>
  @endif
</div>
@endsection
