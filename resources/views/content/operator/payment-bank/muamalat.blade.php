@extends('layouts/layoutMaster')

@section('title', 'Payment Bank Muamalat')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset(mix('assets/vendor/libs/sweetalert2/sweetalert2.css')) }}">
@endsection

@section('vendor-script')
<script src="{{ asset(mix('assets/vendor/libs/sweetalert2/sweetalert2.js')) }}"></script>
@endsection

@section('page-style')
<style>
  .min-w-0 { min-width: 0 !important; }
  .pm-dl { display: grid; grid-template-columns: max-content 1fr; gap: .45rem 1rem; margin: 0; font-size: .8125rem; }
  .pm-dl dt { font-weight: 400; color: var(--bs-secondary-color); }
  .pm-dl dd { margin: 0; font-weight: 500; overflow-wrap: anywhere; }
  .pm-bill { font-size: 1.5rem; font-weight: 600; line-height: 1.2; }
  .pm-kosong { padding: 2.5rem 1rem; text-align: center; color: var(--bs-secondary-color); }
  .pm-kosong i { font-size: 2rem; display: block; margin-bottom: .4rem; opacity: .5; }
  .pm-raw { font-size: .75rem; max-height: 220px; overflow: auto; white-space: pre-wrap; word-break: break-all; }
</style>
@endsection

