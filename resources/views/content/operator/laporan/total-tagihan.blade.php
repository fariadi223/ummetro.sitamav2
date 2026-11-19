@extends('layouts/layoutMaster')

@section('title', 'Laporan Total Tagihan')

@php
  $STATUS = \App\Repositories\Laporan\LaporanTotalTagihanRepository::STATUS;
  $status = (string) ($filter['status'] ?? '');
  $persen = $ringkasan['mahasiswa'] ? round($ringkasan['lunas_mhs'] / $ringkasan['mahasiswa'] * 100, 1) : 0;
  $urlStatus = fn ($s) => route('operator.laporan.total-tagihan.index', array_filter(
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
  :root { --lt-head-h: 3.75rem; }

  .lt-progress { height: .5rem; border-radius: 50rem; }
  .lt-sum { font-size: .75rem; color: var(--bs-secondary-color); }
  .lt-sum strong { font-size: .95rem; color: var(--bs-heading-color, var(--bs-body-color)); display: block; }

  .lt-filter { background: var(--bs-tertiary-bg, rgba(0,0,0,.015)); }
  .lt-filter .form-label { font-size: .75rem; margin-bottom: .25rem; }

  .lt-tabs-header { padding: 0 1rem; border-bottom: 1px solid var(--bs-border-color); }
  .lt-tabs {
    flex-wrap: nowrap; overflow-x: auto; overflow-y: hidden;
    border-bottom: 0; margin-bottom: -1px; scrollbar-width: none; gap: .25rem;
  }
  .lt-tabs::-webkit-scrollbar { display: none; }
  .lt-tabs .nav-link {
    display: inline-flex; align-items: center; gap: .4rem;
    min-height: var(--lt-head-h); padding: 0 .9rem;
    font-size: .8125rem; font-weight: 500; white-space: nowrap;
    border: 0; border-bottom: 2px solid transparent; border-radius: 0;
    background: transparent; color: var(--bs-secondary-color);
  }
  .lt-tabs .nav-link i { font-size: 1.05rem; }
  .lt-tabs .nav-link:hover { color: var(--bs-primary); }
  .lt-tabs .nav-link.active { color: var(--bs-primary); border-bottom-color: var(--bs-primary); box-shadow: none; }
  .lt-tabs .badge { font-size: .65rem; line-height: 1; padding: .3em .55em; }

  .lt-scroll { max-height: max(340px, calc(100vh - 430px)); overflow-y: auto; overflow-x: hidden; }
  .lt-scroll thead th {
    position: sticky; top: 0; z-index: 2;
    background: var(--bs-card-bg, var(--bs-body-bg));
    box-shadow: inset 0 -1px 0 var(--bs-border-color);
    white-space: nowrap;
  }
  .lt-table td { vertical-align: middle; }
  .lt-table .lt-wrap { overflow-wrap: anywhere; word-break: break-word; }
  .lt-meta { font-size: .75rem; color: var(--bs-secondary-color); }
  .lt-empty { padding: 2.5rem 1rem; text-align: center; color: var(--bs-secondary-color); }
  .lt-empty i { font-size: 2rem; display: block; margin-bottom: .4rem; opacity: .5; }

  .lt-aksi { width: 1%; white-space: nowrap; }
  .lt-aksi .btn { width: 1.9rem; height: 1.9rem; padding: 0; }

  /* Modal rincian */
  .lt-dl { display: grid; grid-template-columns: max-content 1fr; gap: .35rem 1rem; margin: 0; font-size: .8125rem; }
  .lt-dl dt { font-weight: 400; color: var(--bs-secondary-color); }
  .lt-dl dd { margin: 0; font-weight: 500; overflow-wrap: anywhere; }
  .lt-modal-tabel td, .lt-modal-tabel th { vertical-align: middle; }
  .lt-modal-tabel tfoot td { font-weight: 600; }

  @media (max-width: 575.98px) {
    .lt-scroll { max-height: none; }
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
    $('#filter-total select').on('change', function () { this.form.submit(); });

    // Rincian tagihan & pembayaran satu mahasiswa di modal.
    var urlDetail = @json(route('operator.laporan.total-tagihan.detail', '__ID__'));
    var idSmt = @json($filter['id_smt']);
    var $modal = $('#modalRincianTagihan');
    var rp = function (n) { return Number(n || 0).toLocaleString('id-ID'); };
    var esc = function (v) { return $('<div>').text(v == null || v === '' ? '—' : String(v)).html(); };
    var permintaan = null;

    var kosong = function (kolom, teks) {
      return '<tr><td colspan="' + kolom + '" class="text-center text-muted py-3">' + teks + '</td></tr>';
    };

    var render = function (d) {
      var info = [
        ['NPM', d.npm], ['Nama', d.nama], ['Program studi', d.prodi],
        ['Angkatan', d.angkatan], ['Tahun akademik', d.semester],
      ];
      $modal.find('.lt-dl').html($.map(info, function (r) {
        return '<dt>' + esc(r[0]) + '</dt><dd>' + esc(r[1]) + '</dd>';
      }).join(''));

      $modal.find('.lt-r-total').text(rp(d.total));
      $modal.find('.lt-r-dibayar').text(rp(d.dibayar));
      $modal.find('.lt-r-sisa').text(d.sisa > 0 ? rp(d.sisa) : (d.sisa < 0 ? 'lebih ' + rp(-d.sisa) : 'Lunas'))
        .toggleClass('text-danger', d.sisa > 0).toggleClass('text-success', d.sisa <= 0);

      $modal.find('.lt-r-tagihan').html(d.tagihan.length ? $.map(d.tagihan, function (t) {
        return '<tr><td class="text-nowrap">' + esc(t.struk_id) + '<small class="d-block text-muted">' + esc(t.tanggal) + '</small></td>' +
          '<td>' + esc(t.keterangan) + '<small class="d-block text-muted">MA ' + esc(t.ma) + '</small></td>' +
          '<td class="text-end text-nowrap">' + rp(t.uang) + '</td>' +
          '<td class="text-center">' + (t.lunas
            ? '<span class="badge bg-label-success">Lunas</span>'
            : '<span class="badge bg-label-warning">Belum</span>') + '</td></tr>';
      }).join('') : kosong(4, 'Tidak ada rincian tagihan.'));
      $modal.find('.lt-r-tagihan-sum').text(rp(d.total));

      $modal.find('.lt-r-bayar').html(d.pembayaran.length ? $.map(d.pembayaran, function (p) {
        return '<tr><td class="text-nowrap">' + esc(p.tanggal) + '<small class="d-block text-muted">angsuran ' + esc(p.angsur_ke) + '</small></td>' +
          '<td>' + esc(p.keterangan) + '<small class="d-block text-muted">MA ' + esc(p.ma) + ' · KPP #' + esc(p.kpp_dtl) + '</small></td>' +
          '<td>' + esc(p.bank) + '</td>' +
          '<td class="text-end text-nowrap">' + rp(p.uang) + '</td></tr>';
      }).join('') : kosong(4, 'Belum ada pembayaran.'));
      $modal.find('.lt-r-bayar-sum').text(rp(d.dibayar));
    };

    $(document).on('click', '[data-rincian]', function () {
      $modal.find('.lt-detail-isi, .lt-detail-galat').addClass('d-none');
      $modal.find('.lt-detail-muat').removeClass('d-none');
      bootstrap.Modal.getOrCreateInstance($modal[0]).show();

      if (permintaan) permintaan.abort();
      permintaan = $.getJSON(urlDetail.replace('__ID__', $(this).data('rincian')), { id_smt: idSmt })
        .done(function (d) {
          render(d);
          $modal.find('.lt-detail-muat').addClass('d-none');
          $modal.find('.lt-detail-isi').removeClass('d-none');
        })
        .fail(function (xhr, status) {
          if (status === 'abort') return;
          $modal.find('.lt-detail-muat').addClass('d-none');
          $modal.find('.lt-detail-galat').removeClass('d-none')
            .text(xhr.status === 404 ? 'Mahasiswa tidak ditemukan.' : 'Rincian gagal dimuat. Coba lagi.');
        });
    });

    $('#btn-export').on('click', function () {
      var q = $('#filter-total').serialize();
      location.href = @json(route('operator.laporan.total-tagihan.export')) + (q ? '?' + q : '');
    });
  });
</script>
@endsection

@section('content')
<div class="pg-header mb-4">
  <div class="pg-header-main">
    <div class="pg-header-icon">
      <i class="ti ti-report-money"></i>
    </div>
    <div class="flex-grow-1 min-w-0">
      <div class="pg-header-crumb">
        <a href="{{ route('operator.dashboard') }}">Dashboard</a>
        <i class="ti ti-chevron-right"></i>
        <span>Laporan</span>
        <i class="ti ti-chevron-right"></i>
        <span>Total Tagihan</span>
      </div>
      <h4 class="pg-header-title">Laporan Jumlah Tagihan Mahasiswa</h4>
      <p class="pg-header-sub">Total tagihan, yang sudah dibayar, dan sisa tagihan per mahasiswa dalam satu tahun akademik.</p>
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
        <small>Mahasiswa bertagihan</small>
      </div>
    </div>
    <div class="pg-stat">
      <span class="pg-stat-icon is-success"><i class="ti ti-circle-check"></i></span>
      <div>
        <strong>Rp {{ $rp($ringkasan['dibayar']) }}</strong>
        <small>Sudah dibayar</small>
      </div>
    </div>
    <div class="pg-stat">
      <span class="pg-stat-icon is-danger"><i class="ti ti-clock"></i></span>
      <div>
        <strong>Rp {{ $rp($ringkasan['sisa']) }}</strong>
        <small>Belum dibayar</small>
      </div>
    </div>
  </div>
</div>

{{-- Filter + ringkasan --}}
<div class="card mb-3">
  <div class="lt-filter px-3 py-3 rounded-top">
    <form id="filter-total" method="GET" action="{{ route('operator.laporan.total-tagihan.index') }}" class="row g-2 align-items-end" autocomplete="off">
      @if ($status !== '')
      <input type="hidden" name="status" value="{{ $status }}">
      @endif
      <div class="col-12 col-sm-6 col-lg-3">
        <label class="form-label" for="id_smt">Tahun Akademik</label>
        <select id="id_smt" name="id_smt" class="form-select">
          @foreach ($semester as $id => $nama)
          <option value="{{ $id }}" @selected($id == $filter['id_smt'])>{{ $nama }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-12 col-sm-6 col-lg">
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
      <div class="col-6 col-sm-3 col-lg-1">
        <label class="form-label" for="angkatan">Angkatan</label>
        <select id="angkatan" name="angkatan" class="form-select">
          <option value="">Semua</option>
          @foreach ($angkatan as $a)
          <option value="{{ $a }}" @selected(($filter['angkatan'] ?? '') == $a)>{{ $a }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-6 col-sm-5 col-lg-2">
        <label class="form-label" for="npm">NPM</label>
        <div class="input-group input-group-merge">
          <span class="input-group-text"><i class="ti ti-search"></i></span>
          <input type="search" id="npm" name="npm" class="form-control" placeholder="NPM, lalu Enter"
            value="{{ $filter['npm'] ?? '' }}" inputmode="numeric">
        </div>
      </div>
      <div class="col-6 col-sm-4 col-lg-1">
        <label class="form-label" for="limit">Per halaman</label>
        <select id="limit" name="limit" class="form-select">
          @foreach ([10, 25, 50, 100] as $n)
          <option value="{{ $n }}" @selected(($filter['limit'] ?? 25) == $n)>{{ $n }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-6 col-sm-12 col-lg-auto d-flex gap-2 justify-content-end">
        <a href="{{ route('operator.laporan.total-tagihan.index') }}" class="btn btn-label-secondary" title="Reset filter">
          <i class="ti ti-refresh ti-xs"></i><span class="d-none d-lg-inline ms-1">Reset</span>
        </a>
        <button type="button" id="btn-export" class="btn btn-success">
          <i class="ti ti-file-spreadsheet ti-xs me-1"></i>Excel
        </button>
      </div>
    </form>
  </div>

  <div class="card-body border-top py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-2">
      <div class="lt-sum">Total tagihan<strong>Rp {{ $rp($ringkasan['total']) }}</strong></div>
      <div class="lt-sum text-end">Mahasiswa lunas<strong class="text-success">{{ $rp($ringkasan['lunas_mhs']) }} dari {{ $rp($ringkasan['mahasiswa']) }} ({{ str_replace('.', ',', $persen) }}%)</strong></div>
    </div>
    <div class="progress lt-progress" role="progressbar" aria-label="Persentase mahasiswa lunas" aria-valuenow="{{ $persen }}" aria-valuemin="0" aria-valuemax="100">
      <div class="progress-bar bg-success" style="width: {{ $persen }}%"></div>
    </div>
  </div>
</div>

{{-- Tabel per mahasiswa --}}
<div class="card">
  <div class="card-header lt-tabs-header">
    <ul class="nav nav-tabs lt-tabs" role="tablist">
      <li class="nav-item">
        <a class="nav-link @if ($status === '') active @endif" href="{{ $urlStatus(null) }}">
          <i class="ti ti-list"></i><span>Semua</span>
          <span class="badge rounded-pill bg-label-primary">{{ $rp($ringkasan['mahasiswa']) }}</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link @if ($status === '0') active @endif" href="{{ $urlStatus('0') }}">
          <i class="ti ti-clock"></i><span>{{ $STATUS['0'] }}</span>
          <span class="badge rounded-pill bg-warning">{{ $rp($ringkasan['belum_mhs']) }}</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link @if ($status === '1') active @endif" href="{{ $urlStatus('1') }}">
          <i class="ti ti-circle-check"></i><span>{{ $STATUS['1'] }}</span>
          <span class="badge rounded-pill bg-success">{{ $rp($ringkasan['lunas_mhs']) }}</span>
        </a>
      </li>
    </ul>
  </div>

  <div class="lt-scroll">
    <table class="table table-sm table-hover small mb-0 lt-table">
      <thead>
        <tr>
          <th class="d-none d-sm-table-cell">#</th>
          <th>Mahasiswa</th>
          <th class="d-none d-lg-table-cell">Program Studi</th>
          <th class="text-end">Total Tagihan</th>
          <th class="text-end d-none d-md-table-cell">Sudah Dibayar</th>
          <th class="text-end">Sisa</th>
          <th class="lt-aksi text-center">Aksi</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($data as $i => $row)
        @php($prodiNama = trim($row->jenjang_didik) . ' ' . trim($row->nm_lemb))
        <tr>
          <td class="d-none d-sm-table-cell text-muted">{{ $data->firstItem() + $i }}</td>
          <td class="lt-wrap">
            <span class="lt-meta d-block">{{ trim($row->nipd) }} · {{ substr(trim((string) $row->mulai_smt), 0, 4) }}</span>
            <span class="fw-medium">{{ trim((string) $row->nm_pd) }}</span>
            <small class="d-block d-lg-none text-muted">{{ $prodiNama }}</small>
          </td>
          <td class="d-none d-lg-table-cell lt-wrap">{{ $prodiNama }}</td>
          <td class="text-end text-nowrap">{{ $rp($row->total) }}</td>
          <td class="text-end text-nowrap d-none d-md-table-cell">{{ $rp($row->dibayar) }}</td>
          <td class="text-end text-nowrap fw-medium">
            @if ((int) $row->sisa > 0)
            <span class="text-danger">{{ $rp($row->sisa) }}</span>
            @else
            <span class="badge bg-label-success">Lunas</span>
            @if ((int) $row->sisa < 0)
            <small class="d-block text-muted mt-1" title="Pembayaran melebihi tagihan">lebih {{ $rp(-$row->sisa) }}</small>
            @endif
            @endif
            <small class="d-block d-md-none text-muted mt-1">dibayar {{ $rp($row->dibayar) }}</small>
          </td>
          <td class="lt-aksi text-center">
            <button type="button" class="btn btn-icon btn-sm btn-text-primary rounded-pill"
              data-rincian="{{ $row->id_reg_pd }}" title="Detail rincian" aria-label="Detail rincian {{ trim($row->nipd) }}">
              <i class="ti ti-eye ti-xs"></i>
            </button>
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="7" class="p-0">
            <div class="lt-empty"><i class="ti ti-report-off"></i>Tidak ada tagihan yang cocok dengan filter.</div>
          </td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  @if ($data->total() > 0)
  <div class="card-footer border-top d-flex flex-wrap justify-content-between align-items-center gap-2 py-3">
    <small class="text-muted">
      {{ $rp($data->firstItem()) }}–{{ $rp($data->lastItem()) }} dari {{ $rp($data->total()) }} mahasiswa
    </small>
    {{ $data->onEachSide(1)->links('pagination::bootstrap-5') }}
  </div>
  @endif
</div>

{{-- Modal rincian tagihan & pembayaran satu mahasiswa --}}
<div class="modal fade" id="modalRincianTagihan" tabindex="-1" aria-labelledby="modalRincianTagihanLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalRincianTagihanLabel">Rincian Tagihan</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <div class="lt-detail-muat text-center text-muted py-5">
          <div class="spinner-border spinner-border-sm me-2" role="status"></div>Memuat rincian…
        </div>
        <div class="lt-detail-galat alert alert-danger mb-0 d-none" role="alert"></div>
        <div class="lt-detail-isi d-none">
          <dl class="lt-dl"></dl>

          <div class="row g-2 mt-2 text-center">
            <div class="col-4"><div class="border rounded p-2"><small class="text-muted d-block">Total tagihan</small><strong class="lt-r-total"></strong></div></div>
            <div class="col-4"><div class="border rounded p-2"><small class="text-muted d-block">Sudah dibayar</small><strong class="lt-r-dibayar"></strong></div></div>
            <div class="col-4"><div class="border rounded p-2"><small class="text-muted d-block">Sisa</small><strong class="lt-r-sisa"></strong></div></div>
          </div>

          <h6 class="mt-4 mb-2">Rincian tagihan</h6>
          <div class="table-responsive border rounded">
            <table class="table table-sm small mb-0 lt-modal-tabel">
              <thead>
                <tr><th>Struk</th><th>Rekening</th><th class="text-end">Jumlah (Rp)</th><th class="text-center">Status</th></tr>
              </thead>
              <tbody class="lt-r-tagihan"></tbody>
              <tfoot>
                <tr><td colspan="2" class="text-end">Total</td><td class="text-end text-nowrap lt-r-tagihan-sum"></td><td></td></tr>
              </tfoot>
            </table>
          </div>

          <h6 class="mt-4 mb-2">Pembayaran (KPP)</h6>
          <div class="table-responsive border rounded">
            <table class="table table-sm small mb-0 lt-modal-tabel">
              <thead>
                <tr><th>Tanggal</th><th>Rekening</th><th>Bank</th><th class="text-end">Jumlah (Rp)</th></tr>
              </thead>
              <tbody class="lt-r-bayar"></tbody>
              <tfoot>
                <tr><td colspan="3" class="text-end">Total</td><td class="text-end text-nowrap lt-r-bayar-sum"></td></tr>
              </tfoot>
            </table>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>
@endsection
