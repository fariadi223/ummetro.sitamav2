{{-- Rekap KPP mahasiswa per MA per semester (dimuat ke modal lewat AJAX). --}}
@if ($baris->isEmpty())
<div class="text-center text-muted py-5">Belum ada data KPP untuk mahasiswa ini.</div>
@else
<div class="table-responsive">
  <table class="table table-sm table-bordered table-hover small mb-0">
    <thead>
      <tr>
        <th>Kode</th>
        <th>Nama Rekening</th>
        @for ($s = 1; $s <= $semester; $s++)
        <th class="text-end text-nowrap">Sem {{ $s }}</th>
        @endfor
      </tr>
    </thead>
    <tbody>
      @foreach ($baris as $r)
      <tr>
        <td class="text-nowrap"><code>{{ $r->kode }}</code></td>
        <td>{{ $r->nama }}</td>
        @for ($s = 1; $s <= $semester; $s++)
        <td class="text-end text-nowrap">{{ !empty($r->sem[$s]) ? number_format($r->sem[$s], 0, ',', '.') : '' }}</td>
        @endfor
      </tr>
      @endforeach
    </tbody>
    <tfoot>
      <tr class="fw-bold">
        <td colspan="2">Jumlah</td>
        @for ($s = 1; $s <= $semester; $s++)
        @php($total = $baris->sum(fn ($r) => $r->sem[$s] ?? 0))
        <td class="text-end text-nowrap">{{ $total ? number_format($total, 0, ',', '.') : '' }}</td>
        @endfor
      </tr>
    </tfoot>
  </table>
</div>
@endif