@section('page-script')
<script>
  $(function () {
    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'), Accept: 'application/json' } });

    var url = {
      inquiry: @json(route('operator.payment-bank.muamalat.inquiry')),
      payment: @json(route('operator.payment-bank.muamalat.payment')),
      reversal: @json(route('operator.payment-bank.muamalat.reversal')),
    };
    var inq = null, pay = null;
    var esc = function (v) { return $('<div>').text(v == null || v === '' ? '—' : String(v)).html(); };
    // BILL dari bank memuat 2 digit sen: 51500000 => Rp 515.000.
    var rupiah = function (bill) { return 'Rp ' + (Number(bill || 0) / 100).toLocaleString('id-ID'); };
    var pesanGalat = function (xhr) {
      var j = xhr.responseJSON || {};
      if (j.errors) return Object.values(j.errors)[0][0];
      return j.message || 'Terjadi kesalahan.';
    };
    var galat = function (xhr) { Swal.fire({ icon: 'error', title: 'Gagal', text: pesanGalat(xhr) }); };
    var badgeErr = function (d) {
      return '<span class="badge ' + (d.sukses ? 'bg-label-success' : 'bg-label-danger') + '">ERR ' + esc(d.ERR) + ' · ' + esc(d.pesan) + '</span>';
    };
    var tombolMuat = function ($b, on) { $b.prop('disabled', on).find('.spinner-border').toggleClass('d-none', !on); };
    var konfirmasi = function (opsi) {
      return Swal.fire($.extend({
        icon: 'warning', showCancelButton: true, cancelButtonText: 'Batal',
        customClass: { confirmButton: 'btn btn-primary me-2', cancelButton: 'btn btn-label-secondary' }, buttonsStyling: false,
      }, opsi));
    };

    var renderInquiry = function (d) {
      inq = d;
      $('#pm-inq-kosong').addClass('d-none');
      $('#pm-inq-isi').removeClass('d-none');
      $('#pm-inq-err').html(badgeErr(d));
      $('#pm-inq-bill').text(d.BILL ? rupiah(d.BILL) : '—');
      $('#pm-inq-dl').html([
        ['VA', $('#vano').val()], ['METHOD', d.METHOD], ['CCY', d.CCY], ['CUSTNAME', d.CUSTNAME],
        ['DESCRIPTION', d.DESCRIPTION], ['DESCRIPTION 2', d.DESCRIPTION2], ['BILL', d.BILL],
      ].map(function (r) { return '<dt>' + esc(r[0]) + '</dt><dd>' + esc(r[1]) + '</dd>'; }).join(''));
      $('#pm-inq-raw').text(JSON.stringify(d, null, 2));
      $('#btn-payment').prop('disabled', !(d.sukses && d.BILL && d.CUSTNAME));
    };

    var renderPayment = function (d) {
      pay = d;
      $('#pm-pay-card').removeClass('d-none');
      $('#pm-info').addClass('d-none');
      $('#pm-pay-err').html(badgeErr(d));
      $('#pm-pay-dl').html([
        ['TRXDATE', d.TRXDATE], ['METHOD', d.METHOD], ['CUSTNAME', d.CUSTNAME], ['BILL', d.BILL ? d.BILL + ' (' + rupiah(d.BILL) + ')' : ''],
        ['DESCRIPTION', d.DESCRIPTION], ['DESCRIPTION 2', d.DESCRIPTION2],
      ].map(function (r) { return '<dt>' + esc(r[0]) + '</dt><dd>' + esc(r[1]) + '</dd>'; }).join(''));
      $('#pm-pay-raw').text(JSON.stringify(d, null, 2));
      $('#btn-reversal').prop('disabled', !d.sukses);
    };

    $('#form-inquiry').on('submit', function (e) {
      e.preventDefault();
      var $b = $('#btn-inquiry');
      tombolMuat($b, true);
      $('#btn-payment').prop('disabled', true);
      $('#pm-pay-card').addClass('d-none');
      $('#pm-info').removeClass('d-none');
      $.post(url.inquiry, { vano: $('#vano').val().trim() })
        .done(renderInquiry)
        .fail(galat)
        .always(function () { tombolMuat($b, false); });
    });

    $('#btn-payment').on('click', function () {
      if (!inq) return;
      var $b = $(this);
      konfirmasi({
        title: 'Proses pelunasan?',
        html: 'Tagihan VA <b>' + esc($('#vano').val()) + '</b> a.n. <b>' + esc(inq.CUSTNAME) + '</b> sebesar <b>' + rupiah(inq.BILL) +
          '</b> akan dicatat <b>lunas</b> melalui webservice Bank Muamalat.',
        confirmButtonText: 'Ya, lunasi',
      }).then(function (r) {
        if (!r.isConfirmed) return;
        tombolMuat($b, true);
        $.post(url.payment, { vano: $('#vano').val().trim(), bill: inq.BILL, name: inq.CUSTNAME })
          .done(function (d) {
            renderPayment(d);
            Swal.fire(d.sukses
              ? { icon: 'success', title: 'Pembayaran sukses', text: 'Pembayaran berhasil dilakukan.' }
              : { icon: 'error', title: 'Pembayaran ditolak', text: 'ERR ' + (d.ERR || '-') + ' · ' + d.pesan });
            $b.prop('disabled', d.sukses);
          })
          .fail(galat)
          .always(function () { $b.find('.spinner-border').addClass('d-none'); if (!pay || !pay.sukses) $b.prop('disabled', false); });
      });
    });

    $('#btn-reversal').on('click', function () {
      if (!pay) return;
      var $b = $(this);
      konfirmasi({
        title: 'Batalkan pembayaran?',
        html: 'Pembayaran TRXDATE <b>' + esc(pay.TRXDATE) + '</b> sebesar <b>' + rupiah(pay.BILL) + '</b> akan di-<b>reversal</b>.',
        confirmButtonText: 'Ya, reversal',
        customClass: { confirmButton: 'btn btn-danger me-2', cancelButton: 'btn btn-label-secondary' },
      }).then(function (r) {
        if (!r.isConfirmed) return;
        tombolMuat($b, true);
        $.post(url.reversal, { vano: $('#vano').val().trim(), bill: pay.BILL, name: pay.CUSTNAME, pymtdate: pay.TRXDATE })
          .done(function (d) {
            Swal.fire(d.sukses
              ? { icon: 'success', title: 'Reversal sukses', text: 'Pembayaran berhasil dibatalkan.' }
              : { icon: 'error', title: 'Reversal ditolak', text: 'ERR ' + (d.ERR || '-') + ' · ' + d.pesan });
            if (d.sukses) { pay.sukses = false; $('#pm-pay-err').html(badgeErr(d)); $('#btn-payment').prop('disabled', true); }
          })
          .fail(galat)
          .always(function () { $b.find('.spinner-border').addClass('d-none'); $b.prop('disabled', !(pay && pay.sukses)); });
      });
    });

    $('#btn-selesai').on('click', function () {
      inq = pay = null;
      $('#pm-pay-card').addClass('d-none');
      $('#pm-info').removeClass('d-none');
      $('#pm-inq-isi').addClass('d-none');
      $('#pm-inq-kosong').removeClass('d-none');
      $('#btn-payment').prop('disabled', true);
      $('#vano').val('').trigger('focus');
    });
  });
</script>
@endsection

