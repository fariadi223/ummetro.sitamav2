@extends('layouts/layoutMaster')

@section('title', 'Struk Penmaru')

@php
  $STATUS = \App\Repositories\Tagihan\TagihanPmbRepository::STATUS;
  $status = (string) ($filter['status'] ?? '');
  $belum = $ringkasan['total'] - $ringkasan['lunas'];
  // URL tab status: filter lain tetap, halaman kembali ke 1.
  $urlStatus = fn ($s) => route('operator.tagihan-pmb.index', array_filter(
    ['status' => $s] + \Illuminate\Support\Arr::except($filter, ['status']),
    fn ($v) => $v !== null
  ) + ['tahun' => $filter['tahun'] ?? '']);
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

  /* Bar kontrol */
  .pm-toolbar .form-label { font-size: .75rem; margin-bottom: .25rem; }
  .pm-nomor .form-control { font-weight: 600; letter-spacing: .03em; text-transform: uppercase; }

  /* Strip profil peserta */
  .pm-profil { border-top: 1px dashed var(--bs-border-color); }
  .pm-avatar {
    width: 2.75rem; height: 2.75rem; flex: 0 0 2.75rem;
    display: grid; place-items: center; border-radius: 50%;
    font-weight: 700; letter-spacing: .02em;
  }
  .pm-chip {
    display: inline-flex; align-items: center; gap: .3rem;
    padding: .2rem .6rem; border-radius: 50rem; font-size: .75rem;
    background: var(--bs-tertiary-bg, rgba(0,0,0,.04)); white-space: nowrap;
  }

  /* Header kartu sejajar dengan tinggi tab */
  :root { --pm-head-h: 3.75rem; }
  .pm-head { min-height: var(--pm-head-h); }

  /* Panel tagihan peserta */
  .pm-empty { padding: 2.25rem 1rem; text-align: center; color: var(--bs-secondary-color); }
  .pm-empty i { font-size: 2rem; display: block; margin-bottom: .4rem; opacity: .5; }
  .pm-pilihan .btn {
    display: flex; align-items: center; gap: .6rem; text-align: start;
    width: 100%; padding: .65rem .85rem; font-size: .8125rem;
  }
  .pm-pilihan .btn .pm-urut {
    width: 1.6rem; height: 1.6rem; flex: 0 0 1.6rem; display: grid; place-items: center;
    border-radius: 50%; font-size: .7rem; font-weight: 700;
    background: var(--bs-tertiary-bg, rgba(0,0,0,.05));
  }
  .pm-pilihan .btn-check:checked + .btn .pm-urut { background: rgba(255,255,255,.25); }
  .pm-terbilang { font-size: .75rem; color: var(--bs-secondary-color); min-height: 1.1rem; }
  .pm-info-row { display: flex; justify-content: space-between; gap: 1rem; font-size: .8125rem; padding: .35rem 0; }
  .pm-info-row + .pm-info-row { border-top: 1px dashed var(--bs-border-color); }
  .pm-info-row span:first-child { color: var(--bs-secondary-color); white-space: nowrap; }
  .pm-info-row span:last-child { text-align: end; font-weight: 500; overflow-wrap: anywhere; }
  .pm-total { font-size: 1.15rem; font-weight: 700; }

  /* Tab status di header kartu daftar */
  .pm-tabs-header { padding: 0 1rem; border-bottom: 1px solid var(--bs-border-color); }
  .pm-tabs {
    flex-wrap: nowrap; overflow-x: auto; overflow-y: hidden;
    border-bottom: 0; margin-bottom: -1px; scrollbar-width: none; gap: .25rem;
  }
  .pm-tabs::-webkit-scrollbar { display: none; }
  .pm-tabs .nav-link {
    display: inline-flex; align-items: center; gap: .4rem;
    min-height: var(--pm-head-h); padding: 0 .9rem;
    font-size: .8125rem; font-weight: 500; white-space: nowrap;
    border: 0; border-bottom: 2px solid transparent; border-radius: 0;
    background: transparent; color: var(--bs-secondary-color);
  }
  .pm-tabs .nav-link i { font-size: 1.05rem; }
  .pm-tabs .nav-link:hover { color: var(--bs-primary); }
  .pm-tabs .nav-link.active { color: var(--bs-primary); border-bottom-color: var(--bs-primary); box-shadow: none; }
  .pm-tabs .badge { font-size: .65rem; line-height: 1; padding: .3em .55em; }

  /* Filter di dalam kartu daftar */
  .pm-filter .form-label { font-size: .75rem; margin-bottom: .25rem; }
  .pm-filter { background: var(--bs-tertiary-bg, rgba(0,0,0,.015)); }

  /* Tabel daftar */
  .pm-scroll {
    max-height: max(320px, calc(100vh - 430px));
    overflow-y: auto; overflow-x: hidden;
  }
  .pm-scroll thead th {
    position: sticky; top: 0; z-index: 2;
    background: var(--bs-card-bg, var(--bs-body-bg));
    box-shadow: inset 0 -1px 0 var(--bs-border-color);
    white-space: nowrap;
  }
  .pm-table td { vertical-align: middle; }
  .pm-table .pm-nama { overflow-wrap: anywhere; word-break: break-word; }
  .pm-no { font-weight: 600; letter-spacing: .02em; font-size: .75rem; color: var(--bs-secondary-color); }
  .pm-aksi { white-space: nowrap; width: 1%; }
  .pm-aksi .btn { width: 1.9rem; height: 1.9rem; padding: 0; }
  .pm-row-aktif { background: rgba(var(--bs-primary-rgb), .07); }
  .pm-row-aktif td:first-child { box-shadow: inset 3px 0 0 var(--bs-primary); }

  @media (max-width: 575.98px) {
    .pm-scroll { max-height: none; }
    .pg-header-stats .pg-stat strong { font-size: .95rem; }
  }
