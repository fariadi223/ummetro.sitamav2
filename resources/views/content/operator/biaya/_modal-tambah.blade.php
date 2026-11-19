{{-- Modal tambah biaya. Error validasi memakai bag "tambah" supaya tidak
     tercampur dengan filter, dan modal dibuka ulang bila ada error. --}}
@php($err = $errors->tambah)

<div class="modal fade" id="modalTambahBiaya" tabindex="-1" aria-labelledby="modalTambahBiayaLabel" aria-hidden="true"
  data-buka="{{ $err->any() ? '1' : '0' }}">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" action="{{ route('operator.biaya.store') }}" novalidate>
        @csrf
        <div class="modal-header">
          <h5 class="modal-title" id="modalTambahBiayaLabel">
            <i class="ti ti-plus ti-sm me-1"></i>Tambah Biaya
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
        </div>

        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label" for="tambah_ma">Rekening Biaya <span class="text-danger">*</span></label>
              <select id="tambah_ma" name="ma" class="form-select select2-modal @error('ma', 'tambah') is-invalid @enderror" required>
                <option value="">Pilih rekening</option>
                @foreach ($rekening as $r)
                <option value="{{ $r->ma }}" @selected(old('ma') == $r->ma)>{{ $r->kode }} — {{ trim($r->nama) }}</option>
                @endforeach
              </select>
              @error('ma', 'tambah')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
              @if ($rekeningGalat)
              <div class="form-text text-warning">
                <i class="ti ti-alert-triangle ti-xs me-1"></i>Rekening SIMAKU tidak dapat diambil; menampilkan rekening yang sudah dipakai di data biaya.
              </div>
              @else
              <div class="form-text">Rekening level 6 dari SIMAKU. Kode dan nama biaya mengikuti rekening yang dipilih.</div>
              @endif
            </div>

            <div class="col-12">
              @php($errSms = $err->first('sms') ?: $err->first('sms.*'))
              @php($smsLama = (array) old('sms', []))
              <div class="d-flex justify-content-between align-items-center">
                <label class="form-label" for="tambah_sms">Program Studi <span class="text-danger">*</span></label>
                <div class="d-flex gap-2 mb-1">
                  <a href="javascript:void(0)" class="small" data-sms-semua>Pilih semua</a>
                  <a href="javascript:void(0)" class="small text-muted" data-sms-kosong>Kosongkan</a>
                </div>
              </div>
              <select id="tambah_sms" name="sms[]" multiple class="form-select select2-modal {{ $errSms ? 'is-invalid' : '' }}"
                data-placeholder="Pilih satu atau lebih program studi" required>
                @foreach ($prodi as $p)
                <option value="{{ $p->id_sms }}" @selected(in_array($p->id_sms, $smsLama, true))>
                  {{ trim($p->jenjang_didik) }} - {{ trim($p->nm_lemb) }}
                </option>
                @endforeach
              </select>
              @if ($errSms)<div class="invalid-feedback d-block">{{ $errSms }}</div>@endif
              <div class="form-text">Biaya dibuat untuk setiap prodi terpilih. Prodi yang sudah punya biaya yang sama dilewati.</div>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label" for="tambah_angkatan">Angkatan <span class="text-danger">*</span></label>
              <input type="number" id="tambah_angkatan" name="angkatan" min="1900" max="9999" step="1"
                class="form-control @error('angkatan', 'tambah') is-invalid @enderror"
                placeholder="mis. {{ now()->year }}" value="{{ old('angkatan', now()->year) }}" required>
              @error('angkatan', 'tambah')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label" for="tambah_kelas">Kelas <span class="text-danger">*</span></label>
              <select id="tambah_kelas" name="kelas" class="form-select @error('kelas', 'tambah') is-invalid @enderror" required>
                <option value="">Pilih kelas</option>
                @foreach (\App\Repositories\Mak\BiayaRepository::KELAS as $nilai => [$kode, $nama])
                <option value="{{ $nilai }}" @selected(old('kelas') === (string) $nilai)>({{ $kode }}) {{ $nama }}</option>
                @endforeach
              </select>
              @error('kelas', 'tambah')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label" for="tambah_uang">Nominal <span class="text-danger">*</span></label>
              <div class="input-group has-validation">
                <span class="input-group-text">Rp</span>
                <input type="number" id="tambah_uang" name="uang" min="0" step="1"
                  class="form-control @error('uang', 'tambah') is-invalid @enderror"
                  placeholder="0" value="{{ old('uang') }}" required>
                @error('uang', 'tambah')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label" for="tambah_sumber_id">Sumber Dana <span class="text-danger">*</span></label>
              <select id="tambah_sumber_id" name="sumber_id" class="form-select select2-modal @error('sumber_id', 'tambah') is-invalid @enderror" required>
                <option value="">Pilih sumber dana</option>
                @foreach ($sumberDana as $s)
                <option value="{{ $s->sumber_id }}" @selected(old('sumber_id') == $s->sumber_id)>{{ $s->sumber_nama }}</option>
                @endforeach
              </select>
              @error('sumber_id', 'tambah')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label" for="tambah_prasarat">Prasarat</label>
              <select id="tambah_prasarat" name="prasarat" class="form-select @error('prasarat', 'tambah') is-invalid @enderror">
                <option value="">Pilih</option>
                @foreach (\App\Repositories\Mak\BiayaRepository::PRASARAT as $nilai => [$label])
                <option value="{{ $nilai }}" @selected(old('prasarat') === (string) $nilai)>{{ $label }}</option>
                @endforeach
                <option value="0" @selected(old('prasarat') === '0')>Tidak Ada</option>
              </select>
              @error('prasarat', 'tambah')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label d-block">Opsi Perhitungan</label>
              <div class="d-flex flex-column gap-2">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="tambah_kredit" name="kredit" value="1" @checked(old('kredit'))>
                  <label class="form-check-label" for="tambah_kredit">Hitung per SKS</label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="tambah_praktik" name="praktik" value="1" @checked(old('praktik'))>
                  <label class="form-check-label" for="tambah_praktik">Hitung per Praktikum</label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="tambah_pilihmhs" name="pilihmhs" value="1" @checked(old('pilihmhs'))>
                  <label class="form-check-label" for="tambah_pilihmhs">Dapat diinput mahasiswa</label>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary">
            <i class="ti ti-device-floppy ti-xs me-1"></i>Simpan
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
