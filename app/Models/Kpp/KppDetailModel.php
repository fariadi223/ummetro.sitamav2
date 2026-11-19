<?php

namespace App\Models\Kpp;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class KppDetailModel extends Model
{
    use HasFactory;

    protected $table        = 'bak_kppmhs_dtl';
    protected $primaryKey   = 'kpp_dtl';
    
    public $incrementing    = false;
    public $timestamps      = false;

    protected $fillable     = [
        'kpp_dtl',
        'ma',
        'kppmhs',
        'uang',
        'keterangan'
    ];
}
