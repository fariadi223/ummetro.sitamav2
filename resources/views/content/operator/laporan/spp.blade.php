@extends('layouts/layoutMaster')

@section('title', 'Laporan SPP')

@php
  $STATUS = \App\Repositories\Laporan\LaporanSppRepository::STATUS;
  $status = (string) ($filter['status'] ?? '');
  $persen = $ringkasan['total'] ? round($ringkasan['lunas'] / $ringkasan['total'] * 100, 1) : 0;
  $urlStatus = fn ($s) => route('operator.laporan.spp.index', array_filter(
    ['status' => $s] + \Illuminate\Support\Arr::except($filter, ['status']),
    fn ($v) => $v !== null && $v !== ''
  ));
  $rp = fn ($n) => number_format((float) $n, 0, ',', '.');
  // Sync jurnal SIMAKU butuh prodi (penentu unit) dan rentang tanggal lunas.
  $bisaSinkron = !empty($filter['id_sms']) && !empty($filter['dari']) && !empty($filter['sampai']);
  $filterSinkron = \Illuminate\Support\Arr::only($filter, ['id_smt', 'id_sms', 'angkatan', 'ma', 'npm', 'dari', 'sampai']);
@endphp

@section('vendor-style')
<link rel="stylesheet" href="{{ asset(mix('assets/vendor/libs/select2/select2.css')) }}">
<link rel="stylesheet" href="{{ asset(mix('assets/vendor/libs/bootstrap-datepicker/bootstrap-datepicker.css')) }}">
@endsection

@section('vendor-script')
<script src="{{ asset(mix('assets/vendor/libs/select2/select2.js')) }}"></script>
<script src="{{ asset(mix('assets/vendor/libs/bootstrap-datepicker/bootstrap-datepicker.js')) }}"></script>
@endsection

