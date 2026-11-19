<?php

namespace App\Models\Saldo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

use App\Models\Servicemahasiswa\MahasiswaModel;

class SaldoSppModel extends Model
{
    use HasFactory;

    protected $table        = 'bak_saldoukt';
    protected $primaryKey   = 'id';
    
    public $incrementing    = true;
    public $timestamps      = false;

    /* protected $keyType = 'string'; */

    protected $fillable     = [
        'id',
        'id_reg_pd',
        'id_jns_saldo',
        'debet',
        'kredit',
        'smt',
        'sem',
        'id_kpp',
        'id_rek',
        'uraian'
    ];

    public function mahasiswa(): object
    {
        return $this->belongsTo(MahasiswaModel::class, 'id_reg_pd');
    }
}
