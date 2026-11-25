@extends('layouts/layoutMaster')

@section('title', 'Saldo UKT Mahasiswa')

@php
  $JENIS = \App\Repositories\Saldo\SaldoUktRepository::JENIS;
  $JENIS_INPUT = \App\Repositories\Saldo\SaldoUktRepository::JENIS_INPUT;
  $STATUS = \App\Repositories\Saldo\SaldoUktRepository::STATUS;
  $status = (string) ($filter['status'] ?? '');
  $urlStatus = fn ($s) => route('operator.saldo-ukt.index', array_filter(
    ['status' => $s] + \Illuminate\Support\Arr::except($filter, ['status', 'page']),
    fn ($v) => $v !== null && $v !== ''
  ));
  $rp = fn ($n) => number_format((float) $n, 0, ',', '.');
  $persen = $ringkasan['saldo'] ? round($ringkasan['digunakan'] / $ringkasan['saldo'] * 100, 1) : 0;
@endphp

@section('vendor-style')
<link rel="stylesheet" href="{{ asset(mix('assets/vendor/libs/select2/select2.css')) }}">
<link rel="stylesheet" href="{{ asset(mix('assets/vendor/libs/sweetalert2/sweetalert2.css')) }}">
@endsection

@section('vendor-script')
<script src="{{ asset(mix('assets/vendor/libs/select2/select2.js')) }}"></script>
<script src="{{ asset(mix('assets/vendor/libs/sweetalert2/sweetalert2.js')) }}"></script>
@endsection

@section('page-style')
<style>
  .min-w-0 { min-width: 0 !important; }
  :root { --su-head-h: 3.75rem; }

  /* Form tambah saldo */
  .su-form .form-label { font-size: .75rem; margin-bottom: .25rem; }
  .su-npm .form-control { font-weight: 600; letter-spacing: .03em; }
  .su-jumlah .form-control { font-weight: 600; text-align: right; }
  .su-jenis .btn { flex: 1 1 0; white-space: nowrap; }
  .su-profil { border: 1px dashed var(--bs-border-color); border-radius: .5rem; padding: .75rem; }
  .su-avatar {
    width: 2.75rem; height: 2.75rem; flex: 0 0 2.75rem;
    display: grid; place-items: center; border-radius: 50%;
    font-weight: 700; letter-spacing: .02em;
  }
  .su-chip {
    display: inline-flex; align-items: center; gap: .3rem;
    padding: .2rem .6rem; border-radius: 50rem; font-size: .75rem;
    background: var(--bs-tertiary-bg, rgba(0,0,0,.04)); white-space: nowrap;
  }
  .su-posisi { display: grid; grid-template-columns: repeat(3, 1fr); gap: .5rem; text-align: center; }
  .su-posisi > div { border: 1px solid var(--bs-border-color); border-radius: .375rem; padding: .4rem .25rem; }
  .su-posisi small { display: block; font-size: .7rem; color: var(--bs-secondary-color); }
  .su-posisi strong { font-size: .8125rem; }

  /* Daftar */
  .su-filter { background: var(--bs-tertiary-bg, rgba(0,0,0,.015)); }
  .su-filter .form-label { font-size: .75rem; margin-bottom: .25rem; }
  .su-sum { font-size: .75rem; color: var(--bs-secondary-color); }
  .su-sum strong { font-size: .95rem; color: var(--bs-heading-color, var(--bs-body-color)); display: block; }
  .su-progress { height: .5rem; border-radius: 50rem; }

  .su-tabs-header { padding: 0 1rem; border-bottom: 1px solid var(--bs-border-color); }
  .su-tabs { flex-wrap: nowrap; overflow-x: auto; overflow-y: hidden; border-bottom: 0; margin-bottom: -1px; scrollbar-width: none; gap: .25rem; }
  .su-tabs::-webkit-scrollbar { display: none; }
  .su-tabs .nav-link {
    display: inline-flex; align-items: center; gap: .4rem;
    min-height: var(--su-head-h); padding: 0 .9rem;
    font-size: .8125rem; font-weight: 500; white-space: nowrap;
    border: 0; border-bottom: 2px solid transparent; border-radius: 0;
    background: transparent; color: var(--bs-secondary-color);
  }
  .su-tabs .nav-link i { font-size: 1.05rem; }
  .su-tabs .nav-link:hover { color: var(--bs-primary); }
  .su-tabs .nav-link.active { color: var(--bs-primary); border-bottom-color: var(--bs-primary); box-shadow: none; }
  .su-tabs .badge { font-size: .65rem; line-height: 1; padding: .3em .55em; }

  .su-scroll { max-height: max(340px, calc(100vh - 470px)); overflow-y: auto; overflow-x: hidden; }
  .su-scroll thead th {
    position: sticky; top: 0; z-index: 2;
    background: var(--bs-card-bg, var(--bs-body-bg));
    box-shadow: inset 0 -1px 0 var(--bs-border-color);
    white-space: nowrap;
  }
  .su-table td { vertical-align: middle; }
  .su-wrap { overflow-wrap: anywhere; word-break: break-word; }
  .su-meta { font-size: .75rem; color: var(--bs-secondary-color); }
  .su-empty { padding: 2.5rem 1rem; text-align: center; color: var(--bs-secondary-color); }
  .su-empty i { font-size: 2rem; display: block; margin-bottom: .4rem; opacity: .5; }
  .su-aksi { width: 1%; white-space: nowrap; }
  .su-aksi .btn { width: 1.9rem; height: 1.9rem; padding: 0; }
  .su-loading { opacity: .55; pointer-events: none; transition: opacity .15s; }

  /* Modal rincian */
  .su-dl { display: grid; grid-template-columns: max-content 1fr; gap: .35rem 1rem; margin: 0; font-size: .8125rem; }
  .su-dl dt { font-weight: 400; color: var(--bs-secondary-color); }
  .su-dl dd { margin: 0; font-weight: 500; overflow-wrap: anywhere; }
  .su-mutasi td, .su-mutasi th { vertical-align: middle; }
  .su-mutasi thead th { white-space: nowrap; }
  .su-mutasi tfoot td { font-weight: 600; }

  @media (max-width: 575.98px) { .su-scroll { max-height: none; } }