@section('page-style')
<style>
  .min-w-0 { min-width: 0 !important; }
  :root { --ls-head-h: 3.75rem; }

  /* Ringkasan lunas */
  .ls-progress { height: .5rem; border-radius: 50rem; }
  .ls-sum { font-size: .75rem; color: var(--bs-secondary-color); }
  .ls-sum strong { font-size: .95rem; color: var(--bs-heading-color, var(--bs-body-color)); display: block; }

  /* Filter */
  .ls-filter { background: var(--bs-tertiary-bg, rgba(0,0,0,.015)); }
  .ls-filter .form-label { font-size: .75rem; margin-bottom: .25rem; }

  /* Tab status */
  .ls-tabs-header { padding: 0 1rem; border-bottom: 1px solid var(--bs-border-color); }
  .ls-tabs {
    flex-wrap: nowrap; overflow-x: auto; overflow-y: hidden;
    border-bottom: 0; margin-bottom: -1px; scrollbar-width: none; gap: .25rem;
  }
  .ls-tabs::-webkit-scrollbar { display: none; }
  .ls-tabs .nav-link {
    display: inline-flex; align-items: center; gap: .4rem;
    min-height: var(--ls-head-h); padding: 0 .9rem;
    font-size: .8125rem; font-weight: 500; white-space: nowrap;
    border: 0; border-bottom: 2px solid transparent; border-radius: 0;
    background: transparent; color: var(--bs-secondary-color);
  }
  .ls-tabs .nav-link i { font-size: 1.05rem; }
  .ls-tabs .nav-link:hover { color: var(--bs-primary); }
  .ls-tabs .nav-link.active { color: var(--bs-primary); border-bottom-color: var(--bs-primary); box-shadow: none; }
  .ls-tabs .badge { font-size: .65rem; line-height: 1; padding: .3em .55em; }

  /* Tabel */
  .ls-scroll { max-height: max(340px, calc(100vh - 430px)); overflow-y: auto; overflow-x: hidden; }
  .ls-scroll thead th {
    position: sticky; top: 0; z-index: 2;
    background: var(--bs-card-bg, var(--bs-body-bg));
    box-shadow: inset 0 -1px 0 var(--bs-border-color);
    white-space: nowrap;
  }
  .ls-table td { vertical-align: middle; }
  .ls-table .ls-wrap { overflow-wrap: anywhere; word-break: break-word; }
  .ls-meta { font-size: .75rem; color: var(--bs-secondary-color); }
  .ls-empty { padding: 2.5rem 1rem; text-align: center; color: var(--bs-secondary-color); }
  .ls-empty i { font-size: 2rem; display: block; margin-bottom: .4rem; opacity: .5; }
  .ls-aksi { width: 1%; white-space: nowrap; }
  .ls-aksi .btn { width: 1.9rem; height: 1.9rem; padding: 0; }

  /* Modal detail struk */
  .ls-dl { display: grid; grid-template-columns: max-content 1fr; gap: .35rem 1rem; margin: 0; font-size: .8125rem; }
  .ls-dl dt { font-weight: 400; color: var(--bs-secondary-color); }
  .ls-dl dd { margin: 0; font-weight: 500; overflow-wrap: anywhere; }

  @media (max-width: 575.98px) {
    .ls-scroll { max-height: none; }
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
    $('#filter-spp select').on('change', function () { this.form.submit(); });
    // Rentang tanggal lunas (bootstrap-datepicker). Tampilan dd-mm-yyyy,
    // nilai terkirim yyyy-mm-dd lewat input hidden. Form dikirim saat
    // kedua tanggal terisi, atau keduanya dikosongkan.
    var ymd = function (d) {
      if (!d) return '';
      var p = function (n) { return (n < 10 ? '0' : '') + n; };
      return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate());
    };
    var $rentang = $('#rentang-lunas');
    $rentang.datepicker({
      format: 'dd-mm-yyyy', autoclose: true, todayHighlight: true, clearBtn: true,
      orientation: 'bottom auto',
    });
    $rentang.on('changeDate clearDate', function () {
      var dari = ymd($('#dari-view').datepicker('getDate'));
      var sampai = ymd($('#sampai-view').datepicker('getDate'));
      var $form = $('#filter-spp');
      $form.find('[name=dari]').val(dari);
      $form.find('[name=sampai]').val(sampai);
      if (!!dari === !!sampai) $form.trigger('submit');
    });
    // Detail satu record KPP (kpp_dtl) di modal.
    var urlDetail = @json(route('operator.laporan.spp.detail', '__ID__'));
    var urlSinkron = @json(route('operator.laporan.spp.sinkron-unit', '__ID__'));
    var csrf = $('meta[name="csrf-token"]').attr('content');
    var $modal = $('#modalDetailSpp');
    var $unit = $('#ls-unit-pilih');
    var rp = function (n) { return Number(n || 0).toLocaleString('id-ID'); };
    var esc = function (v) { return $('<div>').text(v == null || v === '' ? '—' : String(v)).html(); };
    var permintaan = null, kppAktif = null;

    $unit.select2({ width: '100%', dropdownParent: $modal, placeholder: 'Pilih unit SIMAKU' });

    var pesanUnit = function (jenis, teks) {
      $modal.find('.ls-unit-pesan').attr('class', 'ls-unit-pesan small mt-2 text-' + jenis).toggleClass('d-none', !teks).text(teks || '');
    };

    var render = function (d) {
      var info = [
        ['kpp_dtl', d.kpp_dtl], ['NPM', d.npm], ['Nama', d.nama], ['Program studi', d.prodi],
        ['Angkatan', d.angkatan],
        ['Tahun akademik', (d.semester || '—') + (d.smt_mhs ? ' · semester ' + d.smt_mhs : '')],
        ['Rekening', d.keterangan], ['MA', d.ma], ['Jumlah', 'Rp ' + rp(d.uang)],
        ['Angsuran ke', d.angsur_ke], ['Tanggal KPP', d.tanggal], ['Bank', d.bank],
        ['No. struk', d.struk_id], ['Tanggal lunas', d.tgl_lunas],
      ];
      $modal.find('.ls-dl').html($.map(info, function (r) {
        return '<dt>' + esc(r[0]) + '</dt><dd>' + esc(r[1]) + '</dd>';
      }).join(''));

      // Status jurnal SPM di SIMAKU (GET journal/spm).
      var j = d.jurnal || {}, badge = {
        terjurnal: ['bg-label-success', 'ti-circle-check', 'Sudah terjurnal'],
        belum: ['bg-label-warning', 'ti-clock', 'Belum terjurnal'],
        selisih: ['bg-label-danger', 'ti-alert-triangle', 'Nominal jurnal berbeda'],
      }[j.status] || ['bg-label-secondary', 'ti-help', 'Tidak dapat dicek'];
      $modal.find('.ls-jurnal-status').html('<span class="badge ' + badge[0] + '"><i class="ti ' + badge[1] +
        ' ti-xs me-1"></i>' + badge[2] + '</span>' + (j.bukti ? ' <small class="text-muted ms-1">bukti ' + esc(j.bukti) + '</small>' : ''));
      $modal.find('.ls-jurnal-pesan').toggleClass('d-none', !j.pesan).text(j.pesan || '');
      var baris = j.baris || [];
      $modal.find('.ls-jurnal-tabel').toggleClass('d-none', !baris.length);
      $modal.find('.ls-jurnal-baris').html($.map(baris, function (r) {
        var rek = r.rek_nama
          ? esc(r.rek_nama) + '<small class="d-block text-muted">' + esc(r.rek_kode) + ' · id ' + esc(r.idrek6) + '</small>'
          : esc(r.idrek6);
        var unit = r.id_uk
          ? esc(r.unit_nama || 'id ' + r.id_uk) + '<small class="d-block text-muted">id_uk ' + esc(r.id_uk) + ' · kodeunit ' + esc(r.kodeunit) + '</small>'
          : '<span class="badge bg-label-secondary">Belum diisi</span>';
        return '<tr><td>' + esc(r.id_trans) + '</td><td class="ls-wrap">' + rek + '</td><td class="ls-wrap">' + unit + '</td>' +
          '<td class="text-nowrap">' + esc(r.tgl) + '</td>' +
          '<td class="text-end text-nowrap">' + (r.debet ? rp(r.debet) : '—') + '</td>' +
          '<td class="text-end text-nowrap">' + (r.kredit ? rp(r.kredit) : '—') + '</td></tr>';
      }).join(''));

      // Sinkron unit: hanya bila jurnal ada. Pilihan awal = unit jurnal saat ini, atau saran sesuai nama prodi.
      var u = d.unit;
      $modal.find('.ls-unit').toggleClass('d-none', !u);
      pesanUnit('muted', '');
      if (u) {
        var saran = u.saran ? u.saran.id_unit : null;
        var sekarang = baris.length && baris[0].id_uk ? baris[0].id_uk : null;
        $unit.empty().append(new Option('', '', false, false)).append($.map(u.pilihan || [], function (p) {
          return new Option(p.kode + ' — ' + p.nama + (p.id_unit === saran ? '  (sesuai prodi)' : ''), p.id_unit, false, false);
        })).val(saran || sekarang || '').trigger('change.select2');
        $modal.find('.ls-unit-saran').html(u.saran
          ? 'Sesuai prodi <strong>' + esc(d.prodi) + '</strong>: ' + esc(u.saran.kode + ' — ' + u.saran.nama)
          : 'Tidak ada unit yang cocok dengan prodi <strong>' + esc(d.prodi) + '</strong>; pilih manual.');
        var semuaSesuai = saran && baris.length && baris.every(function (r) { return r.id_uk === saran; });
        if (u.galat) pesanUnit('danger', u.galat);
        else if (semuaSesuai) pesanUnit('success', 'Unit jurnal sudah sesuai prodi.');
      }
    };

    $(document).on('click', '[data-detail-spp]', function () {
      kppAktif = $(this).data('detail-spp');
      $modal.find('.ls-detail-isi, .ls-detail-galat').addClass('d-none');
      $modal.find('.ls-detail-muat').removeClass('d-none');
      bootstrap.Modal.getOrCreateInstance($modal[0]).show();

      if (permintaan) permintaan.abort();
      permintaan = $.getJSON(urlDetail.replace('__ID__', kppAktif))
        .done(function (d) {
          render(d);
          $modal.find('.ls-detail-muat').addClass('d-none');
          $modal.find('.ls-detail-isi').removeClass('d-none');
        })
        .fail(function (xhr, status) {
          if (status === 'abort') return;
          $modal.find('.ls-detail-muat').addClass('d-none');
          $modal.find('.ls-detail-galat').removeClass('d-none')
            .text(xhr.status === 404 ? 'Rincian KPP tidak ditemukan.' : 'Detail gagal dimuat. Coba lagi.');
        });
    });

    $('#ls-unit-sinkron').on('click', function () {
      var idUk = $unit.val(), $btn = $(this);
      if (!idUk) { pesanUnit('danger', 'Pilih unit dulu.'); return; }

      $btn.prop('disabled', true).find('.spinner-border').removeClass('d-none');
      $.ajax({
        url: urlSinkron.replace('__ID__', kppAktif), method: 'PUT', dataType: 'json',
        headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
        data: { id_uk: idUk },
      }).done(function (d) {
        render(d);
        pesanUnit('success', d.message || 'Unit jurnal diperbarui.');
      }).fail(function (xhr) {
        pesanUnit('danger', (xhr.responseJSON && xhr.responseJSON.message) || 'Sinkron unit gagal. Coba lagi.');
      }).always(function () {
        $btn.prop('disabled', false).find('.spinner-border').addClass('d-none');
      });
    });

    // Sync jurnal SIMAKU massal: rencana dulu, lalu diproses per batch.
    var urlRencana = @json(route('operator.laporan.spp.sinkron-jurnal.rencana'));
    var urlSinkronJurnal = @json(route('operator.laporan.spp.sinkron-jurnal'));
    var filterSinkron = @json((object) $filterSinkron);
    var $ms = $('#modalSinkronJurnal'), berjalan = false, batal = false;
    var pesanMs = function (jenis, teks) {
      $ms.find('.sj-pesan').attr('class', 'sj-pesan alert alert-' + jenis + ' small mb-0 mt-3').toggleClass('d-none', !teks).text(teks || '');
    };
    var galatXhr = function (xhr, cadangan) {
      var j = xhr.responseJSON || {};
      return j.errors ? $.map(j.errors, function (v) { return v; }).join(' ') : (j.message || cadangan);
    };

    $('#btn-sinkron-jurnal').on('click', function () {
      $ms.find('.sj-rencana, .sj-progres').addClass('d-none');
      $ms.find('.sj-muat').removeClass('d-none');
      $ms.find('.sj-galat').empty().addClass('d-none');
      $('#sj-mulai').prop('disabled', true).removeClass('d-none');
      pesanMs('info', '');
      bootstrap.Modal.getOrCreateInstance($ms[0]).show();

      $.getJSON(urlRencana, filterSinkron).done(function (d) {
        $ms.find('.sj-info').html(
          '<dt>Tahun akademik</dt><dd>' + esc(d.semester) + '</dd>' +
          '<dt>Program studi</dt><dd>' + esc(d.prodi) + '</dd>' +
          '<dt>Rentang</dt><dd>' + esc(d.rentang) + '</dd>' +
          '<dt>Rincian lunas</dt><dd>' + rp(d.total) + '</dd>' +
          '<dt>Unit SIMAKU</dt><dd>' + esc(d.unit.kode + ' — ' + d.unit.nama) + ' <small class="text-muted">(id_uk ' + esc(d.unit.id_unit) + ')</small></dd>');
        $ms.data('total', d.total);
        $('#sj-mulai').prop('disabled', !d.total);
        if (!d.total) pesanMs('warning', 'Tidak ada rincian lunas untuk filter ini.');
        $ms.find('.sj-rencana').removeClass('d-none');
      }).fail(function (xhr) {
        pesanMs('danger', galatXhr(xhr, 'Rencana sinkron gagal dimuat.'));
      }).always(function () {
        $ms.find('.sj-muat').addClass('d-none');
      });
    });

    $('#sj-mulai').on('click', function () {
      var total = $ms.data('total') || 0;
      var jumlah = { diubah: 0, sudah: 0, belum_jurnal: 0, gagal: 0 };
      var logId = null;
      var $bar = $ms.find('.sj-bar');
      var tampil = function (offset) {
        var persen = total ? Math.round(offset / total * 100) : 100;
        $bar.css('width', persen + '%').text(persen + '%');
        $ms.find('.sj-hitung').text(rp(offset) + ' / ' + rp(total) + ' rincian');
        $.each(jumlah, function (k, v) { $ms.find('[data-sj="' + k + '"]').text(rp(v)); });
      };
      var selesai = function (jenis, teks) {
        berjalan = false;
        $bar.removeClass('progress-bar-animated progress-bar-striped');
        $ms.find('[data-bs-dismiss]').prop('disabled', false);
        $('#sj-henti').addClass('d-none');
        pesanMs(jenis, teks);
      };
      var langkah = function (offset) {
        if (batal) { selesai('warning', 'Dihentikan pada rincian ke-' + rp(offset) + '.'); return; }
        $.ajax({
          url: urlSinkronJurnal, method: 'POST', dataType: 'json',
          headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
          data: $.extend({ offset: offset, log_id: logId }, filterSinkron),
        }).done(function (d) {
          total = d.total;
          logId = d.log_id;
          $.each(d.hitung, function (k, v) { jumlah[k] += v; });
          if (d.galat.length) {
            $ms.find('.sj-galat').removeClass('d-none').append($.map(d.galat, function (g) { return '<li>' + esc(g) + '</li>'; }).join(''));
          }
          tampil(d.offset);
          if (d.selesai) {
            selesai(jumlah.gagal ? 'warning' : 'success',
              'Selesai: ' + rp(jumlah.diubah) + ' diubah, ' + rp(jumlah.sudah) + ' sudah sesuai, ' +
              rp(jumlah.belum_jurnal) + ' belum terjurnal' + (jumlah.gagal ? ', ' + rp(jumlah.gagal) + ' gagal.' : '.'));
          } else {
            langkah(d.offset);
          }
        }).fail(function (xhr) {
          selesai('danger', galatXhr(xhr, 'Sinkron gagal.') + ' Berhenti pada rincian ke-' + rp(offset) + '.');
        });
      };

      berjalan = true; batal = false;
      $(this).addClass('d-none');
      $('#sj-henti').removeClass('d-none').prop('disabled', false);
      $ms.find('[data-bs-dismiss]').prop('disabled', true);
      $ms.find('.sj-progres').removeClass('d-none');
      $bar.addClass('progress-bar-animated progress-bar-striped');
      pesanMs('info', '');
      tampil(0);
      langkah(0);
    });

    $('#sj-henti').on('click', function () { batal = true; $(this).prop('disabled', true); });
    $ms.on('hide.bs.modal', function (e) { if (berjalan) e.preventDefault(); });

    $('#btn-export').on('click', function () {
      var q = $('#filter-spp').serialize();
      location.href = @json(route('operator.laporan.spp.export')) + (q ? '?' + q : '');
    });
  });
