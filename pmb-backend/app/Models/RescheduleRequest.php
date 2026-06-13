<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RescheduleRequest extends Model
{
    const STATUS_PENDING   = 'pending';
    const STATUS_DISETUJUI = 'disetujui';
    const STATUS_DITOLAK   = 'ditolak';

    protected $table = 'reschedule_requests';

    protected $fillable = [
        'peserta_jadwal_id',
        'alasan',
        'jadwal_baru_id',
        'status',
        'catatan_admin',
        'diproses_oleh',
        'diproses_at',
    ];

    protected $casts = [
        'diproses_at' => 'datetime',
    ];

    public function pesertaJadwal(): BelongsTo
    {
        return $this->belongsTo(PesertaJadwal::class);
    }

    public function jadwalBaru(): BelongsTo
    {
        return $this->belongsTo(JadwalTes::class, 'jadwal_baru_id');
    }

    public function diprosesOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diproses_oleh');
    }
}
