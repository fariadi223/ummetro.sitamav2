@extends('layouts/layoutMaster')

@section('title', 'Dashboard')

@section('content')
<div class="card">
  <div class="card-body">
    <h4 class="mb-1">Selamat datang, {{ Auth::user()->name }}</h4>
    <p class="mb-0 text-muted">Dashboard administrator SITAMA Universitas Muhammadiyah Metro.</p>
  </div>
</div>
@endsection
