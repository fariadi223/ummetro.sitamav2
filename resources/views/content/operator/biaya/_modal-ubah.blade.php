{{-- Modal ubah biaya. Diisi lewat JS dari atribut data-biaya tombol Edit.
     Error validasi memakai bag "ubah"; bila ada error, modal dibuka ulang
     dengan isian lama (old()). Biaya yang sudah dipakai tagihan: rekening,
     prodi, dan angkatan dikunci (juga dipaksa di controller). --}}
@php($err = $errors->ubah)

<div class="modal fade" id="modalUbahBiaya" tabindex="-1" aria-labelledby="modalUbahBiayaLabel" aria-hidden="true"
  data-buka="{{ $err->any() ? '1' : '0' }}"
  data-action="{{ route('operator.biaya.update', '__ID__') }}"
  @if ($err->any())
  data-lama="{{ json_encode([
    'id' => old('_id'),
    'dipakai' => old('_dipakai') === '1',
    'ma' => old('ma'),
    'ma_label' => old('_ma_label'),
    'sms' => old('sms'),
    'angkatan' => old('angkatan'),
    'kelas' => old('kelas'),
    'uang' => old('uang'),
    'sumber_id' => old('sumber_id'),
    'prasarat' => old('prasarat'),
    'kredit' => old('kredit') === '1',
    'praktik' => old('praktik') === '1',
    'pilihmhs' => old('pilihmhs') === '1',
  ]) }}"
  @endif>
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" action="#" novalidate>
        @csrf
        @method('PUT')
        <input type="hidden" name="_id">
        <input type="hidden" name="_dipakai">
        <input type="hidden" name="_ma_label">

        <div class="modal-header">
          <h5 class="modal-title" id="modalUbahBiayaLabel">
            <i class="ti ti-pencil ti-sm me-1"></i>Ubah Biaya
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
        </div>

        <div class="modal-body">
          <div class="alert alert-warning d-none align-items-center mb-3" data-info-dipakai>
            <i class="ti ti-lock me-2"></i>
            <span>Biaya ini sudah dipakai pada tagihan mahasiswa. Rekening, program studi, dan angkatan tidak dapat diubah.</span>
          </div>

          <div class="row g-3">
            <div class="col-12">
              <label class="form-label" for="ubah_ma">Rekening Biaya <span class="text-danger">*</span></label>
              <select id="ubah_ma" name="ma" class="form-select select2-modal @error('ma', 'ubah') is-invalid @enderror" data-kunci required>
                <option value="">Pilih rekening</option>
                @foreach ($rekening as $r)
                <option value="{{ $r->ma }}">{{ $r->kode }} — {{ trim($r->nama) }}</option>
                @endforeach
              </select>
              @error('ma', 'ubah')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>

            <div class="col-12">
              <label class="form-label" for="ubah_sms">Program Studi <span class="text-danger">*</span></label>
              <select id="ubah_sms" name="sms" class="form-select select2-modal @error('sms', 'ubah') is-invalid @enderror" data-kunci required>
                <option value="">Pilih program studi</option>
                @foreach ($prodi as $p)
                <option value="{{ $p->id_sms }}">{{ trim($p->jenjang_didik) }} - {{ trim($p->nm_lemb) }}</option>
                @endforeach
              </select>
              @error('sms', 'ubah')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label" for="ubah_angkatan">Angkatan <span class="text-danger">*</span></label>
              <input type="number" id="ubah_angkatan" name="angkatan" min="1900" max="9999" step="1"
                class="form-control @error('angkatan', 'ubah') is-invalid @enderror" data-kunci required>
              @error('angkatan', 'ubah')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label" for="ubah_kelas">Kelas <span class="text-danger">*</span></label>
              <select id="ubah_kelas" name="kelas" class="form-select @error('kelas', 'ubah') is-invalid @enderror" required>
                <option value="">Pilih kelas</option>
                @foreach (\App\Repositories\Mak\BiayaRepository::KELAS as $nilai => [$kode, $nama])
                <option value="{{ $nilai }}">({{ $kode }}) {{ $nama }}</option>
                @endforeach
              </select>
              @error('kelas', 'ubah')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label" for="ubah_uang">Nominal <span class="text-danger">*</span></label>
              <div class="input-group has-validation">
                <span class="input-group-text">Rp</span>
                <input type="number" id="ubah_uang" name="uang" min="0" step="1"
                  class="form-control @error('uang', 'ubah') is-invalid @enderror" placeholder="0" required>
                @error('uang', 'ubah')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label" for="ubah_sumber_id">Sumber Dana <span class="text-danger">*</span></label>
              <select id="ubah_sumber_id" name="sumber_id" class="form-select select2-modal @error('sumber_id', 'ubah') is-invalid @enderror" required>
                <option value="">Pilih sumber dana</option>
                @foreach ($sumberDana as $s)
                <option value="{{ $s->sumber_id }}">{{ $s->sumber_nama }}</option>
                @endforeach
              </select>
              @error('sumber_id', 'ubah')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label" for="ubah_prasarat">Prasarat</label>
              <select id="ubah_prasarat" name="prasarat" class="form-select @error('prasarat', 'ubah') is-invalid @enderror">
                <option value="">Pilih</option>
                @foreach (\App\Repositories\Mak\BiayaRepository::PRASARAT as $nilai => [$label])
                <option value="{{ $nilai }}">{{ $label }}</option>
                @endforeach
                <option value="0">Tidak Ada</option>
              </select>
              @error('prasarat', 'ubah')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label d-block">Opsi Perhitungan</label>
              <div class="d-flex flex-column gap-2">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="ubah_kredit" name="kredit" value="1">
                  <label class="form-check-label" for="ubah_kredit">Hitung per SKS</label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="ubah_praktik" name="praktik" value="1">
                  <label class="form-check-label" for="ubah_praktik">Hitung per Praktikum</label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="ubah_pilihmhs" name="pilihmhs" value="1">
                  <label class="form-check-label" for="ubah_pilihmhs">Dapat diinput mahasiswa</label>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary">
            <i class="ti ti-device-floppy ti-xs me-1"></i>Simpan Perubahan
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
