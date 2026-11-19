<?php

namespace App\Models\Servicemahasiswa;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/************************************************************************
 * File ini di panggil pada file lain
 * **********************************************************************
 * App\Models\Servicemahasiswa\MahasiswaModel::prodi
 * App\Models\Mak\MataAnggaranModel::prodi
 * 
 * **********************************************************************
 */

class FakultasModel extends Model
{
    use HasFactory;

    protected $table        = 'tbl_fak';
    protected $primaryKey   = 'kode_fak';
    protected $keyType      = 'string';
    public $incrementing    = false;
    public $timestamps      = false;

    protected $fillable     = [
        'kode_fak',
        'nama_fak'
    ];
}
