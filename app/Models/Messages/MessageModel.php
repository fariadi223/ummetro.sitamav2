<?php

namespace App\Models\Messages;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MessageModel extends Model
{
    use HasFactory;

    protected $table        = 'bak_messages';
    protected $primaryKey   = 'id';
    
    public $incrementing    = true;
    public $timestamps      = false;

    protected $fillable     = [
        'id',
        'nomor_hp',
        'nama',
        'nipd',
        'nominal',
        'va_bsi',
        'va_bmi',
        'jenis_message',
        'send_id',
        'send_at',
        'send_status',
        'send_keterangan',
        'message_template_id'
    ];
}