</style>
@endsection

@section('page-script')
<script>
  $(function () {
    var URL = {
      peserta: @json(route('operator.tagihan-pmb.peserta')),
      biaya: @json(route('operator.tagihan-pmb.biaya')),
      simpan: @json(route('operator.tagihan-pmb.store')),
      tidakLulus: @json(route('operator.tagihan-pmb.tidak-lulus')),
      ubah: @json(route('operator.tagihan-pmb.update')),
      hapus: @json(route('operator.tagihan-pmb.destroy')),
      tutup: @json(route('operator.tagihan-pmb.tutup')),
      export: @json(route('operator.tagihan-pmb.export')),
    };

    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'), Accept: 'application/json' } });

    function rp(n) { return 'Rp ' + Number(n || 0).toLocaleString('id-ID'); }
    function esc(s) {
      return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
      });
    }
    function inisial(nama) {
      return String(nama || '?').trim().split(/\s+/).slice(0, 2).map(function (s) { return s[0]; }).join('').toUpperCase();
    }
    function pesanGalat(xhr) {
      var j = xhr.responseJSON || {};
      if (j.errors) return Object.values(j.errors)[0][0];
      return j.message || 'Terjadi kesalahan.';
    }
    function galat(xhr) { Swal.fire({ icon: 'error', title: 'Gagal', text: typeof xhr === 'string' ? xhr : pesanGalat(xhr) }); }
    function yakin(judul, teks, warna) {
      return Swal.fire({
        icon: 'warning', title: judul, text: teks, showCancelButton: true,
        confirmButtonText: 'Ya', cancelButtonText: 'Batal',
        customClass: { confirmButton: 'btn btn-' + (warna || 'danger') + ' me-2', cancelButton: 'btn btn-label-secondary' }, buttonsStyling: false,
      }).then(function (r) { return r.isConfirmed; });
    }
    // Aksi berhasil: pesan singkat lalu muat ulang daftar; peserta dibuka lagi setelah reload.
    function sukses(d) {
      try { sessionStorage.setItem('pm-peserta', peserta ? peserta.no_tes : ''); } catch (e) {}
      Swal.fire({ icon: 'success', title: d.message, timer: 1100, showConfirmButton: false })
        .then(function () { location.reload(); });
    }
    function kirim(method, url, data, $btn) {
      $btn && $btn.prop('disabled', true);
      return $.ajax({ url: url, method: method, data: data })
        .done(sukses).fail(galat)
        .always(function () { $btn && $btn.prop('disabled', false); });
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
    $('#filter-pmb select').on('change', function () { this.form.submit(); });
    $('#btn-export').on('click', function () {
      var q = $('#filter-pmb').serialize();
      location.href = URL.export + (q ? '?' + q : '');
    });

    /* ---------------- Peserta ---------------- */

    var peserta = null, cariXhr = null;

    function panel(id) {
      $('#pm-kosong, #pm-tambah, #pm-ubah, #pm-lunas').addClass('d-none');
      $(id).removeClass('d-none');
    }

    function resetPeserta(pesan) {
      peserta = null;
      $('#profil-isi').addClass('d-none');
      $('#profil-kosong').removeClass('d-none').find('span').text(pesan || 'Ketik nomor peserta, lalu Enter.');
      $('#panel-sub').text('—');
      $('#pm-kosong span').text(pesan || 'Tagihan peserta muncul di sini.');
      panel('#pm-kosong');
      $('.pm-table tr').removeClass('pm-row-aktif');
    }

    function chipStatus(d) {
      if (d.tagihan && d.tagihan.lunas) return '<span class="badge bg-label-success">Tagihan lunas</span>';
      if (d.tagihan) return '<span class="badge bg-label-warning">Belum lunas</span>';
      if (d.kelulusan === 0) return '<span class="badge bg-label-danger">Tidak lulus</span>';
      return '<span class="badge bg-label-secondary">Belum ada tagihan</span>';
    }

    function tampilPeserta(d) {
      peserta = d;
      $('#p-avatar').text(inisial(d.nama));
      $('#p-nama').text(d.nama);
      $('#p-nomor').text(d.no_tes);
      $('#p-angkatan').text(d.angkatan);
      $('#p-status').html(chipStatus(d));
      $('#p-pilihan').html(d.prodi.length
        ? d.prodi.map(function (p, i) { return '<span class="pm-chip"><i class="ti ti-school ti-xs"></i>' + (i + 1) + '. ' + esc(p.nama) + '</span>'; }).join('')
        : '<span class="pm-chip text-danger"><i class="ti ti-alert-circle ti-xs"></i>Tanpa pilihan prodi</span>');
      $('#profil-kosong').addClass('d-none'); $('#profil-isi').removeClass('d-none');
      $('#panel-sub').text(d.nama + ' · ' + d.no_tes);

      $('.pm-table tr').removeClass('pm-row-aktif');
      $('.pm-table tr[data-nomor="' + d.no_tes.replace(/"/g, '') + '"]').addClass('pm-row-aktif');

      if (!d.tagihan) {
        var html = '';
        d.prodi.forEach(function (p, i) {
          html += '<input type="radio" class="btn-check" name="prodi" id="prodi' + i + '" value="' + esc(p.kode) + '">' +
            '<label class="btn btn-outline-primary" for="prodi' + i + '"><span class="pm-urut">' + (i + 1) + '</span>' +
            '<span class="min-w-0">' + esc(p.nama) + '</span></label>';
        });
        $('#pilihan-prodi').html(html || '<div class="text-danger small">Peserta tidak memiliki pilihan prodi.</div>');
        $('#uang').val('').trigger('input');
        $('#uang-info').text('');
        $('#btn-simpan').prop('disabled', true);
        $('#lulus-info').toggleClass('d-none', d.kelulusan !== 0);
        panel('#pm-tambah');
      } else if (!d.tagihan.lunas) {
        $('#ubah-prodi').text(d.tagihan.prodi);
        $('#ubah-nomor').text(d.tagihan.nomor || '—');
        $('#ubah-awal').text(rp(d.tagihan.uang));
        $('#uang_ubah').val(d.tagihan.uang).trigger('input');
        panel('#pm-ubah');
      } else {
        $('#lunas-prodi').text(d.tagihan.prodi);
        $('#lunas-nomor').text(d.tagihan.nomor || '—');
        $('#lunas-npm').text(d.tagihan.npm || 'Belum terdaftar');
        $('#lunas-uang').text(rp(d.tagihan.uang));
        panel('#pm-lunas');
      }
    }

    function cariPeserta(nomor) {
      nomor = $.trim(nomor || $('#no_tes').val()).toUpperCase();
      if (!nomor) return resetPeserta();
      $('#no_tes').val(nomor);

      if (cariXhr) cariXhr.abort();
      $('#nomor-spinner').removeClass('d-none');
      cariXhr = $.get(URL.peserta, { no_tes: nomor })
        .done(tampilPeserta)
        .fail(function (xhr) {
          if (xhr.statusText === 'abort') return;
          resetPeserta(xhr.status === 404 ? 'Peserta ' + nomor + ' tidak ditemukan.' : pesanGalat(xhr));
        })
        .always(function () { $('#nomor-spinner').addClass('d-none'); });
    }

    $('#form-cari').on('submit', function (e) { e.preventDefault(); cariPeserta(); });
    $('#btn-reset').on('click', function () { $('#no_tes').val('').trigger('focus'); resetPeserta(); });

    // Format rupiah di bawah isian nominal.
    $('#uang, #uang_ubah').on('input', function () {
      var v = Number($(this).val() || 0);
      $($(this).data('preview')).text(v > 0 ? rp(v) : '');
    });

    $('#pilihan-prodi').on('change', 'input[name="prodi"]', function () {
      var prodi = $(this).val();
      $('#uang').val('').trigger('input');
      $('#uang-info').text('');
      $('#uang-spinner').removeClass('d-none');
      $.get(URL.biaya, { no_tes: peserta.no_tes, prodi: prodi }).done(function (d) {
        $('#uang').val(d.uang || '').trigger('input');
        $('#uang-info').text(d.uang ? '' : 'Biaya registrasi (MA 873) untuk prodi & angkatan ini belum ditetapkan BAK.');
      }).always(function () {
        $('#uang-spinner').addClass('d-none');
        $('#btn-simpan').prop('disabled', false);
      });
    });

    $('#pm-tambah').on('submit', function (e) {
      e.preventDefault();
      kirim('POST', URL.simpan, { no_tes: peserta.no_tes, prodi: $('input[name="prodi"]:checked').val(), uang: $('#uang').val() }, $('#btn-simpan'));
    });

    $('#btn-tidak-lulus').on('click', function () {
      yakin('Tandai tidak lulus?', peserta.nama + ' akan ditandai tidak lulus.', 'warning').then(function (ok) {
        if (ok) kirim('POST', URL.tidakLulus, { no_tes: peserta.no_tes }, $('#btn-tidak-lulus'));
      });
    });

    $('#pm-ubah').on('submit', function (e) {
      e.preventDefault();
      kirim('PUT', URL.ubah, { no_tes: peserta.no_tes, uang: $('#uang_ubah').val() }, $('#btn-ubah'));
    });

    $('#btn-hapus').on('click', function () {
      yakin('Hapus tagihan?', 'Tagihan registrasi ulang ' + peserta.nama + ' akan dihapus dan status kelulusan dikosongkan.').then(function (ok) {
        if (ok) kirim('DELETE', URL.hapus, { no_tes: peserta.no_tes }, $('#btn-hapus'));
      });
    });

    function tutup(nomor, nama, $btn) {
      yakin('Tutup tagihan?', nama + ' (' + nomor + ') akan ditandai LUNAS.', 'success').then(function (ok) {
        if (ok) kirim('POST', URL.tutup, { no_tes: nomor }, $btn);
      });
    }
    $('#btn-tutup').on('click', function () { tutup(peserta.no_tes, peserta.nama, $(this)); });

    /* ---------------- Aksi baris tabel ---------------- */

    $(document).on('click', '[data-tutup]', function () {
      var $b = $(this);
      // Data peserta ikut dibuka agar tetap tampil setelah muat ulang.
      peserta = { no_tes: $b.data('tutup'), nama: $b.data('nama') };
      tutup($b.data('tutup'), $b.data('nama'), $b);
    });

    $(document).on('click', '[data-pilih]', function () {
      cariPeserta(String($(this).data('pilih')));
      if (window.matchMedia('(max-width: 1199.98px)').matches) {
        $('html, body').animate({ scrollTop: $('#card-toolbar').offset().top - 90 }, 200);
      }
    });

    // Buka kembali peserta terakhir setelah aksi + muat ulang.
    var terakhir = null;
    try { terakhir = sessionStorage.getItem('pm-peserta'); sessionStorage.removeItem('pm-peserta'); } catch (e) {}
    if (terakhir) cariPeserta(terakhir); else resetPeserta();
  });
