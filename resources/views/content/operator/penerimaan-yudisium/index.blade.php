@extends('layouts/layoutMaster')

@section('title', 'Penerimaan Yudisium')

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
  .py-toolbar .form-label { font-size: .75rem; margin-bottom: .25rem; }
  .py-npm .form-control { font-weight: 600; letter-spacing: .03em; }

  /* Strip profil mahasiswa */
  .py-profil { border-top: 1px dashed var(--bs-border-color); }
  .py-avatar {
    width: 2.75rem; height: 2.75rem; flex: 0 0 2.75rem;
    display: grid; place-items: center; border-radius: 50%;
    font-weight: 700; letter-spacing: .02em;
  }
  .py-chip {
    display: inline-flex; align-items: center; gap: .3rem;
    padding: .2rem .6rem; border-radius: 50rem; font-size: .75rem;
    background: var(--bs-tertiary-bg, rgba(0,0,0,.04)); white-space: nowrap;
  }

  /* Filter & tabel */
  .py-filter { background: var(--bs-tertiary-bg, rgba(0,0,0,.015)); }
  .py-filter .form-label { font-size: .75rem; margin-bottom: .25rem; }
  .py-scroll {
    max-height: max(320px, calc(100vh - 470px));
    overflow-y: auto; overflow-x: hidden;
  }
  .py-scroll thead th {
    position: sticky; top: 0; z-index: 2;
    background: var(--bs-card-bg, var(--bs-body-bg));
    box-shadow: inset 0 -1px 0 var(--bs-border-color);
    white-space: nowrap;
  }
  .py-table td { vertical-align: middle; }
  .py-table .py-nama { overflow-wrap: anywhere; word-break: break-word; }
  .py-row-aktif { background: rgba(var(--bs-success-rgb), .08); }
  .py-row-aktif td:first-child { box-shadow: inset 3px 0 0 var(--bs-success); }
  .py-empty { padding: 2.25rem 1rem; text-align: center; color: var(--bs-secondary-color); }
  .py-empty i { font-size: 2rem; display: block; margin-bottom: .4rem; opacity: .5; }

  @media (max-width: 575.98px) {
    .py-scroll { max-height: none; }
  }
</style>
@endsection

