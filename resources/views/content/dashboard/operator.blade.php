@extends('layouts/layoutMaster')

@section('title', 'Dashboard Operator')

@section('content')
<div class="row g-4">
  {{-- Sambutan & profil akun endpoint --}}
  <div class="col-12 col-lg-8">
    <div class="card h-100">
      <div class="card-body">
        <h4 class="mb-1">Selamat datang, {{ $user->name }}</h4>
        <p class="text-muted mb-4">
          Dashboard admin penerimaan SITAMA Universitas Muhammadiyah Metro.
        </p>
        <div class="d-flex flex-wrap gap-2">
          <span class="badge bg-label-primary">
            <i class="ti ti-shield-check ti-xs me-1"></i>Admin Penerimaan
          </span>
          <span class="badge bg-label-secondary">
            <i class="ti ti-clock ti-xs me-1"></i>{{ now()->translatedFormat('l, d F Y') }}
          </span>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-4">
    <div class="card h-100">
      <div class="card-header pb-2">
        <h5 class="card-title mb-0">Akun</h5>
      </div>
      <div class="card-body">
        <dl class="row mb-0 small">
          <dt class="col-4 text-muted fw-normal">Nama</dt>
          <dd class="col-8 text-break">{{ $user->name }}</dd>
          <dt class="col-4 text-muted fw-normal">Username</dt>
          <dd class="col-8 text-break">{{ $user->username }}</dd>
          <dt class="col-4 text-muted fw-normal">Email</dt>
          <dd class="col-8 text-break mb-0">{{ $user->email ?: '-' }}</dd>
        </dl>
      </div>
    </div>
  </div>

  {{-- Pintasan modul operator --}}
  <div class="col-12">
    <h5 class="mb-0">Modul</h5>
  </div>

  @foreach ($modul as $m)
  <div class="col-12 col-sm-6 col-xl-3">
    <div class="card h-100">
      <div class="card-body">
        <div class="avatar mb-3">
          <span class="avatar-initial rounded bg-label-{{ $m['warna'] }}">
            <i class="ti {{ $m['ikon'] }} ti-md"></i>
          </span>
        </div>
        <h6 class="mb-1">{{ $m['judul'] }}</h6>
        <p class="text-muted small mb-3">{{ $m['keterangan'] }}</p>
        @isset($m['route'])
        <a href="{{ route($m['route']) }}" class="btn btn-sm btn-label-{{ $m['warna'] }} stretched-link">Buka</a>
        @else
        <span class="badge bg-label-secondary">Segera hadir</span>
        @endisset
      </div>
    </div>
  </div>
  @endforeach
</div>
@endsection