</script>
@endsection

@section('content')
<div class="pg-header mb-4">
  <div class="pg-header-main">
    <div class="pg-header-icon">
      <i class="ti ti-file-invoice"></i>
    </div>
    <div class="flex-grow-1 min-w-0">
      <div class="pg-header-crumb">
        <a href="{{ route('operator.dashboard') }}">Dashboard</a>
        <i class="ti ti-chevron-right"></i>
        <span>Pembuatan Struk</span>
        <i class="ti ti-chevron-right"></i>
        <span>Struk Penmaru</span>
      </div>
      <h4 class="pg-header-title">Struk Registrasi Ulang Penmaru</h4>
      <p class="pg-header-sub">Buat, ubah, dan tutup tagihan registrasi ulang calon mahasiswa baru.</p>
    </div>
  </div>

  <div class="pg-header-stats">
    <div class="pg-stat">
      <span class="pg-stat-icon is-info"><i class="ti ti-calendar"></i></span>
      <div>
        <strong>{{ ($filter['tahun'] ?? '') ?: 'Semua' }}</strong>
        <small>Tahun masuk</small>
      </div>
    </div>
    <div class="pg-stat">
      <span class="pg-stat-icon"><i class="ti ti-users"></i></span>
      <div>
        <strong>{{ number_format($ringkasan['total'], 0, ',', '.') }}</strong>
        <small>Tagihan dibuat</small>
      </div>
    </div>
    <div class="pg-stat">
      <span class="pg-stat-icon is-success"><i class="ti ti-circle-check"></i></span>
      <div>
        <strong>{{ number_format($ringkasan['lunas'], 0, ',', '.') }}</strong>
        <small>Lunas · {{ $ringkasan['total'] ? round($ringkasan['lunas'] / $ringkasan['total'] * 100) : 0 }}%</small>
      </div>
    </div>
    <div class="pg-stat">
      <span class="pg-stat-icon is-danger"><i class="ti ti-coins"></i></span>
      <div>
        <strong>Rp {{ number_format($ringkasan['uang'], 0, ',', '.') }}</strong>
        <small>Total tagihan</small>
      </div>
    </div>
  </div>
