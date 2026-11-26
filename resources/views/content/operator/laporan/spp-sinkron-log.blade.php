@extends('layouts/layoutMaster')

@section('title', 'Log Sync Jurnal SIMAKU')

@php
  $SUMBER = \App\Repositories\Laporan\SinkronJurnalLogRepository::SUMBER;
  $HASIL = \App\Repositories\Laporan\SinkronJurnalLogRepository::HASIL;
  $rp = fn ($n) => number_format((float) $n, 0, ',', '.');
  $tgl = fn ($v) => $v ? date('d-m-Y H:i:s', strtotime($v)) : null;
@endphp

@section('page-style')
<style>
  .min-w-0 { min-width: 0 !important; }
  .sl-filter { background: var(--bs-tertiary-bg, rgba(0,0,0,.015)); }
  .sl-filter .form-label { font-size: .75rem; margin-bottom: .25rem; }
  .sl-table td { vertical-align: middle; }
  .sl-table thead th { white-space: nowrap; }
  .sl-meta { font-size: .75rem; color: var(--bs-secondary-color); }
  .sl-empty { padding: 2.5rem 1rem; text-align: center; color: var(--bs-secondary-color); }
  .sl-empty i { font-size: 2rem; display: block; margin-bottom: .4rem; opacity: .5; }
  .sl-num { text-align: end; white-space: nowrap; font-variant-numeric: tabular-nums; }
</style>
@endsection

@section('page-script')
<script>
  $(function () {
    $('#filter-log select').on('change', function () { this.form.submit(); });

    var urlDetail = @json(route('operator.laporan.spp.sinkron-jurnal.log.detail', '__ID__'));
    var HASIL = @json($HASIL);
    var WARNA = { diubah: 'primary', sudah: 'success', belum_jurnal: 'warning', gagal: 'danger' };
    var esc = function (s) { return $('<div>').text(s == null ? '' : String(s)).html(); };
    var $md = $('#modalLogDetail');

    var muat = function () {
      var $body = $md.find('tbody').html('<tr><td colspan="6" class="sl-empty"><div class="spinner-border spinner-border-sm"></div></td></tr>');
      $.getJSON(urlDetail.replace('__ID__', $md.data('id')), { hasil: $md.find('.sl-hasil').val() || undefined }).done(function (rows) {
        if (!rows.length) { $body.html('<tr><td colspan="6" class="sl-empty">Tidak ada data.</td></tr>'); return; }
        $body.html($.map(rows, function (r, i) {
          return '<tr><td class="text-muted">' + (i + 1) + '</td>' +
            '<td>' + esc(r.npm) + '</td>' +
            '<td><span class="font-monospace">' + esc(r.bukti) + '</span></td>' +
            '<td><span class="badge bg-label-' + WARNA[r.hasil] + '">' + esc(HASIL[r.hasil] || r.hasil) + '</span></td>' +
            '<td class="text-nowrap">' + esc(r.id_uk_lama || '-') + (r.baris != null ? ' <span class="sl-meta">(' + esc(r.baris) + ' baris)</span>' : '') + '</td>' +
            '<td class="text-danger small">' + esc(r.pesan) + '</td></tr>';
        }).join(''));
      }).fail(function () {
        $body.html('<tr><td colspan="6" class="sl-empty text-danger">Detail gagal dimuat.</td></tr>');
      });
    };

    $('.btn-log-detail').on('click', function () {
      $md.data('id', $(this).data('id'));
      $md.find('.sl-judul').text($(this).data('judul'));
      $md.find('.sl-hasil').val('');
      bootstrap.Modal.getOrCreateInstance($md[0]).show();
      muat();
    });
    $md.find('.sl-hasil').on('change', muat);
  });
</script>
@endsection

@section('content')
<div class="pg-header mb-4">
  <div class="pg-header-main">
    <div class="pg-header-icon">
      <i class="ti ti-history"></i>
    </div>
    <div class="flex-grow-1 min-w-0">
      <div class="pg-header-crumb">
        <a href="{{ route('operator.dashboard') }}">Dashboard</a>
        <i class="ti ti-chevron-right"></i>
        <a href="{{ route('operator.laporan.spp.index') }}">Laporan SPP</a>
        <i class="ti ti-chevron-right"></i>
        <span>Log sync jurnal</span>
      </div>
      <h4 class="pg-header-title">Log Sync Jurnal SIMAKU</h4>
      <p class="pg-header-sub">Riwayat sinkron unit jurnal (id_uk / kodeunit) rincian tagihan SPP dari web, command, dan per rincian.</p>
    </div>
  </div>
</div>

