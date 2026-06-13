<?php

namespace App\Mail;

use App\Models\PesertaJadwal;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class JadwalTesMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public PesertaJadwal $pesertaJadwal) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Jadwal Tes Seleksi PMB — ' . $this->pesertaJadwal->jadwalTes->judul,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.jadwal-tes',
        );
    }
}
