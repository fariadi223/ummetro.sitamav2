<?php

namespace App\Models\Servicemahasiswa;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/************************************************************************
 * File ini di panggil pada file lain
 * **********************************************************************
 * App\Models\Mak\MataAnggaranModel::prodi
 * 
 * **********************************************************************
 */

class SemesterModel extends Model
{
    use HasFactory;

    protected $table        = 'tbl_smt';
    protected $primaryKey   = 'id_smt';
    
    public $incrementing    = false;
    public $timestamps      = false;

    protected $fillable     = [
        'id_smt',
        'id_thn_ajaran',
        'smt',
        'nm_smt',
        'tgl_mulai',
        'tgl_selesai',
    ];
}