<div class="card">
  <div class="sl-filter px-3 py-3 rounded-top border-bottom">
    <form id="filter-log" method="GET" action="{{ route('operator.laporan.spp.sinkron-jurnal.log') }}" class="row g-2 align-items-end" autocomplete="off">
      <div class="col-12 col-sm-4 col-lg-3">
        <label class="form-label" for="id_smt">Tahun Akademik</label>
        <select id="id_smt" name="id_smt" class="form-select">
          <option value="">Semua</option>
          @foreach ($semester as $id => $nama)
          <option value="{{ $id }}" @selected($id == ($filter['id_smt'] ?? ''))>{{ $nama }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-12 col-sm-5 col-lg-4">
        <label class="form-label" for="id_sms">Program Studi</label>
        <select id="id_sms" name="id_sms" class="form-select">
          <option value="">Semua prodi</option>
          @foreach ($prodi as $p)
          <option value="{{ $p->id_sms }}" @selected(($filter['id_sms'] ?? '') === $p->id_sms)>{{ trim($p->jenjang_didik) }} {{ trim($p->nm_lemb) }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-8 col-sm-3 col-lg-2">
        <label class="form-label" for="sumber">Sumber</label>
        <select id="sumber" name="sumber" class="form-select">
          <option value="">Semua</option>
          @foreach ($SUMBER as $k => $v)
          <option value="{{ $k }}" @selected(($filter['sumber'] ?? '') === $k)>{{ $v }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-4 col-lg-auto ms-lg-auto">
        <a href="{{ route('operator.laporan.spp.sinkron-jurnal.log') }}" class="btn btn-label-secondary w-100" title="Reset filter">
          <i class="ti ti-refresh ti-xs"></i><span class="d-none d-lg-inline ms-1">Reset</span>
        </a>
      </div>
    </form>
  </div>

  <div class="table-responsive">
    <table class="table table-sm table-hover small mb-0 sl-table">
      <thead>
        <tr>
          <th>#</th>
          <th>Waktu</th>
          <th>Prodi / Semester</th>
          <th>Unit tujuan</th>
          <th class="sl-num">Diproses</th>
          <th class="sl-num">Diubah</th>
          <th class="sl-num">Sudah</th>
          <th class="sl-num">Belum</th>
          <th class="sl-num">Gagal</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @forelse ($data as $l)
        @php
          $f = json_decode((string) $l->filter, true) ?: [];
          $rentang = !empty($f['dari']) ? date('d-m-Y', strtotime($f['dari'])) . ' s.d. ' . date('d-m-Y', strtotime($f['sampai'] ?? $f['dari'])) : null;
        @endphp
        <tr>
          <td class="text-muted">{{ $l->id }}</td>
          <td class="text-nowrap">
            {{ $tgl($l->mulai_at) }}
            <div class="sl-meta">{{ $SUMBER[$l->sumber] ?? $l->sumber }} · {{ $l->user_nama ?: '-' }}</div>
          </td>
          <td>
            {{ $l->prodi ?: '-' }}
            <div class="sl-meta">
              {{ $l->id_smt ? ($semester[$l->id_smt] ?? $l->id_smt) : '' }}
              @if ($rentang) · Lunas {{ $rentang }} @endif
              @if (!empty($f['ma'])) · MA {{ $f['ma'] }} @endif
              @if (!empty($f['npm'])) · NPM {{ $f['npm'] }} @endif
            </div>
          </td>
          <td>
            {{ $l->unit_kode }} {{ $l->unit_nama }}
            <div class="sl-meta">id_uk {{ $l->id_uk }}</div>
          </td>
          <td class="sl-num">{{ $rp($l->diproses) }} / {{ $rp($l->total) }}</td>
          <td class="sl-num text-primary">{{ $rp($l->diubah) }}</td>
          <td class="sl-num text-success">{{ $rp($l->sudah) }}</td>
          <td class="sl-num text-warning">{{ $rp($l->belum_jurnal) }}</td>
          <td class="sl-num text-danger">{{ $rp($l->gagal) }}</td>
          <td class="text-nowrap">
            @if (!$l->selesai_at)
            <span class="badge bg-label-secondary">Belum selesai</span>
            @elseif ($l->gagal)
            <span class="badge bg-label-warning">Selesai, ada gagal</span>
            @else
            <span class="badge bg-label-success">Selesai</span>
            @endif
            @if ($l->dry_run) <span class="badge bg-label-info">Dry run</span> @endif
            @if ($l->force && $l->sumber !== 'manual') <span class="badge bg-label-dark">Force</span> @endif
          </td>
          <td class="text-end">
            <button type="button" class="btn btn-sm btn-icon btn-label-primary btn-log-detail" title="Detail per bukti"
              data-id="{{ $l->id }}" data-judul="#{{ $l->id }} · {{ $l->prodi }} · {{ $tgl($l->mulai_at) }}">
              <i class="ti ti-list-details ti-xs"></i>
            </button>
          </td>
        </tr>
        @empty
        <tr><td colspan="11" class="sl-empty"><i class="ti ti-history"></i>Belum ada log sinkron.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  @if ($data->hasPages())
  <div class="card-footer d-flex justify-content-end">
    {{ $data->onEachSide(1)->links('pagination::bootstrap-5') }}
  </div>
  @endif
</div>

{{-- Modal detail log per bukti --}}
<div class="modal fade" id="modalLogDetail" tabindex="-1" aria-labelledby="modalLogDetailLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <div class="min-w-0">
          <h5 class="modal-title" id="modalLogDetailLabel">Detail log sync</h5>
          <div class="sl-meta sl-judul"></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <div class="d-flex justify-content-end mb-2">
          <select class="form-select form-select-sm w-auto sl-hasil" aria-label="Filter hasil">
            <option value="">Semua hasil</option>
            @foreach ($HASIL as $k => $v)
            <option value="{{ $k }}">{{ $v }}</option>
            @endforeach
          </select>
        </div>
        <div class="table-responsive">
          <table class="table table-sm small mb-0">
            <thead><tr><th>#</th><th>NPM</th><th>Bukti</th><th>Hasil</th><th>id_uk lama</th><th>Pesan</th></tr></thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>
@endsection