@section('content')
<div class="pg-header mb-4">
  <div class="pg-header-main">
    <div class="pg-header-icon">
      <i class="ti ti-building-bank"></i>
    </div>
    <div class="flex-grow-1 min-w-0">
      <div class="pg-header-crumb">
        <a href="{{ route('operator.dashboard') }}">Dashboard</a>
        <i class="ti ti-chevron-right"></i>
        <span>Payment Bank</span>
        <i class="ti ti-chevron-right"></i>
        <span>Bank Muamalat</span>
      </div>
      <h4 class="pg-header-title">Cek Tagihan &amp; Pembayaran Bank Muamalat</h4>
      <p class="pg-header-sub">Inquiry, pelunasan, dan reversal tagihan lewat webservice H2H Bank Muamalat.</p>
    </div>
  </div>
  <div class="pg-header-stats">
    <div class="pg-stat">
      <span class="pg-stat-icon is-info"><i class="ti ti-server"></i></span>
      <div class="min-w-0">
        <strong class="text-break">{{ $host ?: 'Belum diatur' }}</strong>
        <small>Host webservice</small>
      </div>
    </div>
  </div>
</div>

@if (!$host)
<div class="alert alert-warning">API_URL_BMI / API_KEY_BMI belum diatur di <code>.env</code>.</div>
@endif

<div class="row g-3">
  {{-- Inquiry --}}
  <div class="col-12 col-lg-6">
    <div class="card h-100">
      <div class="card-header pb-2">
        <h5 class="card-title mb-0">Inquiry tagihan</h5>
      </div>
      <div class="card-body">
        <form id="form-inquiry" autocomplete="off">
          <label class="form-label" for="vano">Nomor Virtual Account</label>
          <div class="input-group">
            <input type="text" id="vano" name="vano" class="form-control" inputmode="numeric" placeholder="mis. 72680100…" required autofocus>
            <button type="submit" id="btn-inquiry" class="btn btn-primary">
              <span class="spinner-border spinner-border-sm me-1 d-none" role="status"></span>
              <i class="ti ti-search ti-xs me-1"></i>Inquiry
            </button>
          </div>
        </form>

        <div id="pm-inq-kosong" class="pm-kosong">
          <i class="ti ti-receipt"></i>Masukkan nomor VA lalu tekan Inquiry.
        </div>

        <div id="pm-inq-isi" class="d-none mt-4">
          <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
            <div>
              <small class="text-muted d-block">Jumlah tagihan</small>
              <div class="pm-bill" id="pm-inq-bill"></div>
            </div>
            <div id="pm-inq-err"></div>
          </div>
          <dl class="pm-dl" id="pm-inq-dl"></dl>
          <details class="mt-3">
            <summary class="small text-muted">Balasan mentah</summary>
            <pre class="pm-raw bg-body-tertiary rounded p-2 mt-2 mb-0" id="pm-inq-raw"></pre>
          </details>
        </div>
      </div>
      <div class="card-footer border-top d-flex flex-wrap gap-2 justify-content-end py-3">
        <button type="button" id="btn-selesai" class="btn btn-label-secondary">
          <i class="ti ti-refresh ti-xs me-1"></i>Reset
        </button>
        <button type="button" id="btn-payment" class="btn btn-success" disabled>
          <span class="spinner-border spinner-border-sm me-1 d-none" role="status"></span>
          <i class="ti ti-cash ti-xs me-1"></i>Proses Pelunasan
        </button>
      </div>
    </div>
  </div>

  {{-- Hasil payment + reversal --}}
  <div class="col-12 col-lg-6">
    <div class="card h-100 d-none" id="pm-pay-card">
      <div class="card-header pb-2 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h5 class="card-title mb-0">Hasil pembayaran</h5>
        <div id="pm-pay-err"></div>
      </div>
      <div class="card-body">
        <dl class="pm-dl" id="pm-pay-dl"></dl>
        <details class="mt-3">
          <summary class="small text-muted">Balasan mentah</summary>
          <pre class="pm-raw bg-body-tertiary rounded p-2 mt-2 mb-0" id="pm-pay-raw"></pre>
        </details>
      </div>
      <div class="card-footer border-top d-flex justify-content-end py-3">
        <button type="button" id="btn-reversal" class="btn btn-danger" disabled>
          <span class="spinner-border spinner-border-sm me-1 d-none" role="status"></span>
          <i class="ti ti-arrow-back-up ti-xs me-1"></i>Proses Reversal
        </button>
      </div>
    </div>

    <div class="card h-100" id="pm-info">
      <div class="card-body small">
        <h6 class="mb-2">Kode ERR</h6>
        <dl class="pm-dl mb-3">
          @foreach ($kodeErr as $kode => $arti)
          <dt>{{ $kode }}</dt><dd>{{ $arti }}</dd>
          @endforeach
        </dl>
        <p class="text-muted mb-0">
          <i class="ti ti-alert-triangle ti-xs text-warning"></i>
          Pelunasan dan reversal benar-benar mengubah status tagihan mahasiswa di SITAMA. Setiap transaksi dicatat di log aplikasi.
        </p>
      </div>
    </div>
  </div>
</div>
@endsection
