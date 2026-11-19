@extends('layouts/layoutMaster')

@section('title', 'Struk Tagihan')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset(mix('assets/vendor/libs/sweetalert2/sweetalert2.css')) }}">
@endsection

@section('vendor-script')
<script src="{{ asset(mix('assets/vendor/libs/sweetalert2/sweetalert2.js')) }}"></script>
@endsection

@section('page-style')
<style>
  /* Tinggi area gulir mengikuti layar: pas untuk notebook 11" (≈768px). */
  .tg-scroll {
    max-height: max(280px, calc(100vh - 470px));
    overflow-y: auto;
    overflow-x: hidden;   /* tidak pernah scroll ke samping */
  }
  /* Bootstrap tidak punya min-w-0: tanpa ini isi flex tidak bisa menyusut. */
  .min-w-0 { min-width: 0 !important; }
  /* Teks panjang (nama rekening, keterangan) dibungkus, bukan melebar. */
  .tg-biaya-item .nama,
  .tg-table td:first-child { overflow-wrap: anywhere; word-break: break-word; }
  .tg-biaya-item .uang { flex-shrink: 0; }
  .tg-table { table-layout: fixed; width: 100%; }
  .tg-table th:not(:first-child), .tg-table td:not(:first-child) { width: 7.5rem; }
  .tg-table .tg-aksi { width: 5.25rem !important; }
  /* Judul kolom satu baris (tidak turun ke bawah); bila sempit dipotong "…". */
  .tg-table thead th { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .tg-riwayat th:nth-child(1), .tg-riwayat td:nth-child(1) { width: 8rem; }
  /* Semester dipersempit; Sarat dilebarkan; Status mengambil sisa lebar. */
  .tg-riwayat th:nth-child(2), .tg-riwayat td:nth-child(2) { width: auto; }
  .tg-riwayat th:nth-child(3), .tg-riwayat td:nth-child(3),
  .tg-riwayat th:nth-child(6), .tg-riwayat td:nth-child(6) { width: 9rem; }
  .tg-riwayat th:nth-child(4), .tg-riwayat td:nth-child(4) { width: 9rem; }
  .tg-riwayat th:nth-child(5), .tg-riwayat td:nth-child(5) { width: 9rem; }
  .tg-riwayat th:nth-child(8), .tg-riwayat td:nth-child(8) { width: 15.5rem; }
  .tg-riwayat .tg-aksi { width: 10.5rem !important; }
  .tg-scroll thead th {
    position: sticky; top: 0; z-index: 2;
    background: var(--bs-card-bg, var(--bs-body-bg));
    box-shadow: inset 0 -1px 0 var(--bs-border-color);
  }
  .tg-table td, .tg-table th { vertical-align: middle; }
  .tg-meta { font-size: .75rem; color: var(--bs-secondary-color); }
  .tg-aksi { white-space: nowrap; width: 1%; }
  .tg-aksi .btn { width: 1.9rem; height: 1.9rem; padding: 0; }

  /* Bar kontrol */
  .tg-toolbar .form-label { font-size: .75rem; margin-bottom: .25rem; }
  .tg-npm .form-control { font-weight: 600; letter-spacing: .03em; }
  .tg-sarat .btn { flex: 1 1 0; white-space: nowrap; }

  /* Strip profil mahasiswa */
  .tg-profil { border-top: 1px dashed var(--bs-border-color); }
  .tg-avatar {
    width: 2.75rem; height: 2.75rem; flex: 0 0 2.75rem;
    display: grid; place-items: center; border-radius: 50%;
    font-weight: 700; letter-spacing: .02em;
  }
  .tg-chip {
    display: inline-flex; align-items: center; gap: .3rem;
    padding: .2rem .6rem; border-radius: 50rem; font-size: .75rem;
    background: var(--bs-tertiary-bg, rgba(0,0,0,.04)); white-space: nowrap;
  }

  /* Rincian biaya prodi */
  .tg-biaya-item { padding: .55rem .9rem; transition: background .15s; }
  .tg-biaya-item:hover { background: var(--bs-tertiary-bg, rgba(0,0,0,.03)); }
  /* Nama tagihan: lebih kecil, maksimal 2 baris lalu "…" (nama lengkap di title). */
  .tg-biaya-item .nama {
    font-size: .75rem; line-height: 1.25;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
  }
  .tg-biaya-item .uang { font-size: .78rem; font-weight: 600; white-space: nowrap; }
  /* Tombol aksi di daftar (flex) selalu selebar isinya, tidak ikut width 1% sel tabel. */
  .tg-biaya-item .tg-aksi { width: auto; flex: 0 0 auto; }

  .tg-row-aktif { background: rgba(var(--bs-success-rgb), .06); }
  .tg-empty { padding: 2.25rem 1rem; text-align: center; color: var(--bs-secondary-color); }
  .tg-empty i { font-size: 2rem; display: block; margin-bottom: .4rem; opacity: .5; }

  /* Tab di header kartu: sejajar dengan tinggi header "Biaya Prodi",
     garis bawah tab aktif menempel ke garis header, bisa digeser di layar sempit. */
  :root { --tg-head-h: 4rem; }
  .tg-head { min-height: var(--tg-head-h); }
  .tg-tabs-header {
    padding: 0 1rem;
    border-bottom: 1px solid var(--bs-border-color);
  }
  .tg-tabs {
    flex-wrap: nowrap;
    overflow-x: auto;
    overflow-y: hidden;
    border-bottom: 0;
    margin-bottom: -1px;
    scrollbar-width: none;
    gap: .25rem;
  }
  .tg-tabs::-webkit-scrollbar { display: none; }
  .tg-tabs .nav-link {
    display: inline-flex; align-items: center; gap: .4rem;
    min-height: var(--tg-head-h);
    padding: 0 .9rem;
    font-size: .8125rem; font-weight: 500;
    white-space: nowrap;
    border: 0;
    border-bottom: 2px solid transparent;
    border-radius: 0;
    background: transparent;
    color: var(--bs-secondary-color);
  }
  .tg-tabs .nav-link i { font-size: 1.05rem; }
  .tg-tabs .nav-link:hover { color: var(--bs-primary); }
  .tg-tabs .nav-link.active {
    color: var(--bs-primary);
    border-bottom-color: var(--bs-primary);
    box-shadow: none;
  }
  .tg-tabs .badge { font-size: .65rem; line-height: 1; padding: .3em .55em; }
  .tg-total { font-size: 1.15rem; font-weight: 700; }
</style>
@endsection

@section('page-script')
<script>
  $(function () {
    var URL = {
      mahasiswa: @json(route('operator.tagihan.mahasiswa')),
      proses: @json(route('operator.tagihan.proses')),
      biaya: @json(route('operator.tagihan.biaya')),
      biayaUpdate: @json(route('operator.tagihan.biaya.update', '__ID__')),
      detail: @json(route('operator.tagihan.detail', '__ID__')),
      kpp: @json(route('operator.tagihan.kpp', '__ID__')),
      rincianStore: @json(route('operator.tagihan.rincian.store')),
      strukHapus: @json(route('operator.tagihan.struk.destroy', '__ID__')),
      rincian: @json(route('operator.tagihan.rincian.update', '__ID__')),
      dispenPakai: @json(route('operator.tagihan.dispensasi.pakai')),
      dispenHapus: @json(route('operator.tagihan.dispensasi.destroy')),
      prasaratStore: @json(route('operator.tagihan.prasarat.store')),
      prasarat: @json(route('operator.tagihan.prasarat.update', '__ID__')),
    };
    var SARAT = { 0: 'Non', 1: 'Ent KRS', 2: 'Cet KPU' };

    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'), Accept: 'application/json' } });

    // State halaman: mahasiswa terpilih dan struk aktif.
    var mhs = null, idTagihan = null;

    function url(k, id) { return URL[k].replace('__ID__', id); }
    function esc(s) {
      return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
      });
    }
    function rp(n) { return Number(n || 0).toLocaleString('id-ID'); }
    function sarat() { return $('input[name="sarat"]:checked').val(); }
    function pesanGalat(xhr) {
      var j = xhr.responseJSON || {};
      if (j.errors) return Object.values(j.errors)[0][0];
      return j.message || 'Terjadi kesalahan.';
    }
    function galat(xhr) { Swal.fire({ icon: 'error', title: 'Gagal', text: typeof xhr === 'string' ? xhr : pesanGalat(xhr) }); }
    function toast(t) {
      Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: t, timer: 1600, showConfirmButton: false });
    }
    function yakin(judul, teks) {
      return Swal.fire({
        icon: 'warning', title: judul, text: teks, showCancelButton: true,
        confirmButtonText: 'Ya', cancelButtonText: 'Batal',
        customClass: { confirmButton: 'btn btn-danger me-2', cancelButton: 'btn btn-label-secondary' }, buttonsStyling: false,
      }).then(function (r) { return r.isConfirmed; });
    }
    function kosong(ikon, teks) { return '<div class="tg-empty"><i class="ti ti-' + ikon + '"></i>' + teks + '</div>'; }
    function kosongTr($tb, cols, ikon, teks) { $tb.html('<tr><td colspan="' + cols + '" class="p-0">' + kosong(ikon, teks) + '</td></tr>'); }
    function inisial(nama) {
      return String(nama || '?').trim().split(/\s+/).slice(0, 2).map(function (s) { return s[0]; }).join('').toUpperCase();
    }
    function hitung(id, n) { $(id).text(n).toggleClass('d-none', !n); }

    /* ---------------- Mahasiswa ---------------- */

    function resetMahasiswa(pesan) {
      mhs = null; setStruk(null);
      $('#profil-isi').addClass('d-none');
      $('#profil-kosong').removeClass('d-none').find('span').text(pesan || 'Ketik NPM mahasiswa untuk memulai.');
      $('#btn-kpp, #btn-prasarat-tambah, #btn-proses').prop('disabled', true);
      $('#info-prodi').text('—');
      $('#biaya-list').html(kosong('list-search', 'Biaya muncul setelah mahasiswa dipilih.'));
      kosongTr($('#tb-rincian'), 3, 'receipt-off', 'Belum ada mahasiswa.');
      kosongTr($('#tb-dispen'), 4, 'discount-off', 'Belum ada mahasiswa.');
      kosongTr($('#tb-prasarat'), 4, 'shield-off', 'Belum ada mahasiswa.');
      kosongTr($('#tb-riwayat'), 9, 'history-off', 'Belum ada mahasiswa.');
      $('#riwayat-ringkas').text('');
      $('#total-struk, #stat-total').text('Rp 0');
      $('#stat-mhs').text('—');
      hitung('#cnt-rincian', 0); hitung('#cnt-dispen', 0); hitung('#cnt-prasarat', 0); hitung('#cnt-biaya', 0); hitung('#cnt-riwayat', 0);
    }

    function setStruk(id) {
      idTagihan = id;
      $('#stat-struk').text(id ? 'Aktif · ' + SARAT[sarat()] : 'Belum diproses');
      $('#stat-struk-icon').toggleClass('is-success', !!id).toggleClass('is-danger', !id)
        .html('<i class="ti ti-' + (id ? 'circle-check' : 'clock') + '"></i>');
      $('#biaya-list').toggleClass('opacity-75', !id);
    }

    var timer, cariXhr;
    function cariMahasiswa() {
      var npm = $.trim($('#npm').val());
      if (npm.length <= 7) { if (mhs) resetMahasiswa(); return; }

      if (cariXhr) cariXhr.abort();
      $('#npm-spinner').removeClass('d-none');
      cariXhr = $.get(URL.mahasiswa, { npm: npm, id_smt: $('#id_smt').val() })
        .done(function (d) {
          mhs = d; setStruk(null);
          $('#p-avatar').text(inisial(d.nmmhs));
          $('#stat-mhs').text(d.npm);
          $('#p-nama').text(d.nmmhs);
          $('#p-npm').text(d.npm);
          $('#p-prodi').text(d.nmprg);
          $('#p-angkatan').text(d.thmsk);
          $('#p-semester').text(d.smmhs);
          $('#info-prodi').text(d.nmprg + ' · ' + d.thmsk);
          $('#profil-kosong').addClass('d-none'); $('#profil-isi').removeClass('d-none');
          $('#btn-kpp, #btn-prasarat-tambah, #btn-proses').prop('disabled', false);
          muatBiaya(); muatDetail();
        })
        .fail(function (xhr) { if (xhr.statusText !== 'abort') resetMahasiswa('NPM ' + npm + ' tidak ditemukan.'); })
        .always(function () { $('#npm-spinner').addClass('d-none'); });
    }

    $('#npm')
      .on('input', function () { clearTimeout(timer); timer = setTimeout(cariMahasiswa, 350); })
      .on('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); clearTimeout(timer); cariMahasiswa(); } });
    $('#id_smt').on('change', function () { $('#stat-semester').text($('#id_smt option:selected').text()); cariMahasiswa(); });
    $('#kelas').on('change', muatBiaya);
    $('input[name="sarat"]').on('change', function () { setStruk(null); muatBiaya(); });

    /* ---------------- Proses struk ---------------- */

    $('#form-proses').on('submit', function (e) {
      e.preventDefault();
      if (!mhs) return galat('NPM mahasiswa belum diisi.');

      var $btn = $('#btn-proses').prop('disabled', true);
      $btn.find('.spinner-border').removeClass('d-none');
      $.post(URL.proses, {
        id_reg_pd: mhs.idreg, id_sms: mhs.idsms, id_smt: $('#id_smt').val(),
        sarat: sarat(), smmhs: mhs.smmhs,
      })
        .done(function (d) { setStruk(d.id); muatDetail(); toast('Struk siap diisi'); })
        .fail(function (xhr) { setStruk(null); galat(xhr); })
        .always(function () { $btn.prop('disabled', false).find('.spinner-border').addClass('d-none'); });
    });

    /* ---------------- Rincian biaya prodi ---------------- */

    function muatBiaya() {
      if (!mhs) return;
      $('#biaya-list').html(kosong('loader-2', 'Memuat biaya…'));
      $.get(URL.biaya, { id_sms: mhs.idsms, angkatan: mhs.thmsk, kelas: $('#kelas').val(), sarat: sarat() })
        .done(function (rows) {
          hitung('#cnt-biaya', rows.length);
          if (!rows.length) return $('#biaya-list').html(kosong('mood-empty', 'Tidak ada biaya untuk prodi, angkatan, kelas, dan prasarat ini.'));
          $('#biaya-list').html(rows.map(function (r) {
            var tanda = (r.kredit == 1 || r.ma == 746) ? '<span class="badge bg-label-info ms-1">/SKS</span>'
              : (r.praktik == 1 ? '<span class="badge bg-label-info ms-1">/MK</span>' : '');
            return '<div class="tg-biaya-item d-flex align-items-center gap-2 border-bottom">' +
              '<div class="flex-grow-1 min-w-0"><div class="nama" title="' + esc($.trim(r.nama)) + '">' + esc($.trim(r.nama)) + '</div>' +
              '<div class="tg-meta">' + esc($.trim(r.kode)) + tanda + '</div></div>' +
              '<div class="uang">' + rp(r.uang) + '</div>' +
              '<div class="tg-aksi d-flex gap-1">' +
              '<button type="button" class="btn btn-icon btn-sm btn-text-secondary rounded-pill" data-biaya-edit="' + esc(JSON.stringify({ id: r.id, nama: $.trim(r.nama), uang: r.uang })) + '" title="Ubah nominal master biaya"><i class="ti ti-pencil ti-xs"></i></button>' +
              '<button type="button" class="btn btn-icon btn-sm btn-primary rounded-pill" data-biaya-tambah="' + esc(r.id) + '" title="Masukkan ke struk"><i class="ti ti-plus ti-xs"></i></button>' +
              '</div></div>';
          }).join(''));
        });
    }

    $(document).on('click', '[data-biaya-tambah]', function () {
      if (!idTagihan) return galat('Klik Proses Struk dulu.');
      var $b = $(this).prop('disabled', true);
      $.post(URL.rincianStore, { id_tagihan: idTagihan, id_reg_pd: mhs.idreg, id_biaya: $b.data('biaya-tambah'), id_smt: $('#id_smt').val() })
        .done(function () { muatDetail(); bootstrap.Tab.getOrCreateInstance(document.querySelector('#tab-struk')).show(); })
        .fail(galat)
        .always(function () { $b.prop('disabled', false); });
    });

    $(document).on('click', '[data-biaya-edit]', function () {
      var b = $(this).data('biaya-edit');
      bukaNominal({
        judul: 'Ubah Nominal Biaya', nama: b.nama, uang: b.uang, label: 'Nominal biaya',
        bantuan: 'Mengubah nominal master biaya (Data Biaya) untuk prodi, angkatan, dan kelas ini.',
        simpan: function (uang) {
          return $.ajax({ url: url('biayaUpdate', b.id), method: 'PUT', data: { uang: uang } }).done(muatBiaya);
        },
      });
    });

    /* ---------------- Detail: rincian struk, dispensasi, prasarat ---------------- */

    function muatDetail() {
      if (!mhs) return;
      $.get(url('detail', mhs.idreg)).done(function (d) {
        tampilRincian(d.rincian); tampilDispen(d.dispensasi); tampilPrasarat(d.prasarat); tampilRiwayat(d.riwayat);
      });
    }

    function tampilRincian(rows) {
      var $tb = $('#tb-rincian'), total = 0;
      hitung('#cnt-rincian', rows.length);
      if (!rows.length) {
        $('#total-struk, #stat-total').text('Rp 0');
        return kosongTr($tb, 3, 'receipt', 'Belum ada rincian struk yang belum lunas.');
      }

      $tb.html(rows.map(function (r) {
        total += Number(r.uang);
        var jurnal = r.post_piutang === '1', aktif = r.id_tagihan === idTagihan;
        return '<tr class="' + (aktif ? 'tg-row-aktif' : '') + '">' +
          '<td><div class="fw-medium">' + esc(r.keterangan) + '</div>' +
          '<div class="tg-meta">Sem ' + esc(r.mhssmt) + ' · ' + esc(SARAT[r.sarat] || '-') + ' · ' + esc($.trim(r.thnsmt)) +
          (jurnal ? ' · <span class="text-warning"><i class="ti ti-lock ti-xs"></i> dijurnal</span>' : '') + '</div></td>' +
          '<td class="text-end text-nowrap fw-medium">' + rp(r.uang) + '</td>' +
          '<td class="tg-aksi text-end">' +
          '<button type="button" class="btn btn-icon btn-sm btn-text-primary rounded-pill" data-rincian-edit="' + esc(JSON.stringify({ id: r.id, nama: r.keterangan, uang: r.uang })) + '" title="Ubah nominal (selisih jadi dispensasi)"><i class="ti ti-pencil ti-xs"></i></button>' +
          (jurnal
            ? '<span class="btn btn-icon btn-sm btn-text-secondary rounded-pill disabled" title="Sudah dijurnal piutang"><i class="ti ti-lock ti-xs"></i></span>'
            : '<button type="button" class="btn btn-icon btn-sm btn-text-danger rounded-pill" data-rincian-hapus="' + esc(r.id) + '" title="Hapus rincian"><i class="ti ti-trash ti-xs"></i></button>') +
          '</td></tr>';
      }).join(''));
      $('#total-struk, #stat-total').text('Rp ' + rp(total));
    }

    $(document).on('click', '[data-rincian-edit]', function () {
      var r = $(this).data('rincian-edit');
      bukaNominal({
        judul: 'Ubah Rincian Struk', nama: r.nama, uang: r.uang, label: 'Nominal ditagihkan',
        bantuan: 'Selisih dari nominal awal dicatat sebagai dispensasi dan bisa ditagihkan kemudian.',
        simpan: function (uang) {
          return $.ajax({ url: url('rincian', r.id), method: 'PUT', data: { uang: uang } }).done(muatDetail);
        },
      });
    });

    $(document).on('click', '[data-rincian-hapus]', function () {
      var id = $(this).data('rincian-hapus');
      yakin('Hapus rincian?', 'Rincian ini akan dihapus dari struk.').then(function (ok) {
        if (ok) $.ajax({ url: url('rincian', id), method: 'DELETE' }).done(muatDetail).fail(galat);
      });
    });

    function tampilDispen(rows) {
      var $tb = $('#tb-dispen');
      hitung('#cnt-dispen', rows.length);
      if (!rows.length) return kosongTr($tb, 4, 'discount', 'Tidak ada dispensasi.');
      $tb.html(rows.map(function (r) {
        var data = esc(JSON.stringify({ ma: r.ma, thnsmt: $.trim(r.thnsmt) }));
        return '<tr><td><div class="fw-medium">' + esc(r.uraian) + '</div><div class="tg-meta">' + esc($.trim(r.thnsmt)) + '</div></td>' +
          '<td class="text-end text-nowrap text-muted">' + rp(r.uang_pokok) + '</td>' +
          '<td class="text-end text-nowrap fw-medium text-warning">' + rp(r.uang_potongan) + '</td>' +
          '<td class="tg-aksi text-end">' +
          '<button type="button" class="btn btn-icon btn-sm btn-text-success rounded-pill" data-dispen-pakai="' + data + '" title="Masukkan sisa ke struk"><i class="ti ti-arrow-bar-to-right ti-xs"></i></button>' +
          (r.post_piutang === '1' ? '' : '<button type="button" class="btn btn-icon btn-sm btn-text-danger rounded-pill" data-dispen-hapus="' + data + '" title="Hapus dispensasi"><i class="ti ti-trash ti-xs"></i></button>') +
          '</td></tr>';
      }).join(''));
    }

    $(document).on('click', '[data-dispen-pakai]', function () {
      if (!idTagihan) return galat('Klik Proses Struk dulu.');
      var d = $(this).data('dispen-pakai');
      $.post(URL.dispenPakai, { id_tagihan: idTagihan, id_reg_pd: mhs.idreg, ma: d.ma, thnsmt: d.thnsmt })
        .done(function () { muatDetail(); toast('Dispensasi masuk ke struk'); }).fail(galat);
    });

    $(document).on('click', '[data-dispen-hapus]', function () {
      var d = $(this).data('dispen-hapus');
      yakin('Hapus dispensasi?', 'Apakah Anda yakin ingin menghapus dispensasi ini?').then(function (ok) {
        if (ok) $.ajax({ url: URL.dispenHapus, method: 'DELETE', data: { id_reg_pd: mhs.idreg, ma: d.ma, thnsmt: d.thnsmt } }).done(muatDetail).fail(galat);
      });
    });

    function tampilPrasarat(rows) {
      var $tb = $('#tb-prasarat');
      hitung('#cnt-prasarat', rows.length);
      if (!rows.length) return kosongTr($tb, 4, 'shield', 'Belum ada status prasarat.');
      function status(v, tgl) {
        return v == 1
          ? '<span class="badge bg-label-success">Enable</span>' + (tgl ? '' : ' <span class="tg-meta">(BAAK)</span>')
          : '<span class="badge bg-label-secondary">Disable</span>';
      }
      $tb.html(rows.map(function (r) {
        return '<tr><td class="fw-medium">' + esc(r.id_smt) + '</td>' +
          '<td>' + status(r.lunas_reg, r.tgl_setor) + '</td><td>' + status(r.lunas_sks, r.tgl_pelunasan) + '</td>' +
          '<td class="tg-aksi text-end"><button type="button" class="btn btn-icon btn-sm btn-text-primary rounded-pill" data-prasarat-edit="' + esc(JSON.stringify(r)) + '" title="Ubah"><i class="ti ti-pencil ti-xs"></i></button></td></tr>';
      }).join(''));
    }

    /* ---------------- Riwayat struk (bak_tagihan) ---------------- */

    function tglIndo(t) {
      if (!t) return '';
      var d = new Date(t + 'T00:00:00');
      return isNaN(d) ? t : d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
    }

    function tampilRiwayat(rows) {
      var $tb = $('#tb-riwayat'), lunas = 0, kosongN = 0;
      hitung('#cnt-riwayat', rows.length);
      if (!rows.length) { $('#riwayat-ringkas').text(''); return kosongTr($tb, 9, 'history', 'Belum ada struk.'); }

      $tb.html(rows.map(function (r) {
        var isLunas = r.lunas == 1, n = Number(r.jumlah_rincian), aktif = r.id === idTagihan;
        if (isLunas) lunas++;
        if (!n) kosongN++;
        // Badge lunas tampil di bawah nomor struk; kolom Pembayaran berisi tanggal lunas & bank.
        var badge = isLunas
          ? '<span class="badge bg-label-success">Lunas</span>'
          : '<span class="badge bg-label-warning">Belum lunas</span>';
        var bayar = isLunas
          ? '<div class="fw-medium">' + (r.lunas_date ? esc(tglIndo(r.lunas_date)) : '-') + '</div>' +
            (r.bank ? '<div class="tg-meta">' + esc(r.nm_bank || ('Bank ' + r.bank)) + '</div>' : '')
          : '<span class="text-muted">—</span>';
        var aksi = (!n && !isLunas)
          ? '<button type="button" class="btn btn-icon btn-sm btn-text-danger rounded-pill" data-struk-hapus="' + esc(r.id) + '" title="Hapus struk kosong"><i class="ti ti-trash ti-xs"></i></button>'
          : '';
        return '<tr class="' + (aktif ? 'tg-row-aktif' : '') + '">' +
          '<td><div class="fw-medium">#' + esc(r.struk_id) + (aktif ? ' <span class="badge bg-label-primary">aktif</span>' : '') + '</div>' +
          '<div class="mt-1">' + badge + '</div></td>' +
          '<td>' + esc($.trim(r.thnsmt)) + '<div class="tg-meta">' + esc($.trim(r.thn_akad)) + ' · ' + (r.smt_akad == 2 ? 'Genap' : 'Ganjil') + '</div></td>' +
          '<td class="text-center">' + esc(r.mhssmt) + '</td>' +
          '<td>' + esc(SARAT[r.sarat] || '-') + '</td>' +
          '<td class="text-nowrap">' + esc(tglIndo(r.input_date)) + '</td>' +
          '<td class="text-center"><span class="badge rounded-pill ' + (n ? 'bg-label-primary' : 'bg-label-secondary') + '">' + n + '</span></td>' +
          '<td class="text-end text-nowrap fw-medium">' + rp(r.total) + '</td>' +
          '<td>' + bayar + '</td>' +
          '<td class="tg-aksi text-end">' + aksi + '</td></tr>';
      }).join(''));

      $('#riwayat-ringkas').text(rows.length + ' struk · ' + lunas + ' lunas' + (kosongN ? ' · ' + kosongN + ' tanpa rincian' : ''));
    }

    $(document).on('click', '[data-struk-hapus]', function () {
      var id = $(this).data('struk-hapus');
      yakin('Hapus struk?', 'Struk tanpa rincian ini akan dihapus.').then(function (ok) {
        if (!ok) return;
        $.ajax({ url: url('strukHapus', id), method: 'DELETE', data: { id_reg_pd: mhs.idreg } })
          .done(function () { if (id === idTagihan) setStruk(null); muatDetail(); toast('Struk dihapus'); })
          .fail(galat);
      });
    });

    $('#btn-prasarat-tambah').on('click', function () {
      $.post(URL.prasaratStore, { id_reg_pd: mhs.idreg, id_smt: $('#id_smt').val() })
        .done(function (d) { toast(d.message); muatDetail(); }).fail(galat);
    });

    var $mPrasarat = $('#modalPrasarat');
    $(document).on('click', '[data-prasarat-edit]', function () {
      var r = $(this).data('prasarat-edit');
      $mPrasarat.data('id', r.id_byr);
      $('#ps_smt').val(r.id_smt); $('#ps_npm').val($.trim(r.nipd)); $('#ps_nama').val(r.nm_pd);
      $('#ps_entri').prop('checked', r.lunas_reg == 1); $('#ps_cetak').prop('checked', r.lunas_sks == 1);
      bootstrap.Modal.getOrCreateInstance($mPrasarat[0]).show();
    });

    $('#form-prasarat').on('submit', function (e) {
      e.preventDefault();
      $.ajax({
        url: url('prasarat', $mPrasarat.data('id')), method: 'PUT',
        data: { entri_krs: $('#ps_entri').is(':checked') ? 1 : '', cetak_kpu: $('#ps_cetak').is(':checked') ? 1 : '' },
      }).done(function () { bootstrap.Modal.getInstance($mPrasarat[0]).hide(); muatDetail(); toast('Prasarat disimpan'); }).fail(galat);
    });

    /* ---------------- Modal nominal (biaya / rincian) ---------------- */

    var $mNominal = $('#modalNominal'), simpanNominal;
    function bukaNominal(o) {
      simpanNominal = o.simpan;
      $('#modalNominalLabel').text(o.judul);
      $('#nm_nama').text(o.nama); $('#nm_awal').text('Rp ' + rp(o.uang)); $('#nm_uang').val(o.uang);
      $('#nm_label').text(o.label); $('#nm_bantuan').text(o.bantuan);
      bootstrap.Modal.getOrCreateInstance($mNominal[0]).show();
    }
    $mNominal.on('shown.bs.modal', function () { $('#nm_uang').trigger('focus').trigger('select'); });
    $('#form-nominal').on('submit', function (e) {
      e.preventDefault();
      simpanNominal($('#nm_uang').val())
        .done(function () { bootstrap.Modal.getInstance($mNominal[0]).hide(); toast('Nominal disimpan'); })
        .fail(galat);
    });

    /* ---------------- KPP ---------------- */

    $('#btn-kpp').on('click', function () {
      if (!mhs) return;
      $('#kpp-isi').html(kosong('loader-2', 'Memuat KPP…'));
      $('#modalKppLabel').text('KPP Mahasiswa — ' + mhs.npm + ' ' + mhs.nmmhs);
      bootstrap.Modal.getOrCreateInstance($('#modalKpp')[0]).show();
      $.get(url('kpp', mhs.idreg)).done(function (html) { $('#kpp-isi').html(html); }).fail(galat);
    });

    resetMahasiswa();
    $('#npm').trigger('focus');
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
        <span>Struk Tagihan</span>
      </div>
      <h4 class="pg-header-title">Pembuatan Struk Tagihan</h4>
      <p class="pg-header-sub">Buat struk, pilih rincian biaya, kelola dispensasi dan status prasarat pembayaran.</p>
    </div>
  </div>

  <div class="pg-header-stats">
    <div class="pg-stat">
      <span class="pg-stat-icon is-info"><i class="ti ti-calendar"></i></span>
      <div>
        <strong id="stat-semester">{{ $semester[$semesterAktif] ?? $semesterAktif }}</strong>
        <small>Tahun akademik</small>
      </div>
    </div>
    <div class="pg-stat">
      <span class="pg-stat-icon"><i class="ti ti-user"></i></span>
      <div>
        <strong id="stat-mhs">—</strong>
        <small>Mahasiswa</small>
      </div>
    </div>
    <div class="pg-stat">
      <span class="pg-stat-icon is-danger" id="stat-struk-icon"><i class="ti ti-clock"></i></span>
      <div>
        <strong id="stat-struk">Belum diproses</strong>
        <small>Status struk</small>
      </div>
    </div>
    <div class="pg-stat">
      <span class="pg-stat-icon is-success"><i class="ti ti-coins"></i></span>
      <div>
        <strong id="stat-total">Rp 0</strong>
        <small>Total belum lunas</small>
      </div>
    </div>
  </div>
</div>

{{-- Bar kontrol + profil mahasiswa --}}
<div class="card mb-3 tg-toolbar">
  <div class="card-body pb-3">
    <form id="form-proses" class="row g-2 align-items-end" autocomplete="off">
      <div class="col-12 col-sm-6 col-lg-3 tg-npm">
        <label class="form-label" for="npm">NPM Mahasiswa</label>
        <div class="input-group input-group-merge">
          <span class="input-group-text"><i class="ti ti-search"></i></span>
          <input type="text" id="npm" class="form-control" placeholder="Ketik NPM, lalu Enter" inputmode="numeric">
          <span class="input-group-text">
            <span class="spinner-border spinner-border-sm text-primary d-none" id="npm-spinner" role="status"></span>
          </span>
        </div>
      </div>
      <div class="col-6 col-sm-6 col-lg-2">
        <label class="form-label" for="id_smt">Tahun Akademik</label>
        <select id="id_smt" class="form-select">
          @foreach ($semester as $id => $nama)
          <option value="{{ $id }}" @selected($id == $semesterAktif)>{{ $nama }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-6 col-sm-6 col-lg-2">
        <label class="form-label" for="kelas">Kelas</label>
        <select id="kelas" class="form-select">
          <option value="">Semua kelas</option>
          @foreach ($kelas as $nilai => [$kode, $nama])
          <option value="{{ $nilai }}">({{ $kode }}) {{ $nama }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-12 col-sm-6 col-lg-2">
        <span class="form-label d-block">Jenis Prasarat</span>
        <div class="btn-group w-100 tg-sarat" role="group" aria-label="Jenis prasarat">
          @foreach ($sarat as $id => $nama)
          <input type="radio" class="btn-check" name="sarat" id="sarat{{ $id }}" value="{{ $id }}" @checked($loop->first)>
          <label class="btn btn-outline-primary" for="sarat{{ $id }}">{{ $nama }}</label>
          @endforeach
        </div>
      </div>
      <div class="col-12 col-lg-3 d-flex gap-2">
        <button type="submit" id="btn-proses" class="btn btn-primary flex-grow-1" disabled>
          <span class="spinner-border spinner-border-sm me-1 d-none" role="status"></span>
          <i class="ti ti-receipt ti-xs me-1"></i>Proses Struk
        </button>
        <button type="button" id="btn-kpp" class="btn btn-label-warning" disabled title="Lihat KPP">
          <i class="ti ti-history ti-xs"></i><span class="d-none d-xxl-inline ms-1">KPP</span>
        </button>
        <a href="{{ route('operator.tagihan.index') }}" class="btn btn-label-secondary" title="Reset">
          <i class="ti ti-refresh ti-xs"></i>
        </a>
      </div>
    </form>
  </div>

  <div class="tg-profil px-4 py-3">
    <div id="profil-kosong" class="d-flex align-items-center gap-2 text-muted small">
      <i class="ti ti-user-question"></i><span></span>
    </div>
    <div id="profil-isi" class="d-none d-flex flex-wrap align-items-center gap-3">
      <div class="d-flex align-items-center gap-3 min-w-0 me-auto">
        <div class="tg-avatar bg-label-primary" id="p-avatar"></div>
        <div class="min-w-0">
          <div class="fw-semibold text-truncate" id="p-nama"></div>
          <div class="small text-muted text-truncate"><span id="p-npm"></span> · <span id="p-prodi"></span></div>
        </div>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <span class="tg-chip"><i class="ti ti-calendar ti-xs"></i>Angkatan <strong id="p-angkatan"></strong></span>
        <span class="tg-chip"><i class="ti ti-stairs-up ti-xs"></i>Semester <strong id="p-semester"></strong></span>
      </div>
    </div>
  </div>
</div>

<div class="row g-3">
  {{-- Rincian biaya prodi --}}
  <div class="col-12 col-lg-5">
    <div class="card h-100">
      <div class="card-header tg-head d-flex align-items-center justify-content-between py-2">
        <div class="min-w-0">
          <h6 class="mb-0">Biaya Prodi <span class="badge rounded-pill bg-label-primary d-none" id="cnt-biaya"></span></h6>
          <small class="text-muted text-truncate d-block" id="info-prodi">—</small>
        </div>
        <small class="text-muted d-none d-md-inline"><i class="ti ti-plus ti-xs"></i> = masukkan ke struk</small>
      </div>
      <div class="tg-scroll border-top" id="biaya-list"></div>
    </div>
  </div>

  {{-- Struk / dispensasi / prasarat --}}
  <div class="col-12 col-lg-7">
    <div class="card h-100 d-flex flex-column">
      <div class="card-header tg-tabs-header">
        <ul class="nav nav-tabs tg-tabs" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-struk" data-bs-toggle="tab" data-bs-target="#pane-struk" type="button" role="tab" aria-controls="pane-struk" aria-selected="true">
              <i class="ti ti-receipt"></i>
              <span>Rincian Struk</span>
              <span class="badge rounded-pill bg-primary d-none" id="cnt-rincian"></span>
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#pane-dispen" type="button" role="tab" aria-controls="pane-dispen" aria-selected="false">
              <i class="ti ti-discount"></i>
              <span>Dispensasi</span>
              <span class="badge rounded-pill bg-warning d-none" id="cnt-dispen"></span>
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#pane-prasarat" type="button" role="tab" aria-controls="pane-prasarat" aria-selected="false">
              <i class="ti ti-shield-check"></i>
              <span>Prasarat</span>
              <span class="badge rounded-pill bg-secondary d-none" id="cnt-prasarat"></span>
            </button>
          </li>
        </ul>
      </div>

      <div class="tab-content p-0 flex-grow-1">
        <div class="tab-pane fade show active" id="pane-struk" role="tabpanel">
          <div class="tg-scroll">
            <table class="table table-sm table-hover tg-table small mb-0">
              <thead>
                <tr>
                  <th>Rincian</th>
                  <th class="text-end">Rp</th>
                  <th class="text-end tg-aksi">Aksi</th>
                </tr>
              </thead>
              <tbody id="tb-rincian"></tbody>
            </table>
          </div>
          <div class="d-flex justify-content-between align-items-center px-4 py-3 border-top bg-warning text-white">
            <span class="fw-medium">Total belum lunas</span>
            <span class="tg-total" id="total-struk">Rp 0</span>
          </div>
        </div>

        <div class="tab-pane fade" id="pane-dispen" role="tabpanel">
          <div class="tg-scroll">
            <table class="table table-sm table-hover tg-table small mb-0">
              <thead>
                <tr>
                  <th>Dispensasi</th>
                  <th class="text-end">Pokok</th>
                  <th class="text-end">Sisa</th>
                  <th class="text-end tg-aksi">Aksi</th>
                </tr>
              </thead>
              <tbody id="tb-dispen"></tbody>
            </table>
          </div>
        </div>

        <div class="tab-pane fade" id="pane-prasarat" role="tabpanel">
          <div class="d-flex justify-content-between align-items-center px-4 py-2 border-bottom">
            <small class="text-muted">Status prasarat pembayaran SPP per tahun akademik</small>
            <button type="button" id="btn-prasarat-tambah" class="btn btn-sm btn-label-primary" disabled title="Tambah status untuk tahun akademik terpilih">
              <i class="ti ti-plus ti-xs me-1"></i>Tambah
            </button>
          </div>
          <div class="tg-scroll">
            <table class="table table-sm table-hover tg-table small mb-0">
              <thead>
                <tr>
                  <th>Tahun Akademik</th>
                  <th>Entri KRS</th>
                  <th>Cetak KPU</th>
                  <th class="text-end tg-aksi">Aksi</th>
                </tr>
              </thead>
              <tbody id="tb-prasarat"></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- Riwayat struk / pembayaran (bak_tagihan) --}}
<div class="card mt-3">
  <div class="card-header tg-head d-flex flex-wrap align-items-center justify-content-between gap-2 py-2">
    <div>
      <h6 class="mb-0"><i class="ti ti-history ti-xs me-1"></i>Histori Struk &amp; Pembayaran
        <span class="badge rounded-pill bg-label-primary d-none" id="cnt-riwayat"></span></h6>
      <small class="text-muted" id="riwayat-ringkas"></small>
    </div>
    <small class="text-muted"><i class="ti ti-trash ti-xs"></i> muncul untuk struk belum lunas tanpa rincian</small>
  </div>
  <div class="tg-scroll border-top">
    <table class="table table-sm table-hover tg-table tg-riwayat small mb-0">
      <thead>
        <tr>
          <th>Struk</th>
          <th>Semester</th>
          <th class="text-center">Sem Mhs</th>
          <th>Sarat</th>
          <th>Dibuat</th>
          <th class="text-center">Rincian</th>
          <th class="text-end">Total (Rp)</th>
          <th>Pembayaran</th>
          <th class="text-end tg-aksi">Aksi</th>
        </tr>
      </thead>
      <tbody id="tb-riwayat"></tbody>
    </table>
  </div>
</div>

{{-- Modal ubah nominal (biaya prodi / rincian struk) --}}
<div class="modal fade" id="modalNominal" tabindex="-1" aria-labelledby="modalNominalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="form-nominal" novalidate>
        <div class="modal-header">
          <h5 class="modal-title" id="modalNominalLabel">Ubah Nominal</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
        </div>
        <div class="modal-body">
          <div class="p-3 rounded bg-label-secondary mb-3">
            <div class="fw-medium" id="nm_nama"></div>
            <small class="text-muted">Nominal saat ini: <span id="nm_awal"></span></small>
          </div>
          <label class="form-label" for="nm_uang" id="nm_label">Nominal</label>
          <div class="input-group input-group-lg">
            <span class="input-group-text">Rp</span>
            <input type="number" id="nm_uang" class="form-control" min="0" step="1" required>
          </div>
          <div class="form-text" id="nm_bantuan"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy ti-xs me-1"></i>Simpan</button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- Modal prasarat --}}
<div class="modal fade" id="modalPrasarat" tabindex="-1" aria-labelledby="modalPrasaratLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="form-prasarat">
        <div class="modal-header">
          <h5 class="modal-title" id="modalPrasaratLabel">Prasarat Pembayaran</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
        </div>
        <div class="modal-body row g-3">
          <div class="col-5">
            <label class="form-label" for="ps_smt">Tahun Akademik</label>
            <input type="text" id="ps_smt" class="form-control" readonly>
          </div>
          <div class="col-7">
            <label class="form-label" for="ps_npm">NPM</label>
            <input type="text" id="ps_npm" class="form-control" readonly>
          </div>
          <div class="col-12">
            <label class="form-label" for="ps_nama">Nama Mahasiswa</label>
            <input type="text" id="ps_nama" class="form-control" readonly>
          </div>
          <div class="col-12 d-flex flex-column gap-2">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="ps_entri">
              <label class="form-check-label" for="ps_entri">Sarat Entri KRS</label>
            </div>
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="ps_cetak">
              <label class="form-check-label" for="ps_cetak">Sarat Cetak KPU</label>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy ti-xs me-1"></i>Simpan</button>
        </div>
      </form>
    </div>
  </div>
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
