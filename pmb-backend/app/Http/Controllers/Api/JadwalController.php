<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreJadwalRequest;
use App\Http\Requests\UpdateJadwalRequest;
use App\Jobs\KirimNotifikasiJadwal;
use App\Models\JadwalTes;
use App\Models\Pendaftar;
use App\Models\PesertaJadwal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class JadwalController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $jadwalList = JadwalTes::withCount([
                'pesertaJadwal as jumlah_peserta' => fn ($q) => $q->whereNotIn('status', [PesertaJadwal::STATUS_RESCHEDULE]),
                'pesertaJadwal as jumlah_confirmed' => fn ($q) => $q->where('status', PesertaJadwal::STATUS_CONFIRMED),
            ])
                ->orderBy('tanggal', 'desc')
                ->get()
                ->map(fn ($j) => [
                    'id'               => $j->id,
                    'kode_jadwal'      => $j->kode_jadwal,
                    'judul'            => $j->judul,
                    'tipe'             => $j->tipe,
                    'tanggal'          => $j->tanggal->format('Y-m-d'),
                    'jam_mulai'        => $j->jam_mulai,
                    'jam_selesai'      => $j->jam_selesai,
                    'lokasi'           => $j->lokasi,
                    'kapasitas'        => $j->kapasitas,
                    'prodi_filter'     => $j->prodi_filter,
                    'jalur_filter'     => $j->jalur_filter,
                    'status'           => $j->status,
                    'published_at'     => $j->published_at,
                    'jumlah_peserta'   => $j->jumlah_peserta,
                    'jumlah_confirmed' => $j->jumlah_confirmed,
                    'sisa_kapasitas'   => max(0, $j->kapasitas - $j->jumlah_peserta),
                ]);

            return response()->json(['success' => true, 'data' => $jadwalList]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Gagal memuat data jadwal'], 500);
        }
    }

    public function store(StoreJadwalRequest $request): JsonResponse
    {
        try {
            $count = JadwalTes::count() + 1;
            do {
                $kode = 'JDW-' . date('Y') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
                $count++;
            } while (JadwalTes::where('kode_jadwal', $kode)->exists());

            $jadwal = JadwalTes::create([
                ...$request->validated(),
                'kode_jadwal' => $kode,
                'created_by'  => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Jadwal berhasil dibuat',
                'data'    => $jadwal,
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Gagal membuat jadwal: ' . $e->getMessage()], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        $jadwal = JadwalTes::withCount([
            'pesertaJadwal as jumlah_peserta' => fn ($q) => $q->whereNotIn('status', [PesertaJadwal::STATUS_RESCHEDULE]),
            'pesertaJadwal as jumlah_confirmed' => fn ($q) => $q->where('status', PesertaJadwal::STATUS_CONFIRMED),
        ])->find($id);
        if (!$jadwal) {
            return response()->json(['success' => false, 'message' => 'Jadwal tidak ditemukan'], 404);
        }
        return response()->json(['success' => true, 'data' => [
            'id'             => $jadwal->id,
            'kode_jadwal'    => $jadwal->kode_jadwal,
            'judul'          => $jadwal->judul,
            'tipe'           => $jadwal->tipe,
            'tanggal'        => $jadwal->tanggal->format('Y-m-d'),
            'jam_mulai'      => $jadwal->jam_mulai,
            'jam_selesai'    => $jadwal->jam_selesai,
            'lokasi'         => $jadwal->lokasi,
            'kapasitas'      => $jadwal->kapasitas,
            'prodi_filter'   => $jadwal->prodi_filter,
            'jalur_filter'   => $jadwal->jalur_filter,
            'status'         => $jadwal->status,
            'catatan'        => $jadwal->catatan,
            'published_at'   => $jadwal->published_at,
            'jumlah_peserta' => $jadwal->jumlah_peserta,
            'jumlah_confirmed' => $jadwal->jumlah_confirmed,
            'sisa_kapasitas' => max(0, $jadwal->kapasitas - $jadwal->jumlah_peserta),
        ]]);
    }

    public function update(UpdateJadwalRequest $request, int $id): JsonResponse
    {
        try {
            $jadwal = JadwalTes::find($id);
            if (!$jadwal) {
                return response()->json(['success' => false, 'message' => 'Jadwal tidak ditemukan'], 404);
            }
            if ($jadwal->status !== JadwalTes::STATUS_DRAFT) {
                return response()->json(['success' => false, 'message' => 'Jadwal yang sudah dipublish tidak dapat diedit'], 422);
            }
            $jadwal->update($request->validated());
            return response()->json(['success' => true, 'message' => 'Jadwal diperbarui', 'data' => $jadwal->fresh()]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Gagal memperbarui jadwal'], 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $jadwal = JadwalTes::find($id);
            if (!$jadwal) {
                return response()->json(['success' => false, 'message' => 'Jadwal tidak ditemukan'], 404);
            }
            if ($jadwal->status !== JadwalTes::STATUS_DRAFT) {
                return response()->json(['success' => false, 'message' => 'Hanya jadwal draft yang dapat dihapus'], 422);
            }
            if ($jadwal->pesertaJadwal()->count() > 0) {
                return response()->json(['success' => false, 'message' => 'Hapus peserta terlebih dahulu sebelum menghapus jadwal'], 422);
            }
            $jadwal->delete();
            return response()->json(['success' => true, 'message' => 'Jadwal berhasil dihapus']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Gagal menghapus jadwal'], 500);
        }
    }

    public function publish(int $id): JsonResponse
    {
        try {
            $jadwal = JadwalTes::with('pesertaJadwal.pendaftar')->find($id);
            if (!$jadwal) {
                return response()->json(['success' => false, 'message' => 'Jadwal tidak ditemukan'], 404);
            }
            if ($jadwal->status !== JadwalTes::STATUS_DRAFT) {
                return response()->json(['success' => false, 'message' => 'Jadwal sudah dipublish atau tidak dapat dipublish'], 422);
            }
            if ($jadwal->pesertaJadwal()->count() === 0) {
                return response()->json(['success' => false, 'message' => 'Tambahkan minimal 1 peserta sebelum publish'], 422);
            }

            $jadwal->update(['status' => JadwalTes::STATUS_PUBLISHED, 'published_at' => now()]);

            $count = 0;
            foreach ($jadwal->pesertaJadwal as $pj) {
                $pj->update(['token_konfirmasi' => Str::random(64)]);
                KirimNotifikasiJadwal::dispatch($pj->fresh());
                $count++;
            }

            return response()->json([
                'success'       => true,
                'message'       => "Jadwal dipublish, notifikasi dikirim ke {$count} peserta",
                'total_dikirim' => $count,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Gagal mempublish jadwal: ' . $e->getMessage()], 500);
        }
    }

    public function assignAuto(int $id): JsonResponse
    {
        try {
            $jadwal = JadwalTes::find($id);
            if (!$jadwal) {
                return response()->json(['success' => false, 'message' => 'Jadwal tidak ditemukan'], 404);
            }

            $sisaKapasitas = $jadwal->sisa_kapasitas;
            if ($sisaKapasitas <= 0) {
                return response()->json(['success' => false, 'message' => 'Slot jadwal sudah penuh'], 422);
            }

            $existingIds = PesertaJadwal::where('jadwal_tes_id', $id)
                ->whereNotIn('status', [PesertaJadwal::STATUS_RESCHEDULE])
                ->pluck('pendaftar_id');

            $query = Pendaftar::where('status', Pendaftar::STATUS_LOLOS)
                ->whereNotIn('id', $existingIds);

            if ($jadwal->prodi_filter) {
                $query->where('prodi', $jadwal->prodi_filter);
            }
            if ($jadwal->jalur_filter) {
                $query->where('jalur', $jadwal->jalur_filter);
            }

            $pendaftarList = $query->limit($sisaKapasitas)->get();

            $assigned = 0;
            foreach ($pendaftarList as $pendaftar) {
                PesertaJadwal::create([
                    'jadwal_tes_id' => $id,
                    'pendaftar_id'  => $pendaftar->id,
                    'status'        => PesertaJadwal::STATUS_ASSIGNED,
                    'assigned_at'   => now(),
                ]);
                $assigned++;
            }

            return response()->json([
                'success'  => true,
                'message'  => "{$assigned} peserta berhasil di-assign ke jadwal ini",
                'assigned' => $assigned,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Gagal auto-assign: ' . $e->getMessage()], 500);
        }
    }

    public function assignManual(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'pendaftar_ids'   => 'required|array|max:50',
            'pendaftar_ids.*' => 'integer|exists:pendaftars,id',
        ]);

        try {
            $jadwal   = JadwalTes::find($id);
            if (!$jadwal) {
                return response()->json(['success' => false, 'message' => 'Jadwal tidak ditemukan'], 404);
            }

            $assigned = 0;
            $skipped  = [];

            foreach ($request->pendaftar_ids as $pendaftarId) {
                $pendaftar = Pendaftar::find($pendaftarId);
                if (!$pendaftar || $pendaftar->status !== Pendaftar::STATUS_LOLOS) {
                    $skipped[] = ['id' => $pendaftarId, 'alasan' => 'Status bukan Lolos Seleksi'];
                    continue;
                }
                $jadwal->refresh();
                if ($jadwal->sisa_kapasitas <= 0) {
                    $skipped[] = ['id' => $pendaftarId, 'alasan' => 'Kapasitas jadwal penuh'];
                    continue;
                }
                $exists = PesertaJadwal::where('jadwal_tes_id', $id)
                    ->where('pendaftar_id', $pendaftarId)
                    ->whereNotIn('status', [PesertaJadwal::STATUS_RESCHEDULE])
                    ->exists();
                if ($exists) {
                    $skipped[] = ['id' => $pendaftarId, 'alasan' => 'Sudah terdaftar di jadwal ini'];
                    continue;
                }
                PesertaJadwal::create([
                    'jadwal_tes_id' => $id,
                    'pendaftar_id'  => $pendaftarId,
                    'status'        => PesertaJadwal::STATUS_ASSIGNED,
                    'assigned_at'   => now(),
                ]);
                $assigned++;
            }

            return response()->json([
                'success'  => true,
                'message'  => "{$assigned} peserta berhasil ditambahkan",
                'assigned' => $assigned,
                'skipped'  => $skipped,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Gagal assign: ' . $e->getMessage()], 500);
        }
    }

    public function pesertaList(int $id): JsonResponse
    {
        try {
            $jadwal = JadwalTes::find($id);
            if (!$jadwal) {
                return response()->json(['success' => false, 'message' => 'Jadwal tidak ditemukan'], 404);
            }

            $peserta = PesertaJadwal::with('pendaftar')
                ->where('jadwal_tes_id', $id)
                ->get()
                ->map(fn ($pj) => [
                    'id'                    => $pj->id,
                    'status'                => $pj->status,
                    'notifikasi_dikirim'    => $pj->notifikasi_dikirim,
                    'notifikasi_dikirim_at' => $pj->notifikasi_dikirim_at,
                    'assigned_at'           => $pj->assigned_at,
                    'confirmed_at'          => $pj->confirmed_at,
                    'catatan'               => $pj->catatan,
                    'pendaftar' => [
                        'id'                => $pj->pendaftar->id,
                        'nama'              => $pj->pendaftar->nama,
                        'nomor_pendaftaran' => $pj->pendaftar->nomor_pendaftaran,
                        'prodi'             => $pj->pendaftar->prodi,
                        'jalur'             => $pj->pendaftar->jalur,
                        'email'             => $pj->pendaftar->email,
                        'nomor_hp'          => $pj->pendaftar->nomor_hp,
                    ],
                ]);

            return response()->json(['success' => true, 'data' => $peserta]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Gagal memuat daftar peserta'], 500);
        }
    }

    public function unassign(int $jadwalId, int $pesertaJadwalId): JsonResponse
    {
        try {
            $jadwal = JadwalTes::find($jadwalId);
            if (!$jadwal) {
                return response()->json(['success' => false, 'message' => 'Jadwal tidak ditemukan'], 404);
            }
            if ($jadwal->status !== JadwalTes::STATUS_DRAFT) {
                return response()->json(['success' => false, 'message' => 'Hanya bisa hapus peserta dari jadwal draft'], 422);
            }
            $pj = PesertaJadwal::where('id', $pesertaJadwalId)->where('jadwal_tes_id', $jadwalId)->first();
            if (!$pj) {
                return response()->json(['success' => false, 'message' => 'Data peserta tidak ditemukan'], 404);
            }
            $pj->delete();
            return response()->json(['success' => true, 'message' => 'Peserta berhasil dihapus dari jadwal']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Gagal menghapus peserta'], 500);
        }
    }

    public function updateKehadiran(Request $request, int $jadwalId, int $pesertaJadwalId): JsonResponse
    {
        $request->validate(['status' => 'required|in:hadir,tidak_hadir']);

        try {
            $jadwal = JadwalTes::find($jadwalId);
            if (!$jadwal || $jadwal->status === JadwalTes::STATUS_DRAFT) {
                return response()->json(['success' => false, 'message' => 'Jadwal belum dipublish, tidak dapat mencatat kehadiran'], 422);
            }
            $pj = PesertaJadwal::where('id', $pesertaJadwalId)->where('jadwal_tes_id', $jadwalId)->first();
            if (!$pj) {
                return response()->json(['success' => false, 'message' => 'Data peserta tidak ditemukan'], 404);
            }
            if (!in_array($pj->status, [PesertaJadwal::STATUS_ASSIGNED, PesertaJadwal::STATUS_CONFIRMED, PesertaJadwal::STATUS_HADIR, PesertaJadwal::STATUS_TIDAK_HADIR])) {
                return response()->json(['success' => false, 'message' => 'Peserta dengan status reschedule tidak dapat dicatat kehadirannya'], 422);
            }
            $pj->update(['status' => $request->status]);
            return response()->json(['success' => true, 'message' => 'Status kehadiran diperbarui', 'data' => $pj->fresh()]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Gagal memperbarui kehadiran'], 500);
        }
    }

    public function resendNotifikasi(int $id): JsonResponse
    {
        try {
            $jadwal = JadwalTes::find($id);
            if (!$jadwal || $jadwal->status !== JadwalTes::STATUS_PUBLISHED) {
                return response()->json(['success' => false, 'message' => 'Jadwal tidak ditemukan atau belum dipublish'], 422);
            }

            $pesertaBelumNotif = PesertaJadwal::with('pendaftar')
                ->where('jadwal_tes_id', $id)
                ->where('notifikasi_dikirim', false)
                ->get();

            $count = 0;
            foreach ($pesertaBelumNotif as $pj) {
                if (!$pj->token_konfirmasi) {
                    $pj->update(['token_konfirmasi' => Str::random(64)]);
                }
                KirimNotifikasiJadwal::dispatch($pj->fresh());
                $count++;
            }

            return response()->json([
                'success'  => true,
                'message'  => "Notifikasi dikirim ulang ke {$count} peserta",
                'terkirim' => $count,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Gagal mengirim ulang notifikasi'], 500);
        }
    }
}
