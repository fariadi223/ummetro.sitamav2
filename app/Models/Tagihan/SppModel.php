<?php

namespace App\Models\Tagihan;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

use App\Models\Tagihan\SppRinciModel;
use App\Models\Servicemahasiswa\MahasiswaModel;

class SppModel extends Model
{
    use HasFactory, HasUuids;

    protected $table        = 'bak_tagihan';
    protected $primaryKey   = 'id';
    
    public $incrementing    = false;
    public $timestamps      = false;

    protected $fillable     = [
        'id',
        'id_reg_pd',
        'thn_akad',
        'thn_tghn',
        'bln_tghn',
        'smt_akad',
        'sarat',
        'input_date',
        'mhssmt',
        'thnsmt',
        'lunas',
        'lunas_date',
        'bank',
        'api_key',
        'struk_id'
    ];
    
    public function rinci(): object
    {
        return $this->hasMany(SppRinciModel::class, 'id_tagihan');
    }

    public function mahasiswa(): object
    {
        //return $this->belongsTo(MahasiswaModel::class, 'id_reg_pd');
        return $this->hasOne(MahasiswaModel::class, 'id_reg_pd', 'id_reg_pd');
    }
}
