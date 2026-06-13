<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jadwal_tes', function (Blueprint $table) {
            $table->id();
            $table->string('kode_jadwal', 20)->unique();
            $table->string('judul', 200);
            $table->enum('tipe', ['tes_tertulis', 'wawancara', 'tes_praktik']);
            $table->date('tanggal');
            $table->time('jam_mulai');
            $table->time('jam_selesai');
            $table->string('lokasi', 150);
            $table->unsignedInteger('kapasitas');
            $table->string('prodi_filter', 50)->nullable();
            $table->string('jalur_filter', 20)->nullable();
            $table->enum('status', ['draft', 'published', 'selesai', 'dibatalkan'])->default('draft');
            $table->text('catatan')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal_tes');
    }
};
