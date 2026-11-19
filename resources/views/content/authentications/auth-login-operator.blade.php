@php
$customizerHidden = 'customizer-hide';
$configData = Helper::appClasses();
@endphp

@extends('layouts/blankLayout')

@section('title', 'Login')

@section('page-meta')
<meta name="robots" content="noindex, nofollow">
@endsection

@section('page-style')
@include('_partials._auth.auth-style')
@endsection

@section('page-script')
<script>
  // Tombol lihat/sembunyikan password (mandiri, tanpa dependensi tema).
  document.querySelectorAll('.au-toggle').forEach(function (toggle) {
    toggle.addEventListener('click', function () {
      var input = toggle.closest('.au-field').querySelector('input');
      var icon = toggle.querySelector('i');
      var hidden = input.getAttribute('type') === 'password';

      input.setAttribute('type', hidden ? 'text' : 'password');
      icon.classList.toggle('ti-eye-off', !hidden);
      icon.classList.toggle('ti-eye', hidden);
      toggle.setAttribute('aria-label', hidden ? 'Sembunyikan password' : 'Tampilkan password');
    });
  });
</script>
@endsection

@section('content')
<div class="au-wrap">
  <div class="au-container">

    {{-- Teks sambutan --}}
    <header class="au-welcome">
      <h2>SELAMAT DATANG</h2>
      <h2>DI PORTAL SISTEM INFORMASI TRACER STUDY</h2>
      <h1>UNIVERSITAS MUHAMMADIYAH METRO LAMPUNG</h1>
    </header>

    <div class="au-row">
      <section class="au-card">

        <div class="au-card-head">
          <img src="{{ asset('assets/img/login/icon.png') }}" alt="Logo Universitas Muhammadiyah Metro">
          <h1>LOGIN PENGELOLA</h1>
        </div>

        <div class="au-card-body">
          @if ($errors->any())
          <div class="alert alert-danger alert-dismissible d-flex align-items-start au-alert" role="alert">
            <i class="ti ti-alert-circle me-2 mt-1 flex-shrink-0"></i>
            <div>{{ $errors->first() }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
          </div>
          @endif

          @if (session('status'))
          <div class="alert alert-success alert-dismissible au-alert" role="alert">
            {{ session('status') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
          </div>
          @endif

          <form method="POST" action="{{ route('operator.login.attempt') }}" novalidate>
            @csrf

            <div class="mb-3">
              <label class="form-label" for="username">Username / Email</label>
              <div class="au-field">
                <input
                  type="text"
                  id="username"
                  name="username"
                  placeholder="Username / Email"
                  class="form-control @error('username') is-invalid @enderror"
                  value="{{ old('username') }}"
                  autocomplete="username"
                  autocapitalize="none"
                  spellcheck="false"
                  enterkeyhint="next"
                  required
                  autofocus>
                <span class="au-icon"><i class="ti ti-user"></i></span>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label" for="password">Password</label>
              <div class="au-field">
                <input
                  type="password"
                  id="password"
                  name="password"
                  placeholder="Password"
                  class="form-control @error('password') is-invalid @enderror"
                  autocomplete="current-password"
                  enterkeyhint="go"
                  required>
                <button type="button" class="au-icon au-toggle" aria-label="Tampilkan password">
                  <i class="ti ti-eye-off"></i>
                </button>
              </div>
            </div>

            <div class="form-check mb-4">
              <input class="form-check-input" type="checkbox" id="remember" name="remember" value="1" @checked(old('remember'))>
              <label class="form-check-label" for="remember">Ingat saya</label>
            </div>

            <button type="submit" class="btn w-100 au-btn">Masuk</button>
          </form>

          <p class="au-foot">
            Copyright &copy; {{ date('Y') }}
            <a href="https://ummetro.ac.id" target="_blank" rel="noopener">Universitas Muhammadiyah Metro</a>
          </p>
        </div>

      </section>
    </div>

  </div>
</div>
@endsection
