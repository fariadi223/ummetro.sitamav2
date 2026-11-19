<?php

namespace App\Models\Pembayaran;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

use App\Models\Servicemahasiswa\MahasiswaModel;

class LunasSppModel extends Model
{
    use HasFactory, HasUuids;

    protected $table        = 'bak_lns';
    protected $primaryKey   = 'id_byr';
    
    public $incrementing    = false;
    public $timestamps      = false;

    protected $fillable     = [
        'id_byr',
        'id_sms',
        'id_smt',
        'id_reg_pd',
        'cetak_kpu',
        'angkatan',
        'lunas_reg',
        'uang_lunas_reg',
        'tgl_setor',
        'lunas_sks',
        'uang_lunas_sks',
        'tgl_pelunasan',
        'jumlah_sks',
        'jumlah_praktikum',
        'kwitansi_sks',
        'renpm',
        'keterangan'
    ];
    
    public function mahasiswa(): object
    {
        return $this->belongsTo(MahasiswaModel::class, 'id_reg_pd', 'id_reg_pd');
        //return $this->hasOne(MahasiswaModel::class, 'id_reg_pd', 'id_reg_pd');
    }
}
