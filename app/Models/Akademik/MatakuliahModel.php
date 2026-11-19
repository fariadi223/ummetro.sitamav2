<?php

namespace App\Models\Akademik;

use App\Models\Servicemahasiswa\PsstudiModel;
use Illuminate\Database\Eloquent\Model;

/**
 * Master matakuliah (tbl_mk).
 *
 * Dipakai di App\Repositories\Akademik\MatakuliahPraktikRepository.
 */
class MatakuliahModel extends Model
{
    protected $table        = 'tbl_mk';
    protected $primaryKey   = 'id_mk';
    protected $keyType      = 'string';

    public $incrementing    = false;
    public $timestamps      = false;

    public function prodi(): object
    {
        return $this->belongsTo(PsstudiModel::class, 'id_sms', 'id_sms');
    }
}
