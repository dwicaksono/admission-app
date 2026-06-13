<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\KirimNotifikasiJadwal;
use App\Models\JadwalTes;
use App\Models\PesertaJadwal;
use App\Models\RescheduleRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RescheduleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = RescheduleRequest::with([
                'pesertaJadwal.pendaftar',
                'pesertaJadwal.jadwalTes',
                'jadwalBaru',
                'diprosesOleh',
            ])->orderByDesc('created_at');

            if ($request->filled('status') && in_array($request->status, ['pending', 'disetujui', 'ditolak'])) {
                $query->where('status', $request->status);
            }

            $list = $query->get()->map(fn ($r) => [
                'id'            => $r->id,
                'alasan'        => $r->alasan,
                'status'        => $r->status,
                'catatan_admin' => $r->catatan_admin,
                'diproses_at'   => $r->diproses_at,
                'created_at'    => $r->created_at,
                'peserta' => [
                    'nama'              => $r->pesertaJadwal->pendaftar->nama,
                    'nomor_pendaftaran' => $r->pesertaJadwal->pendaftar->nomor_pendaftaran,
                    'prodi'             => $r->pesertaJadwal->pendaftar->prodi,
                ],
                'jadwal_lama' => [
                    'id'      => $r->pesertaJadwal->jadwalTes->id,
                    'judul'   => $r->pesertaJadwal->jadwalTes->judul,
                    'tanggal' => $r->pesertaJadwal->jadwalTes->tanggal->format('Y-m-d'),
                    'tipe'    => $r->pesertaJadwal->jadwalTes->tipe,
                ],
                'jadwal_baru' => $r->jadwalBaru ? [
                    'id'      => $r->jadwalBaru->id,
                    'judul'   => $r->jadwalBaru->judul,
                    'tanggal' => $r->jadwalBaru->tanggal->format('Y-m-d'),
                ] : null,
                'diproses_oleh'     => $r->diprosesOleh?->name,
                'peserta_jadwal_id' => $r->peserta_jadwal_id,
            ]);

            return response()->json(['success' => true, 'data' => $list]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Gagal memuat data reschedule'], 500);
        }
    }

    public function process(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status'         => 'required|in:disetujui,ditolak',
            'jadwal_baru_id' => 'required_if:status,disetujui|nullable|integer|exists:jadwal_tes,id',
            'catatan_admin'  => 'nullable|string|max:500',
        ]);

        try {
            $reschedule = RescheduleRequest::with('pesertaJadwal.pendaftar')->find($id);
            if (!$reschedule) {
                return response()->json(['success' => false, 'message' => 'Request tidak ditemukan'], 404);
            }
            if ($reschedule->status !== RescheduleRequest::STATUS_PENDING) {
                return response()->json(['success' => false, 'message' => 'Request ini sudah diproses sebelumnya'], 422);
            }

            if ($request->status === 'disetujui') {
                $jadwalBaru = JadwalTes::find($request->jadwal_baru_id);
                if (!$jadwalBaru || $jadwalBaru->status !== JadwalTes::STATUS_PUBLISHED) {
                    return response()->json(['success' => false, 'message' => 'Jadwal baru tidak valid atau belum dipublish'], 422);
                }
                if ($jadwalBaru->sisa_kapasitas <= 0) {
                    return response()->json(['success' => false, 'message' => 'Jadwal baru sudah penuh, pilih jadwal lain'], 422);
                }

                $reschedule->update([
                    'status'         => RescheduleRequest::STATUS_DISETUJUI,
                    'jadwal_baru_id' => $request->jadwal_baru_id,
                    'catatan_admin'  => $request->catatan_admin,
                    'diproses_oleh'  => auth()->id(),
                    'diproses_at'    => now(),
                ]);

                $existingPj = PesertaJadwal::where('jadwal_tes_id', $request->jadwal_baru_id)
                    ->where('pendaftar_id', $reschedule->pesertaJadwal->pendaftar_id)
                    ->first();
                if ($existingPj) {
                    return response()->json(['success' => false, 'message' => 'Peserta sudah terdaftar di jadwal baru tersebut'], 422);
                }

                $oldPj = $reschedule->pesertaJadwal;

                $newPj = PesertaJadwal::create([
                    'jadwal_tes_id'    => $request->jadwal_baru_id,
                    'pendaftar_id'     => $oldPj->pendaftar_id,
                    'status'           => PesertaJadwal::STATUS_ASSIGNED,
                    'token_konfirmasi' => Str::random(64),
                    'assigned_at'      => now(),
                ]);

                $oldPj->delete();

                KirimNotifikasiJadwal::dispatch($newPj->load(['jadwalTes', 'pendaftar']));

                return response()->json(['success' => true, 'message' => 'Reschedule disetujui, jadwal baru ditetapkan dan notifikasi dikirim']);
            }

            // Ditolak
            $reschedule->update([
                'status'        => RescheduleRequest::STATUS_DITOLAK,
                'catatan_admin' => $request->catatan_admin,
                'diproses_oleh' => auth()->id(),
                'diproses_at'   => now(),
            ]);
            $reschedule->pesertaJadwal->update(['status' => PesertaJadwal::STATUS_ASSIGNED]);

            return response()->json(['success' => true, 'message' => 'Reschedule ditolak, peserta dikembalikan ke jadwal semula']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Gagal memproses reschedule: ' . $e->getMessage()], 500);
        }
    }
}