</div>

{{-- Bar kontrol + profil peserta --}}
<div class="card mb-3 pm-toolbar" id="card-toolbar">
  <div class="card-body pb-3">
    <form id="form-cari" class="row g-2 align-items-end" autocomplete="off">
      <div class="col-12 col-md-6 col-lg-4 pm-nomor">
        <label class="form-label" for="no_tes">Nomor Peserta</label>
        <div class="input-group input-group-merge">
          <span class="input-group-text"><i class="ti ti-search"></i></span>
          <input type="text" id="no_tes" class="form-control" placeholder="2026/UMM/0001, lalu Enter">
          <span class="input-group-text">
            <span class="spinner-border spinner-border-sm text-primary d-none" id="nomor-spinner" role="status"></span>
          </span>
        </div>
      </div>
      <div class="col-12 col-md-6 col-lg-3 d-flex gap-2">
        <button type="submit" class="btn btn-primary flex-grow-1">
          <i class="ti ti-user-search ti-xs me-1"></i>Cari Peserta
        </button>
        <button type="button" id="btn-reset" class="btn btn-label-secondary" title="Kosongkan">
          <i class="ti ti-refresh ti-xs"></i>
        </button>
      </div>
      <div class="col-12 col-lg-5 d-none d-lg-flex justify-content-end">
        <small class="text-muted text-end">
          <i class="ti ti-info-circle ti-xs"></i>
          Klik <i class="ti ti-pencil ti-xs"></i> pada daftar untuk membuka peserta.
        </small>
      </div>
    </form>
  </div>

  <div class="pm-profil px-4 py-3">
    <div id="profil-kosong" class="d-flex align-items-center gap-2 text-muted small">
      <i class="ti ti-user-question"></i><span></span>
    </div>
    <div id="profil-isi" class="d-none d-flex flex-wrap align-items-center gap-3">
      <div class="d-flex align-items-center gap-3 min-w-0 me-auto">
        <div class="pm-avatar bg-label-primary" id="p-avatar"></div>
        <div class="min-w-0">
          <div class="fw-semibold text-truncate" id="p-nama"></div>
          <div class="small text-muted text-truncate"><span id="p-nomor"></span> · Angkatan <span id="p-angkatan"></span></div>
        </div>
      </div>
      <div class="d-flex flex-wrap align-items-center gap-2">
        <span id="p-pilihan" class="d-flex flex-wrap gap-2"></span>
        <span id="p-status"></span>
      </div>
    </div>
  </div>
