<?php

namespace App\Models\Servicemahasiswa;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use App\Models\Servicemahasiswa\PsstudiModel;
use App\Models\Tagihan\SppModel;

/************************************************************************
 * File ini di panggil pada file lain
 * **********************************************************************
 * used App\Models\Saldo\SaldoSppModel::mahasiswa
 * used App\Models\Kpp\KppModel::mahasiswa
 * used App\Models\Tagihan\SppModel::mahasiswa
 * used App\Repositories\Tagihan\SppRepository::mahasiswa
 * 
 * 
 * **********************************************************************
 */
class MahasiswaModel extends Model
{
    use HasFactory, HasUuids;

    protected $table        = 'tbl_mhs';
    protected $primaryKey   = 'id_reg_pd';
    
    public $incrementing    = false;
    public $timestamps      = false;

    protected $fillable     = [
        'id_reg_pd',
        'id_sms',
        'nipd',
        'nm_pd',
        'mulai_smt',
        'telepon_seluler'
    ];

    public function prodi(): object
    {
        //return $this->belongsTo(PsstudiModel::class, 'id_sms');
        return $this->hasOne(PsstudiModel::class, 'id_sms', 'id_sms');
    }
    
    public function tagihan(): object
    {
        return $this->hasMany(SppModel::class, 'id_reg_pd');
    }
}
