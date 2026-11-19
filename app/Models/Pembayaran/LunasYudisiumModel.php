<?php

namespace App\Models\Pembayaran;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class LunasYudisiumModel extends Model
{
    use HasFactory, HasUuids;

    protected $table        = 'bak_lns_yudisium';
    protected $primaryKey   = 'npm';
    
    public $incrementing    = false;
    public $timestamps      = false;

    protected $fillable     = [
        'psstudi',
        'npm',
        'nama_mhs',
        'nama_prg',
        'tanggal_lns'
    ];
}
