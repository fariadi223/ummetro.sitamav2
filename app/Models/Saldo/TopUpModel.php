<?php

namespace App\Models\Saldo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TopUpModel extends Model
{
    use HasFactory;

    protected $table        = 'bak_tagihan_saldo';
    protected $primaryKey   = 'id';
    
    public $incrementing    = true;
    public $timestamps      = true;

    /* protected $keyType = 'string'; */

    const CREATED_AT = 'top_up_tanggal_buat';
    const UPDATED_AT = 'top_up_tanggal_edit'; 

    protected $fillable     = [
        'id',
        'top_up_jenis_id',
        'top_up_mhs_id',
        'top_up_nomor',
        'top_up_jumlah',
        'top_up_uraian',
        'top_up_jurnal_id'
    ];
}