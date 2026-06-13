<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('peserta_jadwal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jadwal_tes_id')->constrained('jadwal_tes')->cascadeOnDelete();
            $table->foreignId('pendaftar_id')->constrained('pendaftars')->cascadeOnDelete();
            $table->enum('status', ['assigned', 'confirmed', 'hadir', 'tidak_hadir', 'reschedule_diminta'])->default('assigned');
            $table->string('token_konfirmasi', 64)->unique()->nullable();
            $table->boolean('notifikasi_dikirim')->default(false);
            $table->timestamp('notifikasi_dikirim_at')->nullable();
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamp('confirmed_at')->nullable();
            $table->text('catatan')->nullable();
            $table->unique(['jadwal_tes_id', 'pendaftar_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('peserta_jadwal');
    }
};
