<?php

namespace App\Models\Mak;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use App\Models\Servicemahasiswa\PsstudiModel;

/************************************************************************
 * File ini di panggil pada file lain
 * **********************************************************************
 * App\Repositories\Mak\BiayaRepository
 * 
 * **********************************************************************
 */


class MataAnggaranModel extends Model
{
    use HasFactory, HasUuids;

    protected $table        = 'bak_mak';
    protected $primaryKey   = 'id';
    
    public $incrementing    = false;
    public $timestamps      = false;

    protected $fillable     = [
        'id',
        'ma',
        'kode',
        'nama',
        'kelas',
        'sms',
        'angkatan',
        'uang',
        'prasarat',
        'kredit',
        'praktik',
        'pilihmhs',
        'sumber_id',
        'sumber_nama'
    ];

    public function prodi(): object
    {
        return $this->belongsTo(PsstudiModel::class, 'sms');
    }
}