</style>
@endsection

@section('page-script')
<script>
  $(function () {
    var URL = {
      mahasiswa: @json(route('operator.saldo-ukt.mahasiswa')),
      store: @json(route('operator.saldo-ukt.store')),
      detail: @json(route('operator.saldo-ukt.detail', '__ID__')),
      detailExport: @json(route('operator.saldo-ukt.detail.export', '__ID__')),
      hapus: @json(route('operator.saldo-ukt.destroy', '__ID__')),
      export: @json(route('operator.saldo-ukt.export')),
    };
    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'), Accept: 'application/json' } });

    function url(k, id) { return URL[k].replace('__ID__', id); }
    function rp(n) { return Number(n || 0).toLocaleString('id-ID'); }
    function esc(v) { return $('<div>').text(v == null || v === '' ? '—' : String(v)).html(); }
    function pesanGalat(xhr) {
      var j = xhr.responseJSON || {};
      if (j.errors) return Object.values(j.errors)[0][0];
      return j.message || 'Terjadi kesalahan.';
    }
    function galat(xhr) { Swal.fire({ icon: 'error', title: 'Gagal', text: typeof xhr === 'string' ? xhr : pesanGalat(xhr) }); }
    function toast(t) { Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: t, timer: 1800, showConfirmButton: false }); }
    function yakin(judul, teks, tombol, kelas) {
      return Swal.fire({
        icon: 'warning', title: judul, text: teks, showCancelButton: true,
        confirmButtonText: tombol || 'Ya', cancelButtonText: 'Batal',
        customClass: { confirmButton: 'btn ' + (kelas || 'btn-danger') + ' me-2', cancelButton: 'btn btn-label-secondary' }, buttonsStyling: false,
      }).then(function (r) { return r.isConfirmed; });
    }
    function inisial(nama) {
      return String(nama || '?').trim().split(/\s+/).slice(0, 2).map(function (s) { return s[0]; }).join('').toUpperCase();
    }

    /* ---------------- Filter daftar ---------------- */

    $('.select2-filter').each(function () {
      var $el = $(this);
      $el.wrap('<div class="position-relative"></div>').select2({
        width: '100%', allowClear: true,
        placeholder: $el.find('option[value=""]').text(),
        dropdownParent: $el.parent(),
      });
    });
    $('#filter-saldo select').on('change', function () { this.form.submit(); });
    $('#btn-export').on('click', function () {
      var q = $('#filter-saldo').serialize();
      location.href = URL.export + (q ? '?' + q : '');
    });

    // Muat ulang daftar & ringkasan tanpa reload halaman (form tambah tidak hilang).
    function segarkanDaftar() {
      $('#su-daftar').addClass('su-loading');
      return $.get(location.href, null, null, 'html').done(function (html) {
        var $baru = $('<div>').append($.parseHTML(html));
        $('#su-daftar').replaceWith($baru.find('#su-daftar'));
        $('#su-stats').replaceWith($baru.find('#su-stats'));
        $('#su-ringkas').replaceWith($baru.find('#su-ringkas'));
      }).always(function () { $('#su-daftar').removeClass('su-loading'); });
    }

    /* ---------------- Form tambah saldo ---------------- */

    var mhs = null, timer, cariXhr;
    var $form = $('#form-saldo');

    function resetMahasiswa(pesan) {
      mhs = null;
      $('#id_reg_pd').val('');
      $('#smmhs').val('');
      $('#profil-isi').addClass('d-none');
      $('#profil-kosong').removeClass('d-none').find('span').text(pesan || 'Ketik NPM mahasiswa untuk memulai.');
      $('#info-semester-ada').addClass('d-none');
      $('#btn-simpan, #btn-profil-rincian').prop('disabled', true);
    }

    function tampilPosisi(p) {
      $('#p-saldo').text(rp(p.saldo));
      $('#p-digunakan').text(rp(p.digunakan));
      $('#p-sisa').text(rp(p.sisa)).toggleClass('text-success', p.sisa > 0).toggleClass('text-danger', p.sisa < 0);
      $('#info-semester-ada').toggleClass('d-none', !p.masuk_smt)
        .find('span').text('Mahasiswa ini sudah menerima ' + p.masuk_smt + ' saldo masuk pada ' + $('#id_smt option:selected').text() + '.');
    }

    function cariMahasiswa() {
      var npm = $.trim($('#npm').val());
      if (npm.length <= 7) { if (mhs || npm === '') resetMahasiswa(); return; }

      if (cariXhr) cariXhr.abort();
      $('#npm-spinner').removeClass('d-none');
      cariXhr = $.get(URL.mahasiswa, { npm: npm, id_smt: $('#id_smt').val() })
        .done(function (d) {
          mhs = d;
          $('#id_reg_pd').val(d.idreg);
          $('#smmhs').val(d.smmhs || '');
          $('#p-avatar').text(inisial(d.nmmhs));
          $('#p-nama').text(d.nmmhs);
          $('#p-npm').text(d.npm);
          $('#p-prodi').text(d.nmprg);
          $('#p-angkatan').text(d.thmsk);
          tampilPosisi(d.posisi);
          $('#profil-kosong').addClass('d-none'); $('#profil-isi').removeClass('d-none');
          $('#btn-simpan, #btn-profil-rincian').prop('disabled', false);
          $('#jumlah').trigger('focus');
        })
        .fail(function (xhr) { if (xhr.statusText !== 'abort') resetMahasiswa('NPM ' + npm + ' tidak ditemukan.'); })
        .always(function () { $('#npm-spinner').addClass('d-none'); });
    }

    $('#npm')
      .on('input', function () { clearTimeout(timer); timer = setTimeout(cariMahasiswa, 350); })
      .on('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); clearTimeout(timer); cariMahasiswa(); } });
    $('#id_smt').on('change', cariMahasiswa);

    // Jumlah diketik dengan pemisah ribuan; dikirim sebagai angka.
    $('#jumlah').on('input', function () {
      var angka = this.value.replace(/\D/g, '').replace(/^0+/, '');
      this.value = angka ? Number(angka).toLocaleString('id-ID') : '';
    });

    $form.on('submit', function (e) {
      e.preventDefault();
      if (!mhs) return galat('NPM mahasiswa belum diisi.');

      var jumlah = $('#jumlah').val().replace(/\D/g, '');
      if (!jumlah || Number(jumlah) <= 0) return galat('Jumlah saldo belum diisi.');

      var jenis = $('input[name="jenis"]:checked');
      var lanjut = yakin(
        'Tambah saldo?',
        jenis.data('label') + ' Rp ' + rp(jumlah) + ' untuk ' + mhs.npm + ' · ' + mhs.nmmhs +
          ($('#info-semester-ada').hasClass('d-none') ? '' : '. Semester ini sudah ada saldo masuk.'),
        'Simpan', 'btn-success'
      );

      lanjut.then(function (ok) {
        if (!ok) return;
        var $btn = $('#btn-simpan').prop('disabled', true);
        $.post(URL.store, {
          id_reg_pd: mhs.idreg, id_smt: $('#id_smt').val(), smmhs: $('#smmhs').val(),
          jenis: jenis.val(), jumlah: jumlah,
        })
          .done(function (d) {
            toast(d.message);
            $('#jumlah').val('');
            tampilPosisi(d.posisi);
            segarkanDaftar();
          })
          .fail(galat)
          .always(function () { $btn.prop('disabled', !mhs); });
      });
    });

    /* ---------------- Modal rincian mutasi ---------------- */

    var $modal = $('#modalRincianSaldo');
    var idAktif = null, permintaan = null;

    function renderRincian(d) {
      var info = [['NPM', d.npm], ['Nama', d.nama], ['Program studi', d.prodi], ['Angkatan', d.angkatan]];
      $modal.find('.su-dl').html($.map(info, function (r) { return '<dt>' + esc(r[0]) + '</dt><dd>' + esc(r[1]) + '</dd>'; }).join(''));
      $modal.find('.su-r-saldo').text(rp(d.saldo));
      $modal.find('.su-r-digunakan').text(rp(d.digunakan));
      $modal.find('.su-r-sisa').text(rp(d.sisa)).toggleClass('text-success', d.sisa > 0).toggleClass('text-danger', d.sisa < 0);

      $modal.find('.su-r-mutasi').html(d.mutasi.length ? $.map(d.mutasi, function (m, i) {
        var uraian = esc(m.uraian) + (m.jenis
          ? '<small class="d-block"><span class="badge bg-label-success">' + esc(m.jenis) + '</span></small>'
          : (m.kpp ? '<small class="d-block text-muted">dari KPP</small>' : ''));
        return '<tr>' +
          '<td class="text-muted">' + (i + 1) + '</td>' +
          '<td class="text-nowrap">' + esc(m.semester) + '<small class="d-block text-muted">semester ' + esc(m.sem) + '</small></td>' +
          '<td>' + uraian + '</td>' +
          '<td class="text-end text-nowrap text-success">' + (m.debet ? rp(m.debet) : '') + '</td>' +
          '<td class="text-end text-nowrap text-danger">' + (m.kredit ? rp(m.kredit) : '') + '</td>' +
          '<td class="text-end text-nowrap fw-medium' + (m.saldo < 0 ? ' text-danger' : '') + '">' + rp(m.saldo) + '</td>' +
          '<td class="su-aksi text-center">' + (m.bisa_hapus
            ? '<button type="button" class="btn btn-icon btn-sm btn-text-danger rounded-pill" data-hapus="' + m.id + '" data-jumlah="' + m.debet + '" title="Hapus saldo masuk"><i class="ti ti-trash ti-xs"></i></button>'
            : '') + '</td>' +
          '</tr>';
      }).join('') : '<tr><td colspan="7" class="text-center text-muted py-3">Belum ada mutasi saldo.</td></tr>');
      $modal.find('.su-r-debet-sum').text(rp(d.saldo));
      $modal.find('.su-r-kredit-sum').text(rp(d.digunakan));
      $modal.find('.su-r-sisa-sum').text(rp(d.sisa));
    }

    function muatRincian(id, diam) {
      idAktif = id;
      if (!diam) {
        $modal.find('.su-detail-isi, .su-detail-galat').addClass('d-none');
        $modal.find('.su-detail-muat').removeClass('d-none');
      }
      if (permintaan) permintaan.abort();
      permintaan = $.getJSON(url('detail', id))
        .done(function (d) {
          renderRincian(d);
          $modal.find('.su-detail-muat').addClass('d-none');
          $modal.find('.su-detail-isi').removeClass('d-none');
        })
        .fail(function (xhr, status) {
          if (status === 'abort') return;
          $modal.find('.su-detail-muat, .su-detail-isi').addClass('d-none');
          $modal.find('.su-detail-galat').removeClass('d-none')
            .text(xhr.status === 404 ? 'Mahasiswa tidak ditemukan.' : 'Rincian gagal dimuat. Coba lagi.');
        });
    }

    function bukaRincian(id) {
      bootstrap.Modal.getOrCreateInstance($modal[0]).show();
      muatRincian(id);
    }

    $(document).on('click', '[data-rincian]', function () { bukaRincian($(this).data('rincian')); });
    $('#btn-profil-rincian').on('click', function () { if (mhs) bukaRincian(mhs.idreg); });
    $('#btn-rincian-export').on('click', function () { if (idAktif) location.href = url('detailExport', idAktif); });

    $modal.on('click', '[data-hapus]', function () {
      var id = $(this).data('hapus');
      yakin('Hapus saldo masuk?', 'Saldo Rp ' + rp($(this).data('jumlah')) + ' akan dihapus.', 'Hapus').then(function (ok) {
        if (!ok) return;
        $.ajax({ url: url('hapus', id), type: 'DELETE' })
          .done(function (d) {
            toast(d.message);
            muatRincian(idAktif, true);
            segarkanDaftar();
            if (mhs && mhs.idreg === idAktif) cariMahasiswa();
          })
          .fail(galat);
      });
    });
  });