</script>
@endsection

@section('content')
<div class="pg-header mb-4">
  <div class="pg-header-main">
    <div class="pg-header-icon">
      <i class="ti ti-report-analytics"></i>
    </div>
    <div class="flex-grow-1 min-w-0">
      <div class="pg-header-crumb">
        <a href="{{ route('operator.dashboard') }}">Dashboard</a>
        <i class="ti ti-chevron-right"></i>
        <span>Laporan</span>
        <i class="ti ti-chevron-right"></i>
        <span>Laporan SPP</span>
      </div>
      <h4 class="pg-header-title">Laporan Rincian Tagihan SPP</h4>
      <p class="pg-header-sub">Rincian tagihan mahasiswa per tahun akademik, prodi, angkatan, dan rekening.</p>
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
      <span class="pg-stat-icon is-success"><i class="ti ti-circle-check"></i></span>
      <div>
        <strong>Rp {{ $rp($ringkasan['lunas']) }}</strong>
        <small>Sudah lunas</small>
      </div>
    </div>
    <div class="pg-stat">
      <span class="pg-stat-icon is-danger"><i class="ti ti-clock"></i></span>
      <div>
        <strong>Rp {{ $rp($ringkasan['belum']) }}</strong>
        <small>Belum lunas</small>
      </div>
    </div>
  </div>
