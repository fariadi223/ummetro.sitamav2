<?php

namespace App\Models\Tagihan;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

use App\Models\Tagihan\SppModel as TagihanSppMdl;

class SppRinciModel extends Model
{
    use HasFactory, HasUuids;

    protected $table        = 'bak_tagihan_dtl';
    protected $primaryKey   = 'id';
    
    public $incrementing    = false;
    public $timestamps      = false;

    protected $fillable     = [
        'id',
        'ma',
        'id_tagihan',
        'uang',
        'keterangan',
        'post_piutang',
        'role_users'
    ];

    public function tagihan(): object
    {
        return $this->hasOne(TagihanSppMdl::class, 'id', 'id_tagihan');
    }
}