</script>
@endsection

@section('content')
<div class="pg-header mb-4">
  <div class="pg-header-main">
    <div class="pg-header-icon">
      <i class="ti ti-wallet"></i>
    </div>
    <div class="flex-grow-1 min-w-0">
      <div class="pg-header-crumb">
        <a href="{{ route('operator.dashboard') }}">Dashboard</a>
        <i class="ti ti-chevron-right"></i>
        <span>Laporan</span>
        <i class="ti ti-chevron-right"></i>
        <span>Saldo UKT</span>
      </div>
      <h4 class="pg-header-title">Saldo UKT Mahasiswa</h4>
      <p class="pg-header-sub">Tambah saldo beasiswa UKT/KIP dan pantau pemakaiannya untuk pembayaran KPP.</p>
    </div>
  </div>

  <div class="pg-header-stats" id="su-stats">
    <div class="pg-stat">
      <span class="pg-stat-icon"><i class="ti ti-users"></i></span>
      <div>
        <strong>{{ $rp($ringkasan['mahasiswa']) }}</strong>
        <small>Mahasiswa bersaldo</small>
      </div>
    </div>
    <div class="pg-stat">
      <span class="pg-stat-icon is-info"><i class="ti ti-arrow-down-circle"></i></span>
      <div>
        <strong>Rp {{ $rp($ringkasan['saldo']) }}</strong>
        <small>Saldo masuk</small>
      </div>
    </div>
    <div class="pg-stat">
      <span class="pg-stat-icon is-danger"><i class="ti ti-arrow-up-circle"></i></span>
      <div>
        <strong>Rp {{ $rp($ringkasan['digunakan']) }}</strong>
        <small>Sudah digunakan</small>
      </div>
    </div>
    <div class="pg-stat">
      <span class="pg-stat-icon is-success"><i class="ti ti-coin"></i></span>
      <div>
        <strong>Rp {{ $rp($ringkasan['sisa']) }}</strong>
        <small>Sisa saldo</small>
      </div>
    </div>
  </div>
