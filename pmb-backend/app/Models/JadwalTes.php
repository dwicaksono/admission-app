<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JadwalTes extends Model
{
    const STATUS_DRAFT      = 'draft';
    const STATUS_PUBLISHED  = 'published';
    const STATUS_SELESAI    = 'selesai';
    const STATUS_DIBATALKAN = 'dibatalkan';

    const TIPE_LIST = ['tes_tertulis', 'wawancara', 'tes_praktik'];

    const TIPE_LABELS = [
        'tes_tertulis' => 'Tes Tertulis',
        'wawancara'    => 'Wawancara',
        'tes_praktik'  => 'Tes Praktik',
    ];

    protected $table = 'jadwal_tes';

    protected $fillable = [
        'kode_jadwal',
        'judul',
        'tipe',
        'tanggal',
        'jam_mulai',
        'jam_selesai',
        'lokasi',
        'kapasitas',
        'prodi_filter',
        'jalur_filter',
        'status',
        'catatan',
        'created_by',
        'published_at',
    ];

    protected $casts = [
        'tanggal'      => 'date',
        'published_at' => 'datetime',
        'kapasitas'    => 'integer',
    ];

    public function pesertaJadwal(): HasMany
    {
        return $this->hasMany(PesertaJadwal::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getJumlahPesertaAttribute(): int
    {
        return $this->pesertaJadwal()
            ->whereNotIn('status', [PesertaJadwal::STATUS_RESCHEDULE])
            ->count();
    }

    public function getSisaKapasitasAttribute(): int
    {
        return max(0, $this->kapasitas - $this->jumlah_peserta);
    }
}
