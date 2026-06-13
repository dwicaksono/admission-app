<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JadwalTes;
use App\Models\Pendaftar;
use App\Models\PesertaJadwal;
use App\Models\RescheduleRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class JadwalPublikController extends Controller
{
    public function show(string $nomorPendaftaran): JsonResponse
    {
        $pendaftar = Pendaftar::where('nomor_pendaftaran', $nomorPendaftaran)->first();
        if (!$pendaftar) {
            return response()->json(['success' => false, 'message' => 'Nomor pendaftaran tidak ditemukan'], 404);
        }

        $pj = PesertaJadwal::with('jadwalTes')
            ->where('pendaftar_id', $pendaftar->id)
            ->whereNotIn('status', [PesertaJadwal::STATUS_RESCHEDULE])
            ->whereHas('jadwalTes', fn ($q) => $q->where('status', JadwalTes::STATUS_PUBLISHED))
            ->orderByDesc('assigned_at')
            ->first();

        if (!$pj) {
            return response()->json(['success' => true, 'data' => ['has_jadwal' => false]]);
        }

        $jadwal     = $pj->jadwalTes;
        $sudahLewat = Carbon::parse($jadwal->tanggal)->isPast();

        $hasPendingReschedule = RescheduleRequest::where('peserta_jadwal_id', $pj->id)
            ->where('status', RescheduleRequest::STATUS_PENDING)
            ->exists();

        return response()->json([
            'success' => true,
            'data'    => [
                'has_jadwal' => true,
                'jadwal'     => [
                    'id'                     => $jadwal->id,
                    'judul'                  => $jadwal->judul,
                    'tipe'                   => $jadwal->tipe,
                    'tanggal'                => $jadwal->tanggal->format('Y-m-d'),
                    'jam_mulai'              => $jadwal->jam_mulai,
                    'jam_selesai'            => $jadwal->jam_selesai,
                    'lokasi'                 => $jadwal->lokasi,
                    'status_jadwal'          => $jadwal->status,
                    'status_peserta'         => $pj->status,
                    'sudah_lewat'            => $sudahLewat,
                    'has_pending_reschedule' => $hasPendingReschedule,
                    'peserta_jadwal_id'      => $pj->id,
                ],
            ],
        ]);
    }

    public function konfirmasi(Request $request, string $nomorPendaftaran): JsonResponse
    {
        $request->validate(['token_konfirmasi' => 'required|string']);

        $pendaftar = Pendaftar::where('nomor_pendaftaran', $nomorPendaftaran)->first();
        if (!$pendaftar) {
            return response()->json(['success' => false, 'message' => 'Nomor pendaftaran tidak ditemukan'], 404);
        }

        $pj = PesertaJadwal::where('pendaftar_id', $pendaftar->id)
            ->where('token_konfirmasi', $request->token_konfirmasi)
            ->where('status', PesertaJadwal::STATUS_ASSIGNED)
            ->first();

        if (!$pj) {
            return response()->json(['success' => false, 'message' => 'Token tidak valid atau kehadiran sudah dikonfirmasi sebelumnya'], 422);
        }

        $pj->update(['status' => PesertaJadwal::STATUS_CONFIRMED, 'confirmed_at' => now()]);

        return response()->json(['success' => true, 'message' => 'Kehadiran berhasil dikonfirmasi. Sampai jumpa di hari H!']);
    }

    public function requestReschedule(Request $request, string $nomorPendaftaran): JsonResponse
    {
        $request->validate(['alasan' => 'required|string|min:10|max:500']);

        $pendaftar = Pendaftar::where('nomor_pendaftaran', $nomorPendaftaran)->first();
        if (!$pendaftar) {
            return response()->json(['success' => false, 'message' => 'Nomor pendaftaran tidak ditemukan'], 404);
        }

        $pj = PesertaJadwal::with('jadwalTes')
            ->where('pendaftar_id', $pendaftar->id)
            ->whereIn('status', [PesertaJadwal::STATUS_ASSIGNED, PesertaJadwal::STATUS_CONFIRMED])
            ->whereHas('jadwalTes', fn ($q) => $q->where('status', JadwalTes::STATUS_PUBLISHED))
            ->orderByDesc('assigned_at')
            ->first();

        if (!$pj) {
            return response()->json(['success' => false, 'message' => 'Tidak ada jadwal aktif yang bisa di-reschedule'], 422);
        }

        if (Carbon::parse($pj->jadwalTes->tanggal)->isPast()) {
            return response()->json(['success' => false, 'message' => 'Jadwal sudah lewat, reschedule tidak dapat diproses'], 422);
        }

        $existingRequest = RescheduleRequest::where('peserta_jadwal_id', $pj->id)
            ->where('status', RescheduleRequest::STATUS_PENDING)
            ->exists();

        if ($existingRequest) {
            return response()->json(['success' => false, 'message' => 'Anda sudah memiliki permintaan reschedule yang sedang diproses'], 422);
        }

        RescheduleRequest::create([
            'peserta_jadwal_id' => $pj->id,
            'alasan'            => $request->alasan,
            'status'            => RescheduleRequest::STATUS_PENDING,
        ]);

        $pj->update(['status' => PesertaJadwal::STATUS_RESCHEDULE]);

        return response()->json(['success' => true, 'message' => 'Permintaan reschedule berhasil dikirim. Admin akan memproses segera.']);
    }
}