</div>

<div class="row g-3">
  {{-- Form tambah saldo --}}
  <div class="col-12 col-xl-4">
    <div class="card su-form">
      <div class="card-header d-flex align-items-center gap-2 py-3">
        <i class="ti ti-circle-plus text-primary"></i>
        <h6 class="mb-0">Tambah Saldo</h6>
      </div>
      <div class="card-body pt-0">
        <form id="form-saldo" autocomplete="off" novalidate>
          <input type="hidden" id="id_reg_pd" name="id_reg_pd">

          <div class="row g-2 mb-3">
            <div class="col-7">
              <label class="form-label" for="id_smt">Tahun Akademik</label>
              <select id="id_smt" name="id_smt" class="form-select">
                @foreach ($semester as $id => $nama)
                <option value="{{ $id }}" @selected($id == $semesterAktif)>{{ $nama }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-5 su-npm">
              <label class="form-label" for="npm">NPM</label>
              <div class="position-relative">
                <input type="text" id="npm" class="form-control" placeholder="Ketik NPM" inputmode="numeric" maxlength="20">
                <span id="npm-spinner" class="spinner-border spinner-border-sm text-primary position-absolute top-50 end-0 translate-middle-y me-2 d-none" role="status"></span>
              </div>
            </div>
          </div>

          <div class="su-profil mb-3">
            <div id="profil-kosong" class="d-flex align-items-center gap-2 text-muted small">
              <i class="ti ti-user-search"></i><span>Ketik NPM mahasiswa untuk memulai.</span>
            </div>
            <div id="profil-isi" class="d-none">
              <div class="d-flex align-items-center gap-3 mb-2">
                <div id="p-avatar" class="su-avatar bg-label-primary">?</div>
                <div class="min-w-0 flex-grow-1">
                  <div id="p-nama" class="fw-semibold su-wrap"></div>
                  <div class="d-flex flex-wrap gap-1 mt-1">
                    <span class="su-chip"><i class="ti ti-id"></i><span id="p-npm"></span></span>
                    <span class="su-chip"><i class="ti ti-calendar"></i><span id="p-angkatan"></span></span>
                  </div>
                </div>
                <button type="button" id="btn-profil-rincian" class="btn btn-icon btn-sm btn-text-primary rounded-pill" title="Rincian saldo" disabled>
                  <i class="ti ti-list-details ti-xs"></i>
                </button>
              </div>
              <div id="p-prodi" class="su-meta mb-2 su-wrap"></div>
              <div class="su-posisi">
                <div><small>Saldo masuk</small><strong id="p-saldo">0</strong></div>
                <div><small>Digunakan</small><strong id="p-digunakan">0</strong></div>
                <div><small>Sisa</small><strong id="p-sisa">0</strong></div>
              </div>
            </div>
          </div>

          <div id="info-semester-ada" class="alert alert-warning d-flex gap-2 small py-2 px-3 d-none" role="alert">
            <i class="ti ti-alert-triangle mt-1"></i><span></span>
          </div>

          <div class="mb-3">
            <label class="form-label d-block">Jenis Beasiswa</label>
            <div class="btn-group w-100 su-jenis" role="group" aria-label="Jenis beasiswa">
              @foreach ($JENIS_INPUT as $id => $nama)
              <input type="radio" class="btn-check" name="jenis" id="jenis-{{ $id }}" value="{{ $id }}" data-label="{{ $nama }}" @checked($loop->first)>
              <label class="btn btn-outline-primary" for="jenis-{{ $id }}">{{ $nama }}</label>
              @endforeach
            </div>
          </div>

          <div class="row g-2 mb-3">
            <div class="col-4">
              <label class="form-label" for="smmhs">Semester Mhs</label>
              <input type="number" id="smmhs" name="smmhs" class="form-control" min="1" max="30">
            </div>
            <div class="col-8 su-jumlah">
              <label class="form-label" for="jumlah">Jumlah Saldo</label>
              <div class="input-group">
                <span class="input-group-text">Rp</span>
                <input type="text" id="jumlah" name="jumlah" class="form-control" inputmode="numeric" placeholder="0">
              </div>
            </div>
          </div>

          <button type="submit" id="btn-simpan" class="btn btn-primary w-100" disabled>
            <i class="ti ti-device-floppy ti-xs me-1"></i>Simpan Saldo
          </button>
        </form>
      </div>
    </div>
  </div>

  {{-- Daftar saldo per mahasiswa --}}
  <div class="col-12 col-xl-8">
    <div class="card">
      <div class="su-filter px-3 py-3 rounded-top">
        <form id="filter-saldo" method="GET" action="{{ route('operator.saldo-ukt.index') }}" class="row g-2 align-items-end" autocomplete="off">
          @if ($status !== '')
          <input type="hidden" name="status" value="{{ $status }}">
          @endif
          <div class="col-12 col-sm-6 col-lg">
            <label class="form-label" for="f_id_sms">Program Studi</label>
            <select id="f_id_sms" name="id_sms" class="form-select select2-filter">
              <option value="">Semua prodi</option>
              @foreach ($prodi as $p)
              <option value="{{ $p->id_sms }}" @selected(($filter['id_sms'] ?? '') === $p->id_sms)>
                {{ trim($p->jenjang_didik) }} {{ trim($p->nm_lemb) }}
              </option>
              @endforeach
            </select>
          </div>
          <div class="col-6 col-sm-3 col-lg-2">
            <label class="form-label" for="f_angkatan">Angkatan</label>
            <select id="f_angkatan" name="angkatan" class="form-select">
              <option value="">Semua</option>
              @foreach ($angkatan as $a)
              <option value="{{ $a }}" @selected(($filter['angkatan'] ?? '') == $a)>{{ $a }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-6 col-sm-3 col-lg-2">
            <label class="form-label" for="f_jenis">Jenis</label>
            <select id="f_jenis" name="jenis" class="form-select">
              <option value="">Semua</option>
              @foreach ($JENIS as $id => $nama)
              <option value="{{ $id }}" @selected(($filter['jenis'] ?? '') === (string) $id)>{{ $nama }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-12 col-sm-6 col-lg-3">
            <label class="form-label" for="f_npm">Cari</label>
            <div class="input-group input-group-merge">
              <span class="input-group-text"><i class="ti ti-search"></i></span>
              <input type="search" id="f_npm" name="npm" class="form-control" placeholder="NPM / nama, Enter"
                value="{{ $filter['npm'] ?? '' }}">
            </div>
          </div>
          <div class="col-4 col-sm-2 col-lg-auto">
            <label class="form-label" for="f_limit">Baris</label>
            <select id="f_limit" name="limit" class="form-select">
              @foreach ([10, 25, 50, 100] as $n)
              <option value="{{ $n }}" @selected(($filter['limit'] ?? 25) == $n)>{{ $n }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-8 col-sm-4 col-lg-auto d-flex gap-2 justify-content-end">
            <a href="{{ route('operator.saldo-ukt.index') }}" class="btn btn-label-secondary" title="Reset filter">
              <i class="ti ti-refresh ti-xs"></i>
            </a>
            <button type="button" id="btn-export" class="btn btn-success">
              <i class="ti ti-file-spreadsheet ti-xs me-1"></i>Excel
            </button>
          </div>
        </form>
      </div>

      <div class="card-body border-top py-3" id="su-ringkas">
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-2">
          <div class="su-sum">Saldo masuk<strong>Rp {{ $rp($ringkasan['saldo']) }}</strong></div>
          <div class="su-sum text-end">Terpakai<strong>Rp {{ $rp($ringkasan['digunakan']) }} ({{ str_replace('.', ',', $persen) }}%)</strong></div>
        </div>
        <div class="progress su-progress" role="progressbar" aria-label="Persentase saldo terpakai" aria-valuenow="{{ $persen }}" aria-valuemin="0" aria-valuemax="100">
          <div class="progress-bar bg-danger" style="width: {{ min(100, $persen) }}%"></div>
        </div>
      </div>

      <div id="su-daftar">
        <div class="card-header su-tabs-header">
          <ul class="nav nav-tabs su-tabs" role="tablist">
            <li class="nav-item">
              <a class="nav-link @if ($status === '') active @endif" href="{{ $urlStatus(null) }}">
                <i class="ti ti-list"></i><span>Semua</span>
                <span class="badge rounded-pill bg-label-primary">{{ $rp($ringkasan['mahasiswa']) }}</span>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link @if ($status === '1') active @endif" href="{{ $urlStatus('1') }}">
                <i class="ti ti-coin"></i><span>{{ $STATUS['1'] }}</span>
                <span class="badge rounded-pill bg-success">{{ $rp($ringkasan['ada_sisa']) }}</span>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link @if ($status === '0') active @endif" href="{{ $urlStatus('0') }}">
                <i class="ti ti-coin-off"></i><span>{{ $STATUS['0'] }}</span>
                <span class="badge rounded-pill bg-secondary">{{ $rp($ringkasan['habis']) }}</span>
              </a>
            </li>
          </ul>
        </div>

        <div class="su-scroll">
          <table class="table table-sm table-hover small mb-0 su-table">
            <thead>
              <tr>
                <th class="d-none d-sm-table-cell">#</th>
                <th>Mahasiswa</th>
                <th class="d-none d-lg-table-cell">Program Studi</th>
                <th class="text-end d-none d-md-table-cell">Saldo Masuk</th>
                <th class="text-end d-none d-md-table-cell">Digunakan</th>
                <th class="text-end">Sisa</th>
                <th class="su-aksi text-center">Aksi</th>
              </tr>
            </thead>
            <tbody>
              @forelse ($data as $i => $row)
              @php($prodiNama = trim($row->jenjang_didik) . ' ' . trim($row->nm_lemb))
              <tr>
                <td class="d-none d-sm-table-cell text-muted">{{ $data->firstItem() + $i }}</td>
                <td class="su-wrap">
                  <span class="su-meta d-block">{{ trim($row->nipd) }} · {{ substr(trim((string) $row->mulai_smt), 0, 4) }}</span>
                  <span class="fw-medium">{{ trim((string) $row->nm_pd) }}</span>
                  @foreach (array_filter(explode(',', (string) $row->jenis), 'strlen') as $j)
                  <span class="badge bg-label-{{ $j === '2' ? 'info' : ($j === '1' ? 'primary' : 'secondary') }} ms-1">{{ $JENIS[(int) $j] ?? $j }}</span>
                  @endforeach
                  <small class="d-block d-lg-none text-muted">{{ $prodiNama }}</small>
                </td>
                <td class="d-none d-lg-table-cell su-wrap">{{ $prodiNama }}</td>
                <td class="text-end text-nowrap d-none d-md-table-cell">{{ $rp($row->saldo) }}</td>
                <td class="text-end text-nowrap d-none d-md-table-cell">{{ $rp($row->digunakan) }}</td>
                <td class="text-end text-nowrap fw-medium">
                  @if ((int) $row->sisa > 0)
                  <span class="text-success">{{ $rp($row->sisa) }}</span>
                  @elseif ((int) $row->sisa < 0)
                  <span class="text-danger" title="Pemakaian melebihi saldo masuk">−{{ $rp(-$row->sisa) }}</span>
                  @else
                  <span class="badge bg-label-secondary">Habis</span>
                  @endif
                  <small class="d-block d-md-none text-muted mt-1">{{ $rp($row->digunakan) }} / {{ $rp($row->saldo) }}</small>
                </td>
                <td class="su-aksi text-center">
                  <button type="button" class="btn btn-icon btn-sm btn-text-primary rounded-pill"
                    data-rincian="{{ $row->id_reg_pd }}" title="Rincian saldo" aria-label="Rincian saldo {{ trim($row->nipd) }}">
                    <i class="ti ti-eye ti-xs"></i>
                  </button>
                </td>
              </tr>
              @empty
              <tr>
                <td colspan="7" class="p-0">
                  <div class="su-empty"><i class="ti ti-wallet-off"></i>Tidak ada saldo yang cocok dengan filter.</div>
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
    </div>
  </div>
</div>

{{-- Modal rincian mutasi saldo satu mahasiswa --}}
<div class="modal fade" id="modalRincianSaldo" tabindex="-1" aria-labelledby="modalRincianSaldoLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalRincianSaldoLabel">Rincian Saldo</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <div class="su-detail-muat text-center text-muted py-5">
          <div class="spinner-border spinner-border-sm me-2" role="status"></div>Memuat rincian…
        </div>
        <div class="su-detail-galat alert alert-danger mb-0 d-none" role="alert"></div>
        <div class="su-detail-isi d-none">
          <dl class="su-dl"></dl>

          <div class="row g-2 mt-2 text-center">
            <div class="col-4"><div class="border rounded p-2"><small class="text-muted d-block">Saldo masuk</small><strong class="su-r-saldo"></strong></div></div>
            <div class="col-4"><div class="border rounded p-2"><small class="text-muted d-block">Digunakan</small><strong class="su-r-digunakan"></strong></div></div>
            <div class="col-4"><div class="border rounded p-2"><small class="text-muted d-block">Sisa</small><strong class="su-r-sisa"></strong></div></div>
          </div>

          <h6 class="mt-4 mb-2">Mutasi saldo</h6>
          <div class="table-responsive border rounded">
            <table class="table table-sm small mb-0 su-mutasi">
              <thead>
                <tr>
                  <th>#</th><th>Tahun Akademik</th><th>Uraian</th>
                  <th class="text-end">Debet</th><th class="text-end">Kredit</th><th class="text-end">Saldo</th><th></th>
                </tr>
              </thead>
              <tbody class="su-r-mutasi"></tbody>
              <tfoot>
                <tr>
                  <td colspan="3" class="text-end">Total</td>
                  <td class="text-end text-nowrap su-r-debet-sum"></td>
                  <td class="text-end text-nowrap su-r-kredit-sum"></td>
                  <td class="text-end text-nowrap su-r-sisa-sum"></td>
                  <td></td>
                </tr>
              </tfoot>
            </table>
          </div>
          <small class="d-block text-muted mt-2">Kredit adalah pemakaian saldo dari proses KPP dan tidak dapat dihapus di sini.</small>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" id="btn-rincian-export" class="btn btn-success">
          <i class="ti ti-file-spreadsheet ti-xs me-1"></i>Excel
        </button>
        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>
@endsection
