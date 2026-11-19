<?php

namespace App\Models\Akademik;

use Illuminate\Database\Eloquent\Model;

/**
 * Nilai / KRS mahasiswa per matakuliah per semester (tbl_nil).
 * Kolom acara_prak = 1 menandai matakuliah praktik (dasar tagihan per praktikum).
 *
 * Dipakai di App\Repositories\Akademik\MatakuliahPraktikRepository.
 */
class NilaiModel extends Model
{
    protected $table        = 'tbl_nil';
    protected $primaryKey   = 'id_nil';
    protected $keyType      = 'string';

    public $incrementing    = false;
    public $timestamps      = false;
}
