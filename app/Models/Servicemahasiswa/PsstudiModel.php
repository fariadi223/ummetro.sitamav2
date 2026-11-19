<?php

namespace App\Models\Servicemahasiswa;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use App\Models\Servicemahasiswa\JurusanModel;

/************************************************************************
 * File ini di panggil pada file lain
 * **********************************************************************
 * App\Models\Servicemahasiswa\MahasiswaModel::prodi
 * App\Models\Mak\MataAnggaranModel::prodi
 * 
 * **********************************************************************
 */

class PsstudiModel extends Model
{
    use HasFactory, HasUuids;

    protected $table        = 'tbl_sms';
    protected $primaryKey   = 'id_sms';
    
    public $incrementing    = false;
    public $timestamps      = false;

    protected $fillable     = [
        'id_sms',
        'kode_prodi',
        'nm_lemb',
        'kd_jur',
        'jenjang_didik',
    ];
    
    public function jurusan(): object
    {
        return $this->hasOne(JurusanModel::class, 'kode_jur', 'kd_jur');
    }
}
