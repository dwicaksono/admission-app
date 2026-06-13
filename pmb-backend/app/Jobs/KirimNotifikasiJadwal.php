<?php

namespace App\Jobs;

use App\Mail\JadwalTesMail;
use App\Models\NotifikasiLog;
use App\Models\PesertaJadwal;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class KirimNotifikasiJadwal implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public array $backoff = [30, 60];

    public function __construct(public PesertaJadwal $pesertaJadwal) {}

    public function handle(): void
    {
        $jadwal    = $this->pesertaJadwal->jadwalTes;
        $pendaftar = $this->pesertaJadwal->pendaftar;

        $log = NotifikasiLog::create([
            'peserta_jadwal_id' => $this->pesertaJadwal->id,
            'channel'           => 'email',
            'subjek'            => 'Jadwal Tes Seleksi PMB — ' . $jadwal->judul,
            'pesan'             => 'Notifikasi jadwal tes dikirim ke ' . $pendaftar->email,
            'status'            => 'pending',
        ]);

        try {
            Mail::to($pendaftar->email)->send(new JadwalTesMail($this->pesertaJadwal));

            $this->pesertaJadwal->update([
                'notifikasi_dikirim'    => true,
                'notifikasi_dikirim_at' => now(),
            ]);

            $log->update(['status' => 'terkirim', 'dikirim_at' => now()]);
        } catch (\Exception $e) {
            $log->update([
                'status'        => 'gagal',
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
