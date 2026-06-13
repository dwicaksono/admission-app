<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifikasi_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('peserta_jadwal_id')->constrained('peserta_jadwal')->cascadeOnDelete();
            $table->enum('channel', ['email', 'in_app'])->default('email');
            $table->string('subjek', 200)->nullable();
            $table->text('pesan');
            $table->enum('status', ['pending', 'terkirim', 'gagal'])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('dikirim_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifikasi_log');
    }
};
