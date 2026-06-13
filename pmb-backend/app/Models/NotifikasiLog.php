<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotifikasiLog extends Model
{
    protected $table = 'notifikasi_log';

    protected $fillable = [
        'peserta_jadwal_id',
        'channel',
        'subjek',
        'pesan',
        'status',
        'error_message',
        'dikirim_at',
    ];

    protected $casts = [
        'dikirim_at' => 'datetime',
    ];

    public function pesertaJadwal(): BelongsTo
    {
        return $this->belongsTo(PesertaJadwal::class);
    }
}