</div>

{{-- Filter + ringkasan --}}
<div class="card mb-3">
  <div class="ls-filter px-3 py-3 rounded-top">
    <form id="filter-spp" method="GET" action="{{ route('operator.laporan.spp.index') }}" class="row g-2 align-items-end" autocomplete="off">
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
      <div class="col-12 col-sm-6 col-lg-4 col-xxl-3">
        <label class="form-label" for="dari-view">Tanggal lunas</label>
        <div class="input-group input-daterange" id="rentang-lunas">
          <input type="text" id="dari-view" class="form-control" placeholder="dd-mm-yyyy" readonly
            value="{{ !empty($filter['dari']) ? date('d-m-Y', strtotime($filter['dari'])) : '' }}">
          <span class="input-group-text">s.d.</span>
          <input type="text" id="sampai-view" class="form-control" placeholder="dd-mm-yyyy" readonly
            value="{{ !empty($filter['sampai']) ? date('d-m-Y', strtotime($filter['sampai'])) : '' }}">
        </div>
        <input type="hidden" name="dari" value="{{ $filter['dari'] ?? '' }}">
        <input type="hidden" name="sampai" value="{{ $filter['sampai'] ?? '' }}">
      </div>
      <div class="col-6 col-sm-6 col-lg-4 col-xxl-2">
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
      <div class="col-12 col-sm-9 col-lg-2 col-xxl-auto d-flex gap-2 justify-content-end">
        <a href="{{ route('operator.laporan.spp.index') }}" class="btn btn-label-secondary" title="Reset filter">
          <i class="ti ti-refresh ti-xs"></i><span class="d-none d-lg-inline ms-1">Reset</span>
        </a>
        <button type="button" id="btn-export" class="btn btn-success">
          <i class="ti ti-file-spreadsheet ti-xs me-1"></i>Excel
        </button>
        <span class="d-inline-block" tabindex="0" @unless ($bisaSinkron) data-bs-toggle="tooltip" title="Pilih program studi dan tanggal lunas (mulai s.d. akhir) dulu" @endunless>
          <button type="button" id="btn-sinkron-jurnal" class="btn btn-primary text-nowrap" @disabled(!$bisaSinkron)>
            <i class="ti ti-refresh ti-xs me-1"></i>Sync jurnal SIMAKU
          </button>
        </span>
      </div>
    </form>
  </div>

  <div class="card-body border-top py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-2">
      <div class="ls-sum">Total tagihan<strong>Rp {{ $rp($ringkasan['total']) }}</strong></div>
      <div class="ls-sum text-end">Persentase lunas<strong class="text-success">{{ str_replace('.', ',', $persen) }}%</strong></div>
    </div>
    <div class="progress ls-progress" role="progressbar" aria-label="Persentase lunas" aria-valuenow="{{ $persen }}" aria-valuemin="0" aria-valuemax="100">
      <div class="progress-bar bg-success" style="width: {{ $persen }}%"></div>
    </div>
  </div>
