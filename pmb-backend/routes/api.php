<?php

use App\Http\Controllers\Api\AdminAuthController;
use App\Http\Controllers\Api\JadwalController;
use App\Http\Controllers\Api\JadwalPublikController;
use App\Http\Controllers\Api\PendaftarController;
use App\Http\Controllers\Api\RescheduleController;
use Illuminate\Support\Facades\Route;

/*
 * API Routes — Sistem PMB
 * Semua route di bawah prefix /api secara otomatis
 */

// --- Auth (max 10 attempts per minute per IP) ---
Route::post('/auth/login', [AdminAuthController::class, 'login'])
    ->middleware('throttle:10,1');

// --- Publik (tidak butuh auth) ---
Route::middleware('throttle:60,1')->group(function () {
    Route::post('/pendaftar', [PendaftarController::class, 'store']);
    Route::get('/pendaftar/{nomorPendaftaran}', [PendaftarController::class, 'show'])
        ->where('nomorPendaftaran', 'PMB-[0-9]{4}-[0-9]{4}');
    Route::post('/pendaftar/{nomorPendaftaran}/heregistrasi', [PendaftarController::class, 'heregistrasi'])
        ->where('nomorPendaftaran', 'PMB-[0-9]{4}-[0-9]{4}');
});

// --- Admin (butuh Sanctum token) ---
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AdminAuthController::class, 'logout']);
    Route::get('/pendaftar', [PendaftarController::class, 'index']);
    Route::patch('/pendaftar/{id}/status', [PendaftarController::class, 'updateStatus']);
    Route::get('/statistik', [PendaftarController::class, 'statistik']);
    Route::get('/pendaftar/export/csv', [PendaftarController::class, 'exportCsv']);

    // --- Jadwal Management (admin) ---
    Route::get('/admin/jadwal', [JadwalController::class, 'index']);
    Route::post('/admin/jadwal', [JadwalController::class, 'store']);
    Route::get('/admin/jadwal/{id}', [JadwalController::class, 'show']);
    Route::put('/admin/jadwal/{id}', [JadwalController::class, 'update']);
    Route::delete('/admin/jadwal/{id}', [JadwalController::class, 'destroy']);
    Route::post('/admin/jadwal/{id}/publish', [JadwalController::class, 'publish']);
    Route::post('/admin/jadwal/{id}/assign-auto', [JadwalController::class, 'assignAuto']);
    Route::post('/admin/jadwal/{id}/assign', [JadwalController::class, 'assignManual']);
    Route::get('/admin/jadwal/{id}/peserta', [JadwalController::class, 'pesertaList']);
    Route::delete('/admin/jadwal/{jadwalId}/peserta/{pesertaJadwalId}', [JadwalController::class, 'unassign']);
    Route::patch('/admin/jadwal/{jadwalId}/peserta/{pesertaJadwalId}/kehadiran', [JadwalController::class, 'updateKehadiran']);
    Route::post('/admin/jadwal/{id}/kirim-notifikasi', [JadwalController::class, 'resendNotifikasi']);

    // --- Reschedule Management (admin) ---
    Route::get('/admin/reschedule', [RescheduleController::class, 'index']);
    Route::patch('/admin/reschedule/{id}', [RescheduleController::class, 'process']);
});

// --- Publik: Jadwal Peserta (rate limit ketat pada mutasi) ---
Route::get('/jadwal/{nomorPendaftaran}', [JadwalPublikController::class, 'show'])
    ->where('nomorPendaftaran', 'PMB-[0-9]{4}-[0-9]{4}')
    ->middleware('throttle:60,1');
Route::post('/jadwal/{nomorPendaftaran}/konfirmasi', [JadwalPublikController::class, 'konfirmasi'])
    ->where('nomorPendaftaran', 'PMB-[0-9]{4}-[0-9]{4}')
    ->middleware('throttle:10,1');
Route::post('/jadwal/{nomorPendaftaran}/reschedule', [JadwalPublikController::class, 'requestReschedule'])
    ->where('nomorPendaftaran', 'PMB-[0-9]{4}-[0-9]{4}')
    ->middleware('throttle:5,1');
