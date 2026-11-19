<?php

namespace App\Models\Kpp;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

use App\Models\Kpp\KppDetailModel;
use App\Models\Servicemahasiswa\MahasiswaModel;
class KppModel extends Model
{
    use HasFactory, HasUuids;

    protected $table        = 'bak_kppmhs';
    protected $primaryKey   = 'id';
    
    public $incrementing    = false;
    public $timestamps      = false;

    protected $fillable     = [
        'id_reg_pd',
        'thn_akad',
        'smt_akad',
        'thn_smt',
        'angsur_ke',
        'create_date',
        'smt_mhs',
        'bank',
        'key_tagihan'
    ];

    public function rinci(): object
    {
        return $this->hasMany(KppDetailModel::class, 'kppmhs');
    }

    public function mahasiswa(): object
    {
        return $this->belongsTo(MahasiswaModel::class, 'id_reg_pd');
    }
}