</div>

{{-- Tabel rincian --}}
<div class="card">
  <div class="card-header ls-tabs-header">
    <ul class="nav nav-tabs ls-tabs" role="tablist">
      <li class="nav-item">
        <a class="nav-link @if ($status === '') active @endif" href="{{ $urlStatus(null) }}">
          <i class="ti ti-list"></i><span>Semua</span>
          <span class="badge rounded-pill bg-label-primary">Rp {{ $rp($ringkasan['total']) }}</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link @if ($status === '0') active @endif" href="{{ $urlStatus('0') }}">
          <i class="ti ti-clock"></i><span>{{ $STATUS['0'] }}</span>
          <span class="badge rounded-pill bg-warning">Rp {{ $rp($ringkasan['belum']) }}</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link @if ($status === '1') active @endif" href="{{ $urlStatus('1') }}">
          <i class="ti ti-circle-check"></i><span>{{ $STATUS['1'] }}</span>
          <span class="badge rounded-pill bg-success">Rp {{ $rp($ringkasan['lunas']) }}</span>
        </a>
      </li>
    </ul>
  </div>

  <div class="ls-scroll">
    <table class="table table-sm table-hover small mb-0 ls-table">
      <thead>
        <tr>
          <th class="d-none d-sm-table-cell">#</th>
          <th>Mahasiswa</th>
          <th class="d-none d-lg-table-cell">Program Studi</th>
          <th>Rekening</th>
          <th class="text-end">Jumlah (Rp)</th>
          <th class="text-center">Status</th>
          <th class="d-none d-md-table-cell text-nowrap">Tgl Lunas</th>
          <th class="ls-aksi text-center">Aksi</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($data as $i => $row)
        @php($prodiNama = trim($row->jenjang_didik) . ' ' . trim($row->nm_lemb))
        <tr>
          <td class="d-none d-sm-table-cell text-muted">{{ $data->firstItem() + $i }}</td>
          <td class="ls-wrap">
            <span class="ls-meta d-block">{{ trim($row->nipd) }} · {{ substr(trim((string) $row->mulai_smt), 0, 4) }}</span>
            <span class="fw-medium">{{ trim((string) $row->nm_pd) }}</span>
            <small class="d-block d-lg-none text-muted">{{ $prodiNama }}</small>
          </td>
          <td class="d-none d-lg-table-cell ls-wrap">{{ $prodiNama }}</td>
          <td class="ls-wrap">{{ trim((string) $row->keterangan) ?: '—' }}</td>
          <td class="text-end text-nowrap fw-medium">{{ $rp($row->uang) }}</td>
          <td class="text-center">
            @if ((int) $row->lunas === 1)
            <span class="badge bg-label-success">Lunas</span>
            @else
            <span class="badge bg-label-warning">Belum</span>
            @endif
            <small class="d-block d-md-none text-muted mt-1">
              {{ $row->lunas_date ? date('d-m-Y', strtotime($row->lunas_date)) : '' }}
            </small>
          </td>
          <td class="d-none d-md-table-cell text-nowrap">
            {{ $row->lunas_date ? date('d-m-Y', strtotime($row->lunas_date)) : '—' }}
          </td>
          <td class="ls-aksi text-center">
            @if ($row->kpp_dtl)
            <button type="button" class="btn btn-icon btn-sm btn-text-primary rounded-pill"
              data-detail-spp="{{ $row->kpp_dtl }}" title="Detail KPP #{{ $row->kpp_dtl }}" aria-label="Detail KPP {{ trim($row->nipd) }}">
              <i class="ti ti-eye ti-xs"></i>
            </button>
            @else
            <button type="button" class="btn btn-icon btn-sm btn-text-secondary rounded-pill" disabled title="Belum ada KPP">
              <i class="ti ti-eye-off ti-xs"></i>
            </button>
            @endif
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="8" class="p-0">
            <div class="ls-empty"><i class="ti ti-report-off"></i>Tidak ada rincian tagihan yang cocok dengan filter.</div>
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

