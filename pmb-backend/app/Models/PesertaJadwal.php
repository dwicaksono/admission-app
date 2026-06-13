<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PesertaJadwal extends Model
{
    const STATUS_ASSIGNED    = 'assigned';
    const STATUS_CONFIRMED   = 'confirmed';
    const STATUS_HADIR       = 'hadir';
    const STATUS_TIDAK_HADIR = 'tidak_hadir';
    const STATUS_RESCHEDULE  = 'reschedule_diminta';

    protected $table = 'peserta_jadwal';

    protected $fillable = [
        'jadwal_tes_id',
        'pendaftar_id',
        'status',
        'token_konfirmasi',
        'notifikasi_dikirim',
        'notifikasi_dikirim_at',
        'assigned_at',
        'confirmed_at',
        'catatan',
    ];

    protected $casts = [
        'notifikasi_dikirim'    => 'boolean',
        'notifikasi_dikirim_at' => 'datetime',
        'assigned_at'           => 'datetime',
        'confirmed_at'          => 'datetime',
    ];

    public function jadwalTes(): BelongsTo
    {
        return $this->belongsTo(JadwalTes::class);
    }

    public function pendaftar(): BelongsTo
    {
        return $this->belongsTo(Pendaftar::class);
    }

    public function rescheduleRequests(): HasMany
    {
        return $this->hasMany(RescheduleRequest::class);
    }

    public function notifikasiLogs(): HasMany
    {
        return $this->hasMany(NotifikasiLog::class);
    }
}