</div>

<div class="row g-3">
  {{-- Panel tagihan peserta --}}
  <div class="col-12 col-xl-4">
    <div class="card h-100">
      <div class="card-header pm-head d-flex align-items-center py-2">
        <div class="min-w-0">
          <h6 class="mb-0"><i class="ti ti-receipt ti-xs me-1"></i>Tagihan Peserta</h6>
          <small class="text-muted text-truncate d-block" id="panel-sub">—</small>
        </div>
      </div>
      <div class="card-body border-top pt-3">
        <div id="pm-kosong" class="pm-empty">
          <i class="ti ti-receipt-off"></i><span></span>
        </div>

        {{-- Belum punya tagihan --}}
        <form id="pm-tambah" class="d-none" novalidate>
          <span class="form-label d-block">Program studi diterima</span>
          <div class="pm-pilihan d-grid gap-2 mb-3" id="pilihan-prodi"></div>

          <label class="form-label d-flex justify-content-between" for="uang">
            <span>Jumlah tagihan</span>
            <span class="spinner-border spinner-border-sm text-primary d-none" id="uang-spinner" role="status"></span>
          </label>
          <div class="input-group">
            <span class="input-group-text">Rp</span>
            <input type="number" id="uang" class="form-control" min="1" step="1" data-preview="#uang-preview" placeholder="Pilih prodi dulu">
          </div>
          <div class="pm-terbilang mt-1" id="uang-preview"></div>
          <div class="small text-danger" id="uang-info"></div>

          <div class="alert alert-danger small py-2 mt-3 mb-0 d-none" id="lulus-info">
            <i class="ti ti-alert-triangle ti-xs me-1"></i>Peserta ini saat ini ditandai tidak lulus.
          </div>

          <div class="d-flex flex-wrap gap-2 mt-3">
            <button type="submit" id="btn-simpan" class="btn btn-success flex-grow-1" disabled>
              <i class="ti ti-device-floppy ti-xs me-1"></i>Buat Tagihan
            </button>
            <button type="button" id="btn-tidak-lulus" class="btn btn-label-warning" title="Tandai peserta tidak lulus">
              <i class="ti ti-user-x ti-xs me-1"></i>Tidak Lulus
            </button>
          </div>
        </form>

        {{-- Punya tagihan belum lunas --}}
        <form id="pm-ubah" class="d-none" novalidate>
          <div class="mb-3">
            <div class="pm-info-row"><span>Program studi</span><span id="ubah-prodi"></span></div>
            <div class="pm-info-row"><span>No. registrasi</span><span id="ubah-nomor"></span></div>
            <div class="pm-info-row"><span>Tagihan saat ini</span><span id="ubah-awal"></span></div>
          </div>

          <label class="form-label" for="uang_ubah">Ubah jumlah tagihan</label>
          <div class="input-group">
            <span class="input-group-text">Rp</span>
            <input type="number" id="uang_ubah" class="form-control" min="1" step="1" data-preview="#ubah-preview">
          </div>
          <div class="pm-terbilang mt-1" id="ubah-preview"></div>

          <div class="d-flex flex-wrap gap-2 mt-3">
            <button type="submit" id="btn-ubah" class="btn btn-primary flex-grow-1">
              <i class="ti ti-device-floppy ti-xs me-1"></i>Simpan
            </button>
            <button type="button" id="btn-tutup" class="btn btn-label-success" title="Tutup tagihan (tandai lunas)">
              <i class="ti ti-key ti-xs me-1"></i>Tutup
            </button>
            <button type="button" id="btn-hapus" class="btn btn-label-danger" title="Hapus tagihan">
              <i class="ti ti-trash ti-xs"></i>
            </button>
          </div>
        </form>

        {{-- Sudah lunas --}}
        <div id="pm-lunas" class="d-none">
          <div class="alert alert-success d-flex align-items-center gap-2 py-2 mb-3">
            <i class="ti ti-circle-check"></i>
            <span class="small">Tagihan sudah lunas dan tidak dapat diubah.</span>
          </div>
          <div class="pm-info-row"><span>Program studi</span><span id="lunas-prodi"></span></div>
          <div class="pm-info-row"><span>No. registrasi</span><span id="lunas-nomor"></span></div>
          <div class="pm-info-row"><span>NPM</span><span id="lunas-npm"></span></div>
          <div class="pm-info-row align-items-center"><span>Tagihan</span><span class="pm-total text-success" id="lunas-uang"></span></div>
        </div>
      </div>
    </div>
  </div>

  {{-- Daftar tagihan --}}
  <div class="col-12 col-xl-8">
    <div class="card h-100 d-flex flex-column">
      <div class="card-header pm-tabs-header d-flex align-items-center justify-content-between gap-2">
        <ul class="nav nav-tabs pm-tabs" role="tablist">
          <li class="nav-item">
            <a class="nav-link @if ($status === '') active @endif" href="{{ $urlStatus(null) }}">
              <i class="ti ti-list"></i><span>Semua</span>
              <span class="badge rounded-pill bg-label-primary">{{ number_format($ringkasan['total'], 0, ',', '.') }}</span>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link @if ($status === '0') active @endif" href="{{ $urlStatus('0') }}">
              <i class="ti ti-clock"></i><span>{{ $STATUS['0'] }}</span>
              <span class="badge rounded-pill bg-warning">{{ number_format($belum, 0, ',', '.') }}</span>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link @if ($status === '1') active @endif" href="{{ $urlStatus('1') }}">
              <i class="ti ti-circle-check"></i><span>{{ $STATUS['1'] }}</span>
              <span class="badge rounded-pill bg-success">{{ number_format($ringkasan['lunas'], 0, ',', '.') }}</span>
            </a>
          </li>
        </ul>
        <button type="button" id="btn-export" class="btn btn-sm btn-label-success flex-shrink-0" title="Unduh Excel sesuai filter">
          <i class="ti ti-file-spreadsheet ti-xs"></i><span class="d-none d-sm-inline ms-1">Excel</span>
        </button>
      </div>

      <div class="pm-filter px-3 py-3 border-bottom">
        <form id="filter-pmb" method="GET" action="{{ route('operator.tagihan-pmb.index') }}" class="row g-2 align-items-end">
          @if ($status !== '')
          <input type="hidden" name="status" value="{{ $status }}">
          @endif
          <div class="col-6 col-md-2">
            <label class="form-label" for="tahun">Tahun Masuk</label>
            <select id="tahun" name="tahun" class="form-select">
              <option value="">Semua</option>
              @foreach ($tahun as $t)
              <option value="{{ $t }}" @selected(($filter['tahun'] ?? '') == $t)>{{ $t }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-6 col-md-2 order-md-last">
            <label class="form-label" for="limit">Per halaman</label>
            <select id="limit" name="limit" class="form-select">
              @foreach ([10, 25, 50, 100] as $n)
              <option value="{{ $n }}" @selected(($filter['limit'] ?? 25) == $n)>{{ $n }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-12 col-md-4">
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
          <div class="col-12 col-md-4">
            <label class="form-label" for="search">Cari</label>
            <div class="input-group input-group-merge">
              <span class="input-group-text"><i class="ti ti-search"></i></span>
              <input type="search" id="search" name="search" class="form-control"
                placeholder="Nomor atau nama, lalu Enter" value="{{ $filter['search'] ?? '' }}">
            </div>
          </div>
        </form>
      </div>

      <div class="pm-scroll flex-grow-1">
        <table class="table table-sm table-hover small mb-0 pm-table">
          <thead>
            <tr>
              <th class="d-none d-sm-table-cell">#</th>
              <th>Peserta</th>
              <th class="d-none d-md-table-cell">Prodi</th>
              <th class="d-none d-lg-table-cell">NPM</th>
              <th class="text-end">Tagihan</th>
              <th class="text-center">Status</th>
              <th class="text-end pm-aksi">Aksi</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($data as $i => $row)
            @php($nama = trim((string) $row->nama_lengkap))
            @php($prodiNama = trim($row->jenjang_didik) . ' ' . trim($row->nm_lemb))
            <tr data-nomor="{{ $row->no_tes }}">
              <td class="d-none d-sm-table-cell text-muted">{{ $data->firstItem() + $i }}</td>
              <td class="pm-nama">
                <span class="pm-no d-block">{{ $row->no_tes }} · {{ (int) $row->thn_ajaran }}</span>
                <span class="fw-medium">{{ $nama }}</span>
                <small class="d-block d-md-none text-muted">{{ $prodiNama }}</small>
              </td>
              <td class="d-none d-md-table-cell">{{ $prodiNama }}</td>
              <td class="d-none d-lg-table-cell text-nowrap">{{ $row->nipd_register ?: '—' }}</td>
              <td class="text-end text-nowrap fw-medium">{{ number_format((float) $row->registrasi_uang, 0, ',', '.') }}</td>
              <td class="text-center">
                @if ((int) $row->lunas === 1)
                <span class="badge bg-label-success">Lunas</span>
                @else
                <span class="badge bg-label-warning">Belum</span>
                @endif
              </td>
              <td class="text-end pm-aksi">
                <button type="button" class="btn btn-sm btn-icon btn-label-primary" title="Buka peserta" data-pilih="{{ $row->no_tes }}">
                  <i class="ti ti-{{ (int) $row->lunas === 1 ? 'eye' : 'pencil' }}"></i>
                </button>
                @if ((int) $row->lunas !== 1)
                <button type="button" class="btn btn-sm btn-icon btn-label-success" title="Tutup tagihan (tandai lunas)"
                  data-tutup="{{ $row->no_tes }}" data-nama="{{ $nama }}">
                  <i class="ti ti-key"></i>
                </button>
                @endif
              </td>
            </tr>
            @empty
            <tr>
              <td colspan="7" class="p-0">
                <div class="pm-empty"><i class="ti ti-receipt-off"></i>Tidak ada tagihan yang cocok dengan filter.</div>
              </td>
            </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      @if ($data->total() > 0)
      <div class="card-footer border-top d-flex flex-wrap justify-content-between align-items-center gap-2 py-3">
        <small class="text-muted">
          {{ number_format($data->firstItem(), 0, ',', '.') }}–{{ number_format($data->lastItem(), 0, ',', '.') }}
          dari {{ number_format($data->total(), 0, ',', '.') }} tagihan
        </small>
        {{ $data->onEachSide(1)->links('pagination::bootstrap-5') }}
      </div>
      @endif
    </div>
  </div>
</div>
@endsection