{{-- Modal detail satu record KPP (bak_kppmhs_dtl.kpp_dtl) --}}
<div class="modal fade" id="modalDetailSpp" tabindex="-1" aria-labelledby="modalDetailSppLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalDetailSppLabel">Detail KPP</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <div class="ls-detail-muat text-center text-muted py-5">
          <div class="spinner-border spinner-border-sm me-2" role="status"></div>Memuat detail…
        </div>
        <div class="ls-detail-galat alert alert-danger mb-0 d-none" role="alert"></div>
        <div class="ls-detail-isi d-none">
          <dl class="ls-dl"></dl>

          <div class="border-top mt-3 pt-3">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
              <h6 class="mb-0">Jurnal SIMAKU</h6>
              <div class="ls-jurnal-status"></div>
            </div>
            <div class="ls-jurnal-pesan small text-muted d-none"></div>
            <div class="ls-jurnal-tabel table-responsive border rounded d-none">
              <table class="table table-sm small mb-0">
                <thead>
                  <tr>
                    <th>id_trans</th>
                    <th>Rekening</th>
                    <th>Unit</th>
                    <th>Tanggal</th>
                    <th class="text-end">Debet</th>
                    <th class="text-end">Kredit</th>
                  </tr>
                </thead>
                <tbody class="ls-jurnal-baris"></tbody>
              </table>
            </div>

            <div class="ls-unit d-none mt-3">
              <label class="form-label small mb-1" for="ls-unit-pilih">Unit jurnal (id_uk / kodeunit)</label>
              <div class="d-flex flex-column flex-sm-row gap-2">
                <div class="flex-grow-1 min-w-0">
                  <select id="ls-unit-pilih" class="form-select"></select>
                </div>
                <button type="button" id="ls-unit-sinkron" class="btn btn-primary text-nowrap">
                  <span class="spinner-border spinner-border-sm me-1 d-none" role="status"></span>
                  <i class="ti ti-refresh ti-xs me-1"></i>Sinkron unit
                </button>
              </div>
              <div class="ls-unit-saran form-text"></div>
              <div class="ls-unit-pesan small mt-2 d-none"></div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

