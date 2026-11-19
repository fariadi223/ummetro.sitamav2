<?php

namespace App\Models\Servicemahasiswa;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Servicemahasiswa\FakultasModel;
/************************************************************************
 * File ini di panggil pada file lain
 * **********************************************************************
 * App\Models\Servicemahasiswa\MahasiswaModel::prodi
 * App\Models\Mak\MataAnggaranModel::prodi
 * 
 * **********************************************************************
 */

class JurusanModel extends Model
{
    use HasFactory;

    protected $table        = 'tbl_jur';
    protected $primaryKey   = 'kode_jur';
    protected $keyType      = 'string';
    public $incrementing    = false;
    public $timestamps      = false;

    protected $fillable     = [
        'kode_jur',
        'nama_jur',
        'kode_fak',
    ];
    
    public function fakultas(): object
    {
        //return $this->belongsTo(PsstudiModel::class, 'id_sms');
        return $this->hasOne(FakultasModel::class, 'kode_fak', 'kode_fak');
    }
}