@section('page-script')
<script>
  $(function () {
    var URL = {
      mahasiswa: @json(route('operator.penerimaan-yudisium.mahasiswa')),
      simpan: @json(route('operator.penerimaan-yudisium.store')),
      kpp: @json(route('operator.tagihan.kpp', '__ID__')),
    };

    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'), Accept: 'application/json' } });

    function pesanGalat(xhr) {
      var j = xhr.responseJSON || {};
      if (j.errors) return Object.values(j.errors)[0][0];
      return j.message || 'Terjadi kesalahan.';
    }
    function galat(xhr) { Swal.fire({ icon: 'error', title: 'Gagal', text: typeof xhr === 'string' ? xhr : pesanGalat(xhr) }); }
    function inisial(nama) {
      return String(nama || '?').trim().split(/\s+/).slice(0, 2).map(function (s) { return s[0]; }).join('').toUpperCase();
    }
    function tanggal(t) {
      return new Date(t + 'T00:00:00').toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
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
    $('#filter-yudisium select, #filter-yudisium input[type="date"]').on('change', function () { this.form.submit(); });

    /* ---------------- Mahasiswa ---------------- */

    var mhs = null, timer, cariXhr;

    function resetMahasiswa(pesan) {
      mhs = null;
      $('#profil-isi').addClass('d-none');
      $('#profil-kosong').removeClass('d-none').find('span').text(pesan || 'Ketik NPM mahasiswa untuk memulai.');
      $('#btn-lunas, #btn-kpp').prop('disabled', true);
      $('#stat-mhs').text('—');
      $('.py-table tr').removeClass('py-row-aktif');
    }

    function tampil(d) {
      mhs = d;
      $('#p-avatar').text(inisial(d.nmmhs));
      $('#p-nama').text(d.nmmhs);
      $('#p-npm').text(d.npm);
      $('#p-prodi').text(d.nmprg);
      $('#p-angkatan').text(d.thmsk);
      $('#p-semester').text(d.smmhs);
      $('#p-status').html(d.lunas
        ? '<span class="badge bg-label-success"><i class="ti ti-circle-check ti-xs me-1"></i>Lunas ' + tanggal(d.lunas) + '</span>'
        : '<span class="badge bg-label-warning"><i class="ti ti-clock ti-xs me-1"></i>Belum lunas</span>');
      $('#stat-mhs').text(d.npm);
      $('#profil-kosong').addClass('d-none'); $('#profil-isi').removeClass('d-none');
      $('#btn-kpp').prop('disabled', false);
      $('#btn-lunas').prop('disabled', !!d.lunas);
      $('.py-table tr').removeClass('py-row-aktif');
      $('.py-table tr[data-npm="' + d.npm.replace(/"/g, '') + '"]').addClass('py-row-aktif');
    }

    function cariMahasiswa() {
      var npm = $.trim($('#npm').val());
      if (npm.length <= 7) { if (mhs) resetMahasiswa(); return; }

      if (cariXhr) cariXhr.abort();
      $('#npm-spinner').removeClass('d-none');
      cariXhr = $.get(URL.mahasiswa, { npm: npm, id_smt: $('#id_smt').val() })
        .done(tampil)
        .fail(function (xhr) { if (xhr.statusText !== 'abort') resetMahasiswa('NPM ' + npm + ' tidak ditemukan.'); })
        .always(function () { $('#npm-spinner').addClass('d-none'); });
    }

    $('#npm')
      .on('input', function () { clearTimeout(timer); timer = setTimeout(cariMahasiswa, 350); })
      .on('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); clearTimeout(timer); cariMahasiswa(); } });
    $('#id_smt').on('change', cariMahasiswa);
    $('#btn-reset').on('click', function () { $('#npm').val('').trigger('focus'); resetMahasiswa(); });

    /* ---------------- Klik lunas ---------------- */

    $('#form-lunas').on('submit', function (e) {
      e.preventDefault();
      if (!mhs) return galat('NPM mahasiswa belum diisi.');

      Swal.fire({
        icon: 'question', title: 'Klik lunas yudisium?',
        html: '<b>' + $('<div>').text(mhs.nmmhs).html() + '</b><br><small>' + mhs.npm + ' · ' + $('<div>').text(mhs.nmprg).html() + '</small>',
        showCancelButton: true, confirmButtonText: 'Ya, lunas', cancelButtonText: 'Batal',
        customClass: { confirmButton: 'btn btn-success me-2', cancelButton: 'btn btn-label-secondary' }, buttonsStyling: false,
      }).then(function (r) {
        if (!r.isConfirmed) return;
        var $btn = $('#btn-lunas').prop('disabled', true);
        $btn.find('.spinner-border').removeClass('d-none');
        $.post(URL.simpan, { npm: mhs.npm })
          .done(function (d) {
            Swal.fire({ icon: 'success', title: d.message, timer: 1300, showConfirmButton: false })
              .then(function () { location.reload(); });
          })
          .fail(function (xhr) { $btn.prop('disabled', false); galat(xhr); })
          .always(function () { $btn.find('.spinner-border').addClass('d-none'); });
      });
    });

    /* ---------------- KPP ---------------- */

    $('#btn-kpp').on('click', function () {
      if (!mhs) return;
      $('#kpp-isi').html('<div class="py-empty"><i class="ti ti-loader-2"></i>Memuat KPP…</div>');
      $('#modalKppLabel').text('KPP · ' + mhs.npm + ' — ' + mhs.nmmhs);
      bootstrap.Modal.getOrCreateInstance(document.getElementById('modalKpp')).show();
      $.get(URL.kpp.replace('__ID__', mhs.idreg)).done(function (html) { $('#kpp-isi').html(html); }).fail(galat);
    });

    /* ---------------- Baris tabel ---------------- */

    $(document).on('click', '[data-pilih]', function () {
      $('#npm').val($(this).data('pilih'));
      clearTimeout(timer); cariMahasiswa();
      $('html, body').animate({ scrollTop: $('#card-toolbar').offset().top - 90 }, 200);
    });

    resetMahasiswa();
  });
</script>
@endsection

@section('content')
<div class="pg-header mb-4">
  <div class="pg-header-main">
    <div class="pg-header-icon">
      <i class="ti ti-cash-register"></i>
    </div>
    <div class="flex-grow-1 min-w-0">
      <div class="pg-header-crumb">
        <a href="{{ route('operator.dashboard') }}">Dashboard</a>
        <i class="ti ti-chevron-right"></i>
        <span>Penerimaan Struk</span>
        <i class="ti ti-chevron-right"></i>
        <span>Penerimaan Yudisium</span>
      </div>
      <h4 class="pg-header-title">Penerimaan Yudisium</h4>
      <p class="pg-header-sub">Catat pelunasan biaya yudisium mahasiswa (klik lunas).</p>
    </div>
  </div>

  <div class="pg-header-stats">
    <div class="pg-stat">
      <span class="pg-stat-icon"><i class="ti ti-user"></i></span>
      <div>
        <strong id="stat-mhs">—</strong>
        <small>Mahasiswa</small>
      </div>
    </div>
    <div class="pg-stat">
      <span class="pg-stat-icon is-success"><i class="ti ti-calendar-check"></i></span>
      <div>
        <strong>{{ number_format($ringkasan['hari_ini'], 0, ',', '.') }}</strong>
        <small>Lunas hari ini</small>
      </div>
    </div>
    <div class="pg-stat">
      <span class="pg-stat-icon is-info"><i class="ti ti-calendar-month"></i></span>
      <div>
        <strong>{{ number_format($ringkasan['bulan_ini'], 0, ',', '.') }}</strong>
        <small>Lunas bulan ini</small>
      </div>
    </div>
    <div class="pg-stat">
      <span class="pg-stat-icon is-danger"><i class="ti ti-school"></i></span>
      <div>
        <strong>{{ number_format($ringkasan['total'], 0, ',', '.') }}</strong>
        <small>Total pelunasan</small>
      </div>
    </div>
  </div>
</div>

{{-- Bar kontrol + profil mahasiswa --}}
<div class="card mb-3 py-toolbar" id="card-toolbar">
  <div class="card-body pb-3">
    <form id="form-lunas" class="row g-2 align-items-end" autocomplete="off">
      <div class="col-12 col-sm-6 col-lg-4 py-npm">
        <label class="form-label" for="npm">NPM Mahasiswa</label>
        <div class="input-group input-group-merge">
          <span class="input-group-text"><i class="ti ti-search"></i></span>
          <input type="text" id="npm" class="form-control" placeholder="Ketik NPM, lalu Enter" inputmode="numeric">
          <span class="input-group-text">
            <span class="spinner-border spinner-border-sm text-primary d-none" id="npm-spinner" role="status"></span>
          </span>
        </div>
      </div>
      <div class="col-12 col-sm-6 col-lg-3">
        <label class="form-label" for="id_smt">Tahun Akademik</label>
        <select id="id_smt" class="form-select">
          @foreach ($semester as $id => $nama)
          <option value="{{ $id }}" @selected($id == $semesterAktif)>{{ $nama }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-12 col-lg-5 d-flex gap-2">
        <button type="submit" id="btn-lunas" class="btn btn-success flex-grow-1" disabled>
          <span class="spinner-border spinner-border-sm me-1 d-none" role="status"></span>
          <i class="ti ti-circle-check ti-xs me-1"></i>Klik Lunas
        </button>
        <button type="button" id="btn-kpp" class="btn btn-label-warning" disabled title="Lihat KPP">
          <i class="ti ti-history ti-xs"></i><span class="d-none d-sm-inline ms-1">KPP</span>
        </button>
        <button type="button" id="btn-reset" class="btn btn-label-secondary" title="Kosongkan">
          <i class="ti ti-refresh ti-xs"></i>
        </button>
      </div>
    </form>
  </div>

  <div class="py-profil px-4 py-3">
    <div id="profil-kosong" class="d-flex align-items-center gap-2 text-muted small">
      <i class="ti ti-user-question"></i><span></span>
    </div>
    <div id="profil-isi" class="d-none d-flex flex-wrap align-items-center gap-3">
      <div class="d-flex align-items-center gap-3 min-w-0 me-auto">
        <div class="py-avatar bg-label-primary" id="p-avatar"></div>
        <div class="min-w-0">
          <div class="fw-semibold text-truncate" id="p-nama"></div>
          <div class="small text-muted text-truncate"><span id="p-npm"></span> · <span id="p-prodi"></span></div>
        </div>
      </div>
      <div class="d-flex flex-wrap align-items-center gap-2">
        <span class="py-chip"><i class="ti ti-calendar ti-xs"></i>Angkatan <strong id="p-angkatan"></strong></span>
        <span class="py-chip"><i class="ti ti-stairs-up ti-xs"></i>Semester <strong id="p-semester"></strong></span>
        <span id="p-status"></span>
      </div>
    </div>
  </div>
</div>

{{-- Daftar pelunasan --}}
<div class="card">
  <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2 py-3">
    <div>
      <h6 class="mb-0"><i class="ti ti-list-check ti-xs me-1"></i>Riwayat Pelunasan Yudisium
        <span class="badge rounded-pill bg-label-primary">{{ number_format($data->total(), 0, ',', '.') }}</span></h6>
      <small class="text-muted">Satu kali per mahasiswa, terbaru di atas.</small>
    </div>
    <a href="{{ route('operator.penerimaan-yudisium.index') }}" class="btn btn-sm btn-label-secondary">
      <i class="ti ti-filter-off ti-xs me-1"></i>Reset filter
    </a>
  </div>

  <div class="py-filter px-3 py-3 border-top border-bottom">
    <form id="filter-yudisium" method="GET" action="{{ route('operator.penerimaan-yudisium.index') }}" class="row g-2 align-items-end">
      <div class="col-12 col-md-4 col-xl-3">
        <label class="form-label" for="search">Cari</label>
        <div class="input-group input-group-merge">
          <span class="input-group-text"><i class="ti ti-search"></i></span>
          <input type="search" id="search" name="search" class="form-control"
            placeholder="NPM atau nama, lalu Enter" value="{{ $filter['search'] ?? '' }}">
        </div>
      </div>
      <div class="col-12 col-md-4 col-xl-3">
        <label class="form-label" for="psstudi">Program Studi</label>
        <select id="psstudi" name="psstudi" class="form-select select2-filter">
          <option value="">Semua prodi</option>
          @foreach ($prodi as $kode => $nama)
          <option value="{{ $kode }}" @selected(($filter['psstudi'] ?? '') === (string) $kode)>{{ $nama }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-6 col-md-2 col-xl-2">
        <label class="form-label" for="dari">Dari tanggal</label>
        <input type="date" id="dari" name="dari" class="form-control" value="{{ $filter['dari'] ?? '' }}">
      </div>
      <div class="col-6 col-md-2 col-xl-2">
        <label class="form-label" for="sampai">Sampai tanggal</label>
        <input type="date" id="sampai" name="sampai" class="form-control" value="{{ $filter['sampai'] ?? '' }}">
      </div>
      <div class="col-6 col-md-2 col-xl-2">
        <label class="form-label" for="limit">Per halaman</label>
        <select id="limit" name="limit" class="form-select">
          @foreach ([10, 25, 50, 100] as $n)
          <option value="{{ $n }}" @selected(($filter['limit'] ?? 25) == $n)>{{ $n }}</option>
          @endforeach
        </select>
      </div>
    </form>
  </div>

  <div class="py-scroll">
    <table class="table table-sm table-hover small mb-0 py-table">
      <thead>
        <tr>
          <th class="d-none d-sm-table-cell">#</th>
          <th>Tanggal</th>
          <th>Mahasiswa</th>
          <th class="d-none d-md-table-cell">Program Studi</th>
          <th class="text-end">Aksi</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($data as $i => $row)
        @php($prodiNama = trim((string) $row->nama_prg))
        <tr data-npm="{{ trim($row->npm) }}">
          <td class="d-none d-sm-table-cell text-muted">{{ $data->firstItem() + $i }}</td>
          <td class="text-nowrap">
            {{ $row->tanggal_lns ? \Illuminate\Support\Carbon::parse($row->tanggal_lns)->translatedFormat('d M Y') : '—' }}
          </td>
          <td class="py-nama">
            <span class="fw-medium d-block">{{ trim((string) $row->nama_mhs) }}</span>
            <small class="text-muted">{{ trim($row->npm) }}</small>
            <small class="d-block d-md-none text-muted">{{ $prodiNama }}</small>
          </td>
          <td class="d-none d-md-table-cell">{{ $prodiNama ?: '—' }}</td>
          <td class="text-end">
            <button type="button" class="btn btn-sm btn-icon btn-label-primary" title="Buka mahasiswa" data-pilih="{{ trim($row->npm) }}">
              <i class="ti ti-eye"></i>
            </button>
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="5" class="p-0">
            <div class="py-empty"><i class="ti ti-receipt-off"></i>Tidak ada pelunasan yang cocok dengan filter.</div>
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
      dari {{ number_format($data->total(), 0, ',', '.') }} pelunasan
    </small>
    {{ $data->onEachSide(1)->links('pagination::bootstrap-5') }}
  </div>
  @endif
</div>

{{-- Modal KPP --}}
<div class="modal fade" id="modalKpp" tabindex="-1" aria-labelledby="modalKppLabel" aria-hidden="true">
  <div class="modal-dialog modal-fullscreen-lg-down modal-xl modal-dialog-scrollable" style="--bs-modal-width: 96vw;">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalKppLabel">KPP Mahasiswa</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body p-0" id="kpp-isi"></div>
    </div>
  </div>
</div>
@endsection