{{-- Modal sync jurnal SIMAKU massal (unit jurnal sesuai prodi) --}}
<div class="modal fade" id="modalSinkronJurnal" tabindex="-1" aria-labelledby="modalSinkronJurnalLabel" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalSinkronJurnalLabel">Sync jurnal SIMAKU</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <div class="sj-muat text-center text-muted py-4">
          <div class="spinner-border spinner-border-sm me-2" role="status"></div>Menghitung rincian…
        </div>
        <div class="sj-rencana d-none">
          <p class="small text-muted mb-2">Unit jurnal (id_uk / kodeunit) semua rincian lunas berikut akan disamakan dengan unit prodi di SIMAKU.</p>
          <dl class="ls-dl sj-info"></dl>
        </div>
        <div class="sj-progres d-none mt-3">
          <div class="d-flex justify-content-between small mb-1">
            <span>Progres</span><span class="sj-hitung text-muted"></span>
          </div>
          <div class="progress" style="height: 1rem;">
            <div class="progress-bar sj-bar" role="progressbar" style="width: 0%"></div>
          </div>
          <div class="row g-2 text-center small mt-2">
            <div class="col-3"><strong class="d-block text-primary" data-sj="diubah">0</strong>Diubah</div>
            <div class="col-3"><strong class="d-block text-success" data-sj="sudah">0</strong>Sudah sesuai</div>
            <div class="col-3"><strong class="d-block text-warning" data-sj="belum_jurnal">0</strong>Belum jurnal</div>
            <div class="col-3"><strong class="d-block text-danger" data-sj="gagal">0</strong>Gagal</div>
          </div>
          <ul class="sj-galat small text-danger mt-2 mb-0 ps-3 d-none" style="max-height: 10rem; overflow-y: auto;"></ul>
        </div>
        <div class="sj-pesan d-none"></div>
      </div>
      <div class="modal-footer">
        <a href="{{ route('operator.laporan.spp.sinkron-jurnal.log', \Illuminate\Support\Arr::only($filter, ['id_smt', 'id_sms'])) }}" class="btn btn-label-info me-auto" target="_blank">
          <i class="ti ti-history ti-xs me-1"></i>Riwayat
        </a>
        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Tutup</button>
        <button type="button" id="sj-henti" class="btn btn-label-danger d-none">Hentikan</button>
        <button type="button" id="sj-mulai" class="btn btn-primary" disabled>
          <i class="ti ti-player-play ti-xs me-1"></i>Mulai sync
        </button>
      </div>
    </div>
  </div>
</div>
@endsection
